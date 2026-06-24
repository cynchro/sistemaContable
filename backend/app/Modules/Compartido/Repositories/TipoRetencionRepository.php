<?php

namespace App\Modules\Compartido\Repositories;

use PDO;
use App\Exceptions\NotFoundException;

/**
 * Tipos de retención/percepción. Hay estándar de AFIP (tenant_id NULL, read-only) y propios
 * de cada estudio (tenant_id = tenant). El listado devuelve ambos; el ABM opera solo sobre
 * los propios del estudio.
 */
class TipoRetencionRepository
{
    private const WRITABLE = ['cod_afip', 'alicuota', 'nombre', 'tipo_rg3685', 'provincia_id', 'base_calculo'];

    public function __construct(private PDO $pdo)
    {
    }

    /** Estándar (globales) + propios del estudio. @return list<array<string, mixed>> */
    public function findAllForTenant(string $tenantId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM tipos_retencion WHERE tenant_id IS NULL OR tenant_id = ? ORDER BY nombre'
        );
        $stmt->execute([$tenantId]);

        return (array) $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Visible para el estudio (global o propio). @return array<string, mixed> */
    public function findVisible(int $id, string $tenantId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM tipos_retencion WHERE id = ? AND (tenant_id IS NULL OR tenant_id = ?)'
        );
        $stmt->execute([$id, $tenantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            throw new NotFoundException('Tipo de retención', $id);
        }

        return $row;
    }

    /** Propio del estudio (para editar/borrar). Las estándar de AFIP no son del estudio → 404. */
    public function findOwn(int $id, string $tenantId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM tipos_retencion WHERE id = ? AND tenant_id = ?');
        $stmt->execute([$id, $tenantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            throw new NotFoundException('Tipo de retención', $id);
        }

        return $row;
    }

    /**
     * @param  array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function create(array $data, string $tenantId): array
    {
        $fields = $this->writableFrom($data);
        $fields['tenant_id'] = $tenantId;

        $columns      = array_keys($fields);
        $placeholders = array_map(static fn (string $c) => ":{$c}", $columns);

        $this->pdo->prepare(sprintf(
            'INSERT INTO tipos_retencion (%s) VALUES (%s)',
            implode(', ', $columns),
            implode(', ', $placeholders),
        ))->execute($fields);

        return $this->findVisible((int) $this->pdo->lastInsertId(), $tenantId);
    }

    /** @param array<string, mixed> $data */
    public function update(int $id, array $data, string $tenantId): bool
    {
        $fields = $this->writableFrom($data);

        if ($fields === []) {
            return false;
        }

        $set = implode(', ', array_map(static fn (string $c) => "{$c} = :{$c}", array_keys($fields)));
        $fields['id']        = $id;
        $fields['tenant_id'] = $tenantId;

        $stmt = $this->pdo->prepare(
            "UPDATE tipos_retencion SET {$set} WHERE id = :id AND tenant_id = :tenant_id"
        );
        $stmt->execute($fields);

        return $stmt->rowCount() > 0;
    }

    public function delete(int $id, string $tenantId): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM tipos_retencion WHERE id = ? AND tenant_id = ?');
        $stmt->execute([$id, $tenantId]);

        return $stmt->rowCount() > 0;
    }

    /**
     * @param  array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function writableFrom(array $data): array
    {
        return array_intersect_key($data, array_flip(self::WRITABLE));
    }
}
