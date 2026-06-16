<?php

namespace App\Modules\Iva\Repositories;

use PDO;
use App\Exceptions\NotFoundException;

/**
 * Persistencia del agregado Venta = cabecera + discriminaciones + retenciones.
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
        'cai', 'fecha_cai',
    ];

    private const DISCRIMINACION_WRITABLE = [
        'neto_gravado', 'iva_alicuota', 'iva_importe', 'iva_inc_alicuota',
        'iva_inc_importe', 'reintegro_t', 'concepto',
    ];

    private const RETENCION_WRITABLE = ['tipo_retencion_id', 'porcentaje', 'importe'];

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
        $discriminaciones = (array) $disStmt->fetchAll(PDO::FETCH_ASSOC);

        $retStmt = $this->pdo->prepare(
            'SELECT * FROM venta_retenciones WHERE venta_discriminacion_id = ? ORDER BY id'
        );

        foreach ($discriminaciones as &$dis) {
            $retStmt->execute([$dis['id']]);
            $dis['retenciones'] = (array) $retStmt->fetchAll(PDO::FETCH_ASSOC);
        }
        unset($dis);

        $venta['discriminaciones'] = $discriminaciones;

        return $venta;
    }

    /**
     * Inserta el agregado completo. Debe llamarse dentro de una transacción.
     *
     * @param  array<string, mixed>                                 $header
     * @param  list<array<string, mixed>>                           $discriminaciones cada una con 'retenciones'
     * @return array<string, mixed>
     */
    public function create(array $header, array $discriminaciones, int $periodoId): array
    {
        $headerFields = $this->filter($header, self::HEADER_WRITABLE) + ['periodo_id' => $periodoId];
        $ventaId = $this->insert('ventas', $headerFields);

        foreach ($discriminaciones as $dis) {
            $disFields = $this->filter($dis, self::DISCRIMINACION_WRITABLE);
            $disFields['venta_id'] = $ventaId;
            $disId = $this->insert('venta_discriminaciones', $disFields);

            foreach ((array) ($dis['retenciones'] ?? []) as $ret) {
                $retFields = $this->filter($ret, self::RETENCION_WRITABLE);
                $retFields['venta_discriminacion_id'] = $disId;
                $this->insert('venta_retenciones', $retFields);
            }
        }

        return $this->findById($ventaId, $periodoId);
    }

    /**
     * Reemplaza el agregado (cabecera + hijos). Debe llamarse dentro de una transacción.
     *
     * @param  array<string, mixed>       $header
     * @param  list<array<string, mixed>> $discriminaciones
     * @return array<string, mixed>
     */
    public function replace(int $id, array $header, array $discriminaciones, int $periodoId): array
    {
        $fields = $this->filter($header, self::HEADER_WRITABLE);

        if ($fields !== []) {
            $set = implode(', ', array_map(static fn (string $c) => "{$c} = :{$c}", array_keys($fields)));
            $fields['id']         = $id;
            $fields['periodo_id'] = $periodoId;
            $stmt = $this->pdo->prepare("UPDATE ventas SET {$set} WHERE id = :id AND periodo_id = :periodo_id");
            $stmt->execute($fields);
        }

        // Las retenciones caen por cascade al borrar las discriminaciones.
        $del = $this->pdo->prepare('DELETE FROM venta_discriminaciones WHERE venta_id = ?');
        $del->execute([$id]);

        foreach ($discriminaciones as $dis) {
            $disFields = $this->filter($dis, self::DISCRIMINACION_WRITABLE);
            $disFields['venta_id'] = $id;
            $disId = $this->insert('venta_discriminaciones', $disFields);

            foreach ((array) ($dis['retenciones'] ?? []) as $ret) {
                $retFields = $this->filter($ret, self::RETENCION_WRITABLE);
                $retFields['venta_discriminacion_id'] = $disId;
                $this->insert('venta_retenciones', $retFields);
            }
        }

        return $this->findById($id, $periodoId);
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

    public function delete(int $id, int $periodoId): bool
    {
        // venta_discriminaciones y venta_retenciones caen por FK ON DELETE CASCADE.
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
