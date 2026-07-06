<?php

namespace App\Modules\Iva\Repositories;

use PDO;
use App\Exceptions\NotFoundException;
use App\Helpers\PaginatorHelper;

/**
 * Persistencia del agregado Venta = cabecera + discriminaciones + percepciones.
 * Acotado a `periodo_id`; la pertenencia del período a la empresa/tenant la valida
 * el Service. Los multi-insert se ejecutan dentro de la transacción que abre el
 * Service (DB::withTransaction).
 */
class VentaRepository
{
    private const HEADER_WRITABLE = [
        'tipo_comprobante_id', 'tipo_documento_id', 'condicion_iva_id', 'provincia_id',
        'rubro_id', 'tipo_operacion_venta_id', 'tipo_moneda_id', 'cliente_id', 'fecha',
        'cliente_nombre', 'cuit', 'letra', 'punto_venta', 'numero', 'numero_fin',
        'neto_no_grav', 'exento', 'imp_interno', 'total', 'tipo_cambio', 'concepto',
        'cai', 'fecha_cai', 'actividad_id', 'es_bien_uso', 'campo_auxiliar',
    ];

    private const DISCRIMINACION_WRITABLE = [
        'neto_gravado', 'iva_alicuota', 'iva_importe', 'iva_inc_alicuota',
        'iva_inc_importe', 'reintegro_t', 'concepto',
    ];

    private const PERCEPCION_WRITABLE = ['tipo_retencion_id', 'provincia_id', 'base', 'alicuota', 'importe'];

    private const ASOCIADO_WRITABLE = ['tipo_comprobante_id', 'letra', 'punto_venta', 'numero', 'cuit', 'fecha'];

    public function __construct(private PDO $pdo)
    {
    }

    /** @return list<array<string, mixed>> Cabeceras (sin anidar) del período. */
    public function findAllByPeriodo(int $periodoId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM ventas WHERE periodo_id = ? ORDER BY fecha, id');
        $stmt->execute([$periodoId]);

        return (array) $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Listado paginado y filtrado del período. Los nombres de columna del WHERE son
     * literales del código; los valores van por placeholders (sin inyección).
     *
     * @param  array<string, mixed> $filtros fecha_desde/hasta, cliente_id, letra, nombre, numero, orden
     * @return array<string, mixed> total / cantidad_por_pagina / pagina / results
     */
    public function findPaginado(int $periodoId, array $filtros, int $page, int $perPage): array
    {
        $where  = ['periodo_id = ?'];
        $params = [$periodoId];

        if (!empty($filtros['fecha_desde'])) {
            $where[]  = 'fecha >= ?';
            $params[] = $filtros['fecha_desde'];
        }
        if (!empty($filtros['fecha_hasta'])) {
            $where[]  = 'fecha <= ?';
            $params[] = $filtros['fecha_hasta'];
        }
        if (!empty($filtros['cliente_id'])) {
            $where[]  = 'cliente_id = ?';
            $params[] = (int) $filtros['cliente_id'];
        }
        if (!empty($filtros['letra'])) {
            $where[]  = 'letra = ?';
            $params[] = $filtros['letra'];
        }
        if (!empty($filtros['nombre'])) {
            $where[]  = 'cliente_nombre LIKE ?';
            $params[] = '%' . $filtros['nombre'] . '%';
        }
        if (!empty($filtros['numero'])) {
            $where[]  = 'numero LIKE ?';
            $params[] = '%' . $filtros['numero'] . '%';
        }

        $orden = ($filtros['orden'] ?? '') === 'nombre' ? 'cliente_nombre, fecha, id' : 'fecha, id';
        $query = 'SELECT * FROM ventas WHERE ' . implode(' AND ', $where) . ' ORDER BY ' . $orden;

        return (new PaginatorHelper($this->pdo, $query, $page, $perPage, true, $params))->getPaginatedResults();
    }

    /** @return array<string, mixed> Cabecera con discriminaciones y sus retenciones. */
    public function findById(int $id, int $periodoId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM ventas WHERE id = ? AND periodo_id = ?');
        $stmt->execute([$id, $periodoId]);
        $venta = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$venta) {
            throw new NotFoundException('Venta', $id);
        }

        $disStmt = $this->pdo->prepare('SELECT * FROM venta_discriminaciones WHERE venta_id = ? ORDER BY id');
        $disStmt->execute([$id]);
        $venta['discriminaciones'] = (array) $disStmt->fetchAll(PDO::FETCH_ASSOC);

        // Percepciones del comprobante (con datos del tipo: clasificación RG y código AFIP).
        $percStmt = $this->pdo->prepare(
            'SELECT p.*, tr.tipo_rg3685, tr.cod_afip AS tipo_cod_afip, tr.nombre AS tipo_nombre
             FROM venta_percepciones p
             LEFT JOIN tipos_retencion tr ON tr.id = p.tipo_retencion_id
             WHERE p.venta_id = ? ORDER BY p.id'
        );
        $percStmt->execute([$id]);
        $venta['percepciones'] = (array) $percStmt->fetchAll(PDO::FETCH_ASSOC);

        // Comprobantes asociados (con el codigo de tipo, para resolver el CbteTipo AFIP).
        $asocStmt = $this->pdo->prepare(
            'SELECT a.*, tc.codigo AS tipo_codigo
             FROM venta_comprobantes_asociados a
             LEFT JOIN tipos_comprobante tc ON tc.id = a.tipo_comprobante_id
             WHERE a.venta_id = ? ORDER BY a.id'
        );
        $asocStmt->execute([$id]);
        $venta['comprobantes_asociados'] = (array) $asocStmt->fetchAll(PDO::FETCH_ASSOC);

        return $venta;
    }

