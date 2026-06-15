<?php

// Migration: Create API Keys Table

return new class {
    public function up(\PDO $pdo): void
    {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS api_keys (
                id           CHAR(36)     NOT NULL PRIMARY KEY,
                tenant_id    CHAR(36)     NOT NULL,
                name         VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
                prefix       VARCHAR(32)  NOT NULL,
                hash         CHAR(64)     NOT NULL,
                scopes       JSON         NULL,
                last_used_at DATETIME     NULL,
                expires_at   DATETIME     NULL,
                revoked_at   DATETIME     NULL,
                created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_prefix (prefix),
                INDEX idx_tenant (tenant_id),
                FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS api_keys');
    }
};
