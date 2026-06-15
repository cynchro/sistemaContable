<?php

namespace App\Support\Entitlements;

use PDO;
use DateTimeImmutable;
use App\Support\Contracts\EntitlementResolverInterface;

/**
 * Lee los entitlements efectivos de `tenant_entitlements`. El base solo LEE;
 * la tabla la puebla billing (source='billing:*') o se carga a mano
 * (source='manual'). Ver ADR 0001.
 */
class DbEntitlementResolver implements EntitlementResolverInterface
{
    public function __construct(private PDO $pdo)
    {
    }

    public function for(string $tenantId): EntitlementSet
    {
        $stmt = $this->pdo->prepare(
            'SELECT feature, type, limit_value, enabled, period_start, period_end, expires_at
             FROM tenant_entitlements WHERE tenant_id = ?'
        );
        $stmt->execute([$tenantId]);

        /** @var list<array<string, mixed>> $rows */
        $rows = (array) $stmt->fetchAll(PDO::FETCH_ASSOC);

        $byFeature = [];
        foreach ($rows as $row) {
            $feature             = (string) $row['feature'];
            $byFeature[$feature] = new Entitlement(
                feature: $feature,
                type: (string) $row['type'],
                limit: $row['limit_value'] !== null ? (int) $row['limit_value'] : null,
                enabled: (bool) $row['enabled'],
                periodStart: $this->date($row['period_start']),
                periodEnd: $this->date($row['period_end']),
                expiresAt: $this->date($row['expires_at']),
            );
        }

        return new EntitlementSet($byFeature);
    }

    private function date(mixed $value): ?DateTimeImmutable
    {
        return $value !== null && $value !== '' ? new DateTimeImmutable((string) $value) : null;
    }
}