    /**
     * Inserta el agregado completo. Debe llamarse dentro de una transacción.
     *
     * @param  array<string, mixed>       $header
     * @param  list<array<string, mixed>> $discriminaciones
     * @param  list<array<string, mixed>> $percepciones     percepciones del comprobante (integran el total)
     * @param  list<array<string, mixed>> $asociados        comprobantes asociados (NC/ND)
     * @return array<string, mixed>
     */
    public function create(
        array $header,
        array $discriminaciones,
        array $percepciones,
        array $asociados,
        int $periodoId,
    ): array {
        $headerFields = $this->filter($header, self::HEADER_WRITABLE) + ['periodo_id' => $periodoId];
        $ventaId = $this->insert('ventas', $headerFields);

        $this->insertHijos($ventaId, $discriminaciones, $percepciones);
        $this->insertAsociados($ventaId, $asociados);

        return $this->findById($ventaId, $periodoId);
    }

    /**
     * Reemplaza el agregado (cabecera + hijos). Debe llamarse dentro de una transacción.
     *
     * @param  array<string, mixed>       $header
     * @param  list<array<string, mixed>> $discriminaciones
     * @param  list<array<string, mixed>> $percepciones
     * @param  list<array<string, mixed>> $asociados
     * @return array<string, mixed>
     */
    public function replace(
        int $id,
        array $header,
        array $discriminaciones,
        array $percepciones,
        array $asociados,
        int $periodoId,
    ): array {
        $fields = $this->filter($header, self::HEADER_WRITABLE);

        if ($fields !== []) {
            $set = implode(', ', array_map(static fn (string $c) => "{$c} = :{$c}", array_keys($fields)));
            $fields['id']         = $id;
            $fields['periodo_id'] = $periodoId;
            $stmt = $this->pdo->prepare("UPDATE ventas SET {$set} WHERE id = :id AND periodo_id = :periodo_id");
            $stmt->execute($fields);
        }

        $this->pdo->prepare('DELETE FROM venta_discriminaciones WHERE venta_id = ?')->execute([$id]);
        $this->pdo->prepare('DELETE FROM venta_percepciones WHERE venta_id = ?')->execute([$id]);
        $this->insertHijos($id, $discriminaciones, $percepciones);

        $this->pdo->prepare('DELETE FROM venta_comprobantes_asociados WHERE venta_id = ?')->execute([$id]);
        $this->insertAsociados($id, $asociados);

        return $this->findById($id, $periodoId);
    }

    /**
     * Inserta las discriminaciones y percepciones de un comprobante.
     *
     * @param list<array<string, mixed>> $discriminaciones
     * @param list<array<string, mixed>> $percepciones
     */
    private function insertHijos(int $ventaId, array $discriminaciones, array $percepciones): void
    {
        foreach ($discriminaciones as $dis) {
            $disFields = $this->filter($dis, self::DISCRIMINACION_WRITABLE);
            $disFields['venta_id'] = $ventaId;
            $this->insert('venta_discriminaciones', $disFields);
        }

        foreach ($percepciones as $perc) {
            $percFields = $this->filter($perc, self::PERCEPCION_WRITABLE);
            $percFields['venta_id'] = $ventaId;
            $this->insert('venta_percepciones', $percFields);
        }
    }

