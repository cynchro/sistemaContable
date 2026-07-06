<?php

namespace App\Modules\Iva\Repositories;

use PDO;
use App\Exceptions\NotFoundException;
use App\Helpers\PaginatorHelper;

/**
 * Persistencia del agregado Compra = cabecera + discriminaciones + percepciones.
 * Simétrico a VentaRepository (lado proveedor; la discriminación lleva cf_computable).
 * Acotado a `periodo_id`; la pertenencia del período la valida el Service. Los
 * multi-insert corren dentro de la transacción que abre el Service.
 */
class CompraRepository
{
    private const HEADER_WRITABLE = [
        'tipo_comprobante_id', 'condicion_iva_id', 'provincia_id', 'rubro_id',
        'tipo_operacion_compra_id', 'tipo_moneda_id', 'proveedor_id', 'fecha',
        'proveedor_nombre', 'cuit', 'letra', 'punto_venta', 'numero',
        'neto_no_grav', 'exento', 'imp_interno', 'total', 'tipo_cambio', 'concepto',
        'cai', 'fecha_cai', 'actividad_id', 'concepto_dj', 'campo_auxiliar',
    ];

    private const DISCRIMINACION_WRITABLE = [
        'neto_gravado', 'iva_alicuota', 'iva_importe', 'iva_inc_alicuota',
        'iva_inc_importe', 'cf_computable',
    ];

    private const PERCEPCION_WRITABLE = ['tipo_retencion_id', 'provincia_id', 'base', 'alicuota', 'importe'];

    public function __construct(private PDO $pdo)
    {
    }

    /** @return list<array<string, mixed>> Cabeceras (sin anidar) del período. */
    public function findAllByPeriodo(int $periodoId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM compras WHERE periodo_id = ? ORDER BY fecha, id');
        $stmt->execute([$periodoId]);

        return (array) $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Listado paginado y filtrado del período. Los nombres de columna del WHERE son
     * literales del código; los valores van por placeholders (sin inyección).
     *
     * @param array{
     *   fecha_desde?: ?string, fecha_hasta?: ?string, proveedor_id?: ?int,
     *   cuit?: ?string, letra?: ?string, nombre?: ?string, numero?: ?string, orden?: ?string
     * } $filtros
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
        if (!empty($filtros['proveedor_id'])) {
            $where[]  = 'proveedor_id = ?';
            $params[] = (int) $filtros['proveedor_id'];
        }
        if (!empty($filtros['cuit'])) {
            $where[]  = 'cuit = ?';
            $params[] = $filtros['cuit'];
        }
        if (!empty($filtros['letra'])) {
            $where[]  = 'letra = ?';
            $params[] = $filtros['letra'];
        }
        if (!empty($filtros['nombre'])) {
            $where[]  = 'proveedor_nombre LIKE ?';
            $params[] = '%' . $filtros['nombre'] . '%';
        }
        if (!empty($filtros['numero'])) {
            $where[]  = 'numero LIKE ?';
            $params[] = '%' . $filtros['numero'] . '%';
        }

        $orden = ($filtros['orden'] ?? '') === 'nombre' ? 'proveedor_nombre, fecha, id' : 'fecha, id';
        $query = 'SELECT * FROM compras WHERE ' . implode(' AND ', $where) . ' ORDER BY ' . $orden;

        return (new PaginatorHelper($this->pdo, $query, $page, $perPage, true, $params))->getPaginatedResults();
    }

    /** @return array<string, mixed> Cabecera con discriminaciones y percepciones. */
    public function findById(int $id, int $periodoId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM compras WHERE id = ? AND periodo_id = ?');
        $stmt->execute([$id, $periodoId]);
        $compra = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$compra) {
            throw new NotFoundException('Compra', $id);
        }

        $disStmt = $this->pdo->prepare('SELECT * FROM compra_discriminaciones WHERE compra_id = ? ORDER BY id');
        $disStmt->execute([$id]);
        $compra['discriminaciones'] = (array) $disStmt->fetchAll(PDO::FETCH_ASSOC);

        // Percepciones sufridas del comprobante (con datos del tipo: clasificación RG y código AFIP).
        $percStmt = $this->pdo->prepare(
            'SELECT p.*, tr.tipo_rg3685, tr.cod_afip AS tipo_cod_afip, tr.nombre AS tipo_nombre
             FROM compra_percepciones p
             LEFT JOIN tipos_retencion tr ON tr.id = p.tipo_retencion_id
             WHERE p.compra_id = ? ORDER BY p.id'
        );
        $percStmt->execute([$id]);
        $compra['percepciones'] = (array) $percStmt->fetchAll(PDO::FETCH_ASSOC);

