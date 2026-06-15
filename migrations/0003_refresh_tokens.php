<?php

return new class {
    public function up(\PDO $pdo): void
    {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS refresh_tokens (
                id         INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
                user_id    INT          NOT NULL,
                token      VARCHAR(128) NOT NULL,
                expires_at DATETIME     NOT NULL,
                created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT uq_refresh_token UNIQUE (token),
                CONSTRAINT fk_refresh_user FOREIGN KEY (user_id) REFERENCES usuarios(id) ON DELETE CASCADE,
                INDEX idx_refresh_expires (user_id, expires_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS refresh_tokens');
    }
};
