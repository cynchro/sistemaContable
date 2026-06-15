<?php

return new class {
    public function up(\PDO $pdo): void
    {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS clientes (
                id        INT      NOT NULL AUTO_INCREMENT PRIMARY KEY,
                tenant_id CHAR(36) NOT NULL,
                CONSTRAINT fk_clientes_tenant
                    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
                INDEX idx_clientes_tenant (tenant_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS clientes');
    }
};
