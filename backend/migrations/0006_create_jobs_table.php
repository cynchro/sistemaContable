<?php

// Migration: Create Jobs Table

return new class {
    public function up(\PDO $pdo): void
    {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS jobs (
                id           BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT PRIMARY KEY,
                queue        VARCHAR(100)     NOT NULL DEFAULT 'default',
                payload      MEDIUMTEXT       NOT NULL,
                attempts     TINYINT UNSIGNED NOT NULL DEFAULT 0,
                max_attempts TINYINT UNSIGNED NOT NULL DEFAULT 3,
                status       VARCHAR(20)      NOT NULL DEFAULT 'pending',
                available_at TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
                reserved_at  TIMESTAMP        NULL,
                reserved_by  CHAR(36)         NULL,
                failed_at    TIMESTAMP        NULL,
                error        TEXT             NULL,
                created_at   TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_queue_status (queue, status, available_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS jobs');
    }
};
