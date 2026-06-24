<?php

namespace App\Modules\Fiscal\Repositories;

use PDO;
use App\Exceptions\NotFoundException;

/**
 * Catálogo de tributos por tenant (estudio). Jerárquico vía `parent_id`.
 */
class TributoRepository
{
    private const WRITABLE = ['parent_id', 'nombre', 'tipo_persona', 'subcategoria', 'is_activo'];

    public function __construct(private PDO $pdo)
    {
    }

    /** @return list<array<string, mixed>> */
    public function findAll(string $tenantId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM tributos WHERE tenant_id = ? ORDER BY nombre');
        $stmt->execute([$tenantId]);

        return (array) $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array<string, mixed> */
    public function findById(int $id, string $tenantId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM tributos WHERE id = ? AND tenant_id = ?');
        $stmt->execute([$id, $tenantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            throw new NotFoundException('Tributo', $id);
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

        $sql = sprintf(
            'INSERT INTO tributos (%s) VALUES (%s)',
            implode(', ', $columns),
            implode(', ', $placeholders),
        );
        $this->pdo->prepare($sql)->execute($fields);

        return $this->findById((int) $this->pdo->lastInsertId(), $tenantId);
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

        $stmt = $this->pdo->prepare("UPDATE tributos SET {$set} WHERE id = :id AND tenant_id = :tenant_id");
        $stmt->execute($fields);

        return $stmt->rowCount() > 0;
    }

    public function delete(int $id, string $tenantId): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM tributos WHERE id = ? AND tenant_id = ?');
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
