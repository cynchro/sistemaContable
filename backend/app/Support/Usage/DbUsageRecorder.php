<?php

namespace App\Support\Usage;

use PDO;
use DateTimeInterface;
use App\Support\Contracts\UsageRecorderInterface;

/**
 * Metering DB-backed sobre `usage_events`. Registro liviano: el rating/cobro de
 * este uso lo hace billing (Fase 5+). El conteo por ciclo usa `occurred_at`.
 */
class DbUsageRecorder implements UsageRecorderInterface
{
    public function __construct(private PDO $pdo)
    {
    }

    public function record(
        string $tenantId,
        string $metric,
        int $qty = 1,
        ?string $idempotencyKey = null,
        array $meta = []
    ): void {
        $metaJson = $meta !== [] ? json_encode($meta) : null;

        // INSERT IGNORE: con idempotency_key (UNIQUE) un reintento no duplica.
        $stmt = $this->pdo->prepare(
            'INSERT IGNORE INTO usage_events (tenant_id, metric, quantity, idempotency_key, meta, occurred_at)
             VALUES (?, ?, ?, ?, ?, NOW())'
        );
        $stmt->execute([$tenantId, $metric, $qty, $idempotencyKey, $metaJson]);
    }

    public function total(string $tenantId, string $metric, DateTimeInterface $since): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT COALESCE(SUM(quantity), 0) FROM usage_events
             WHERE tenant_id = ? AND metric = ? AND occurred_at >= ?'
        );
        $stmt->execute([$tenantId, $metric, $since->format('Y-m-d H:i:s')]);

        return (int) $stmt->fetchColumn();
    }
}
