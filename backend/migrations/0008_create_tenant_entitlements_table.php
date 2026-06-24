<?php

// Migration: Create Tenant Entitlements Table
//
// Entitlements efectivos por tenant. El base solo LEE esta tabla; la puebla
// billing (source='billing:*') o se carga a mano (source='manual'). Los campos
// period_start/period_end (para type='quota') los mantiene billing y los
// denormaliza acá para que el resolver no dependa de la tabla subscriptions.
// Ver docs/adr/0001-saas-identity-entitlements-billing.md (D2).

return new class {
    public function up(\PDO $pdo): void
    {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS tenant_entitlements (
                id           BIGINT       NOT NULL AUTO_INCREMENT PRIMARY KEY,
                tenant_id    CHAR(36)     NOT NULL,
                feature      VARCHAR(120) NOT NULL,
                type         ENUM('flag','quota','seat') NOT NULL,
                limit_value  BIGINT       NULL,
                enabled      TINYINT(1)   NOT NULL DEFAULT 1,
                source       VARCHAR(60)  NOT NULL DEFAULT 'manual',
                period_start DATETIME     NULL,
                period_end   DATETIME     NULL,
                expires_at   DATETIME     NULL,
                updated_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_tenant_feature (tenant_id, feature),
                FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS tenant_entitlements');
    }
};
