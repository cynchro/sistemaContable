<?php

// Migration: Create Usage Events Table
//
// Registro liviano de uso (metering). El base solo REGISTRA y SUMA; el rating/
// cobro de este uso es de billing (Fase 5+). El conteo por ciclo se hace con
// occurred_at >= period_start del entitlement. Ver ADR 0001 (D2).

return new class {
    public function up(\PDO $pdo): void
    {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS usage_events (
                id              BIGINT       NOT NULL AUTO_INCREMENT PRIMARY KEY,
                tenant_id       CHAR(36)     NOT NULL,
                metric          VARCHAR(120) NOT NULL,
                quantity        BIGINT       NOT NULL DEFAULT 1,
                idempotency_key VARCHAR(120) NULL,
                meta            JSON         NULL,
                occurred_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_idem (idempotency_key),
                INDEX idx_tenant_metric_time (tenant_id, metric, occurred_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS usage_events');
    }
};