    /** @param list<array<string, mixed>> $asociados */
    private function insertAsociados(int $ventaId, array $asociados): void
    {
        foreach ($asociados as $asoc) {
            $fields = $this->filter($asoc, self::ASOCIADO_WRITABLE);
            $fields['venta_id'] = $ventaId;
            $this->insert('venta_comprobantes_asociados', $fields);
        }
    }

    /**
     * Busca un comprobante de venta duplicado en la empresa (mismo tipo + letra +
     * punto de venta + número), comparando pv/número por valor numérico (ignora ceros
     * a la izquierda). Devuelve el id existente o null. Excluye `$exceptId` (para update).
     */
    public function findDuplicado(
        int $empresaId,
        ?int $tipoId,
        ?string $letra,
        string $puntoVenta,
        string $numero,
        int $exceptId = 0,
    ): ?int {
        $stmt = $this->pdo->prepare(
            'SELECT v.id FROM ventas v
             JOIN periodos p ON p.id = v.periodo_id
             WHERE p.empresa_id = :empresa
               AND v.tipo_comprobante_id <=> :tipo
               AND UPPER(COALESCE(v.letra, \'\')) = :letra
               AND CAST(v.punto_venta AS UNSIGNED) = :pv
               AND CAST(v.numero AS UNSIGNED) = :numero
               AND v.id <> :except
             LIMIT 1'
        );
        $stmt->execute([
            'empresa' => $empresaId,
            'tipo'    => $tipoId,
            'letra'   => strtoupper(trim((string) $letra)),
            'pv'      => (int) $puntoVenta,
            'numero'  => (int) $numero,
            'except'  => $exceptId,
        ]);
        $id = $stmt->fetchColumn();

        return $id === false ? null : (int) $id;
    }

    /**
     * Persiste el resultado de la autorización electrónica (CAE) sobre la cabecera.
     *
     * @param array<string, mixed> $fields subconjunto de numero/cae/cae_vto/afip_resultado/afip_obs
     */
    public function updateCae(int $id, int $periodoId, array $fields): void
    {
        $fields = $this->filter($fields, ['numero', 'cae', 'cae_vto', 'afip_resultado', 'afip_obs']);

        if ($fields === []) {
            return;
        }

        $set = implode(', ', array_map(static fn (string $c) => "{$c} = :{$c}", array_keys($fields)));
        $fields['id']         = $id;
        $fields['periodo_id'] = $periodoId;

        $stmt = $this->pdo->prepare("UPDATE ventas SET {$set} WHERE id = :id AND periodo_id = :periodo_id");
        $stmt->execute($fields);
    }

    /** Reasigna el comprobante a otro período (mover). Las reglas las valida el Service. */
    public function moverAPeriodo(int $id, int $periodoOrigen, int $periodoDestino): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE ventas SET periodo_id = :destino WHERE id = :id AND periodo_id = :origen'
        );
        $stmt->execute(['destino' => $periodoDestino, 'id' => $id, 'origen' => $periodoOrigen]);

        return $stmt->rowCount() > 0;
    }

    public function delete(int $id, int $periodoId): bool
    {
        // venta_discriminaciones y venta_percepciones caen por FK ON DELETE CASCADE.
        $stmt = $this->pdo->prepare('DELETE FROM ventas WHERE id = ? AND periodo_id = ?');
        $stmt->execute([$id, $periodoId]);

        return $stmt->rowCount() > 0;
    }

    /**
     * @param  array<string, mixed> $fields
     * @return int  id insertado
     */
    private function insert(string $table, array $fields): int
    {
        $columns      = array_keys($fields);
        $placeholders = array_map(static fn (string $c) => ":{$c}", $columns);

        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $table,
            implode(', ', $columns),
            implode(', ', $placeholders),
        );
        $this->pdo->prepare($sql)->execute($fields);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * @param  array<string, mixed> $data
     * @param  list<string>         $allowed
     * @return array<string, mixed>
     */
    private function filter(array $data, array $allowed): array
    {
        return array_intersect_key($data, array_flip($allowed));
    }
}
