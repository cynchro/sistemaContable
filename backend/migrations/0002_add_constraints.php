<?php

return new class {
    public function up(\PDO $pdo): void
    {
        // Unique constraint on usuarios.usuario to prevent duplicate usernames atomically
        $pdo->exec('ALTER TABLE usuarios ADD CONSTRAINT uq_usuarios_usuario UNIQUE (usuario)');

        // Index on usuarios.token for fast AuthMiddleware lookups
        $pdo->exec('ALTER TABLE usuarios MODIFY COLUMN token VARCHAR(1000) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL');
        $pdo->exec('CREATE INDEX idx_usuarios_token ON usuarios (token(255))');
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP INDEX idx_usuarios_token ON usuarios');
        $pdo->exec('ALTER TABLE usuarios DROP INDEX uq_usuarios_usuario');
    }
};
