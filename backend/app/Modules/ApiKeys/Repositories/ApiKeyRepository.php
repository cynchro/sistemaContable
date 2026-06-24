<?php

namespace App\Modules\ApiKeys\Repositories;

use PDO;
use App\Exceptions\NotFoundException;

class ApiKeyRepository
{
    /** Columnas seguras para exponer — nunca incluye `hash`. */
    private const COLUMNS =
        'id, tenant_id, name, prefix, scopes, last_used_at, expires_at, revoked_at, created_at';

    public function __construct(private PDO $pdo)
    {
    }

    /** @return list<array<string, mixed>> */
    public function allForTenant(string $tenantId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT ' . self::COLUMNS . ' FROM api_keys WHERE tenant_id = ? ORDER BY created_at DESC'
        );
        $stmt->execute([$tenantId]);

        /** @var list<array<string, mixed>> $rows */
        $rows = (array) $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map([$this, 'present'], $rows);
    }

    /** @return array<string, mixed> */
    public function findForTenant(string $id, string $tenantId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT ' . self::COLUMNS . ' FROM api_keys WHERE id = ? AND tenant_id = ?'
        );
        $stmt->execute([$id, $tenantId]);

        /** @var array<string, mixed>|false $row */
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            throw new NotFoundException('ApiKey', $id);
        }

        return $this->present($row);
    }

    /**
     * @param  array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function present(array $row): array
    {
        $row['scopes']  = isset($row['scopes'])
            ? (json_decode((string) $row['scopes'], true) ?: [])
            : [];
        $row['revoked'] = ($row['revoked_at'] ?? null) !== null;

        return $row;
    }
}