        return $compra;
    }

    /**
     * Inserta el agregado completo. Debe llamarse dentro de una transacción.
     *
     * @param  array<string, mixed>       $header
     * @param  list<array<string, mixed>> $discriminaciones
     * @param  list<array<string, mixed>> $percepciones     percepciones del comprobante (integran el total)
     * @return array<string, mixed>
     */
    public function create(array $header, array $discriminaciones, array $percepciones, int $periodoId): array
    {
        $headerFields = $this->filter($header, self::HEADER_WRITABLE) + ['periodo_id' => $periodoId];
        $compraId = $this->insert('compras', $headerFields);

        $this->insertHijos($compraId, $discriminaciones, $percepciones);

        return $this->findById($compraId, $periodoId);
    }

    /**
     * Reemplaza el agregado (cabecera + hijos). Debe llamarse dentro de una transacción.
     *
     * @param  array<string, mixed>       $header
     * @param  list<array<string, mixed>> $discriminaciones
     * @param  list<array<string, mixed>> $percepciones
     * @return array<string, mixed>
     */
    public function replace(int $id, array $header, array $discriminaciones, array $percepciones, int $periodoId): array
    {
        $fields = $this->filter($header, self::HEADER_WRITABLE);

        if ($fields !== []) {
            $set = implode(', ', array_map(static fn (string $c) => "{$c} = :{$c}", array_keys($fields)));
            $fields['id']         = $id;
            $fields['periodo_id'] = $periodoId;
            $stmt = $this->pdo->prepare("UPDATE compras SET {$set} WHERE id = :id AND periodo_id = :periodo_id");
            $stmt->execute($fields);
        }

        $this->pdo->prepare('DELETE FROM compra_discriminaciones WHERE compra_id = ?')->execute([$id]);
        $this->pdo->prepare('DELETE FROM compra_percepciones WHERE compra_id = ?')->execute([$id]);

        $this->insertHijos($id, $discriminaciones, $percepciones);

        return $this->findById($id, $periodoId);
    }

    /** Reasigna el comprobante a otro período (mover). Las reglas las valida el Service. */
    public function moverAPeriodo(int $id, int $periodoOrigen, int $periodoDestino): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE compras SET periodo_id = :destino WHERE id = :id AND periodo_id = :origen'
        );
        $stmt->execute(['destino' => $periodoDestino, 'id' => $id, 'origen' => $periodoOrigen]);

        return $stmt->rowCount() > 0;
    }

    public function delete(int $id, int $periodoId): bool
    {
        // compra_discriminaciones y compra_percepciones caen por FK ON DELETE CASCADE.
        $stmt = $this->pdo->prepare('DELETE FROM compras WHERE id = ? AND periodo_id = ?');
        $stmt->execute([$id, $periodoId]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Inserta las discriminaciones y percepciones de un comprobante.
     *
     * @param list<array<string, mixed>> $discriminaciones
     * @param list<array<string, mixed>> $percepciones
     */
    private function insertHijos(int $compraId, array $discriminaciones, array $percepciones): void
    {
        foreach ($discriminaciones as $dis) {
            $disFields = $this->filter($dis, self::DISCRIMINACION_WRITABLE);
            $disFields['compra_id'] = $compraId;
            $this->insert('compra_discriminaciones', $disFields);
        }

        foreach ($percepciones as $perc) {
            $percFields = $this->filter($perc, self::PERCEPCION_WRITABLE);
            $percFields['compra_id'] = $compraId;
            $this->insert('compra_percepciones', $percFields);
        }
    }

    /**
     * Busca un comprobante de compra duplicado en la empresa: mismo proveedor (CUIT) +
     * tipo + letra + punto de venta + número. Incluye el CUIT porque distintos proveedores
     * pueden repetir punto de venta/número. pv/número se comparan por valor numérico.
     * Devuelve el id existente o null. Excluye `$exceptId` (para update).
     */
    public function findDuplicado(
        int $empresaId,
        ?string $cuit,
        ?int $tipoId,
        ?string $letra,
        string $puntoVenta,
        string $numero,
        int $exceptId = 0,
    ): ?int {
        $stmt = $this->pdo->prepare(
            'SELECT c.id FROM compras c
             JOIN periodos p ON p.id = c.periodo_id
             WHERE p.empresa_id = :empresa
               AND COALESCE(c.cuit, \'\') = :cuit
               AND c.tipo_comprobante_id <=> :tipo
               AND UPPER(COALESCE(c.letra, \'\')) = :letra
               AND CAST(c.punto_venta AS UNSIGNED) = :pv
               AND CAST(c.numero AS UNSIGNED) = :numero
               AND c.id <> :except
             LIMIT 1'
        );
        $stmt->execute([
            'empresa' => $empresaId,
            'cuit'    => trim((string) $cuit),
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
