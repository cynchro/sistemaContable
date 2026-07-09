<?php

namespace App\Modules\Sueldos\Repositories;

use PDO;
use App\Exceptions\NotFoundException;

/**
 * Persistencia de contribuciones patronales: definiciones por empresa, base
 * imponible (desde los recibos) y resultados liquidados por empleado.
 */
class ContribucionRepository
{
    private const WRITABLE = [
        'descripcion', 'porcentaje', 'importe_fijo', 'incluye_norem',
        'aplica_detraccion', 'aplica_topes', 'tope_min', 'tope_max', 'cuenta_id', 'orden',
    ];

    /** Monto de la detracción vigente (Dto 99/2019) configurado a nivel de empresa. */
    public function detraccionMonto(int $empresaId): string
    {
        $stmt = $this->pdo->prepare('SELECT detraccion_monto FROM sueldos_empresa_config WHERE empresa_id = ?');
        $stmt->execute([$empresaId]);
        $v = $stmt->fetchColumn();

        return ($v === false || $v === null) ? '0' : (string) $v;
    }

    public function __construct(private PDO $pdo)
    {
    }

    /** @return list<array<string, mixed>> */
    public function findAllByEmpresa(int $empresaId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM contribuciones WHERE empresa_id = ? ORDER BY orden, descripcion');
        $stmt->execute([$empresaId]);

        return (array) $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array<string, mixed> */
    public function findById(int $id, int $empresaId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM contribuciones WHERE id = ? AND empresa_id = ?');
        $stmt->execute([$id, $empresaId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            throw new NotFoundException('Contribucion', $id);
        }

        return $row;
    }

    /**
     * @param  array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function create(array $data, int $empresaId): array
    {
        $fields = array_intersect_key($data, array_flip(self::WRITABLE));
        $fields['empresa_id'] = $empresaId;

        $columns      = array_keys($fields);
        $placeholders = array_map(static fn (string $c) => ":{$c}", $columns);

        $sql = sprintf(
            'INSERT INTO contribuciones (%s) VALUES (%s)',
            implode(', ', $columns),
            implode(', ', $placeholders),
        );
        $this->pdo->prepare($sql)->execute($fields);

        return $this->findById((int) $this->pdo->lastInsertId(), $empresaId);
    }

    /** @param array<string, mixed> $data */
    public function update(int $id, array $data, int $empresaId): bool
    {
        $fields = array_intersect_key($data, array_flip(self::WRITABLE));

        if ($fields === []) {
            return false;
        }

        $set = implode(', ', array_map(static fn (string $c) => "{$c} = :{$c}", array_keys($fields)));
        $fields['id']         = $id;
        $fields['empresa_id'] = $empresaId;

        $stmt = $this->pdo->prepare("UPDATE contribuciones SET {$set} WHERE id = :id AND empresa_id = :empresa_id");
        $stmt->execute($fields);

        return $stmt->rowCount() > 0;
    }

    public function delete(int $id, int $empresaId): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM contribuciones WHERE id = ? AND empresa_id = ?');
        $stmt->execute([$id, $empresaId]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Base imponible (remunerativa y no remunerativa) desde los recibos del empleado.
     *
     * @return array{remunerativo: string, no_remunerativo: string}
     */
    public function baseImponible(int $liquidacionId, int $empleadoId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT
                COALESCE(SUM(CASE WHEN tipo = 1 THEN importe ELSE 0 END), 0) AS rem,
                COALESCE(SUM(CASE WHEN tipo = 2 THEN importe ELSE 0 END), 0) AS norem
               FROM recibos WHERE liquidacion_id = ? AND empleado_id = ?'
        );
        $stmt->execute([$liquidacionId, $empleadoId]);
        /** @var array<string, mixed> $row */
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'remunerativo'    => (string) ($row['rem'] ?? '0'),
            'no_remunerativo' => (string) ($row['norem'] ?? '0'),
        ];
    }

    /** @return list<array<string, mixed>> */
    public function liquidadas(int $liquidacionId, int $empleadoId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM contribuciones_liquidadas WHERE liquidacion_id = ? AND empleado_id = ? ORDER BY id'
        );
        $stmt->execute([$liquidacionId, $empleadoId]);

        return (array) $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Reemplaza las contribuciones liquidadas del empleado. Dentro de transacción.
     *
     * @param list<array<string, mixed>> $lineas
     */
    public function replaceLiquidadas(int $liquidacionId, int $empleadoId, array $lineas): void
    {
        $del = $this->pdo->prepare(
            'DELETE FROM contribuciones_liquidadas WHERE liquidacion_id = ? AND empleado_id = ?'
        );
        $del->execute([$liquidacionId, $empleadoId]);

        $ins = $this->pdo->prepare(
            'INSERT INTO contribuciones_liquidadas
                (liquidacion_id, empleado_id, contribucion_id, descripcion,
                 base_imponible, porcentaje, importe_fijo, importe_total)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        foreach ($lineas as $l) {
            $ins->execute([
                $liquidacionId,
                $empleadoId,
                $l['contribucion_id'] ?? null,
                $l['descripcion'] ?? null,
                $l['base_imponible'] ?? 0,
                $l['porcentaje'] ?? 0,
                $l['importe_fijo'] ?? 0,
                $l['importe_total'] ?? 0,
            ]);
        }
    }
}
