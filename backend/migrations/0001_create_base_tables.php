<?php

return new class {
    public function up(\PDO $pdo): void
    {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS tenants (
                id     CHAR(36)     PRIMARY KEY DEFAULT (UUID()),
                nombre VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS roles (
                id     INT         NOT NULL AUTO_INCREMENT PRIMARY KEY,
                nombre VARCHAR(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                estado VARCHAR(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS permisos (
                id      INT         NOT NULL AUTO_INCREMENT PRIMARY KEY,
                permiso VARCHAR(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                estado  INT         DEFAULT NULL COMMENT '0: sin permiso, 1: lectura, 2: lectura-escritura'
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS roles_permisos (
                id      INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                rol     INT DEFAULT NULL,
                permiso INT DEFAULT NULL,
                estado  INT DEFAULT NULL COMMENT '0: sin permiso, 1: lectura, 2: lectura-escritura'
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS usuarios (
                id            INT           NOT NULL AUTO_INCREMENT PRIMARY KEY,
                usuario       VARCHAR(255)  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                clave         VARCHAR(1000) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
                rol           INT           DEFAULT NULL,
                token         VARCHAR(1000) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                desarrollador INT           DEFAULT NULL,
                tenant_id     CHAR(36)      NOT NULL,
                FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS usuarios');
        $pdo->exec('DROP TABLE IF EXISTS roles_permisos');
        $pdo->exec('DROP TABLE IF EXISTS permisos');
        $pdo->exec('DROP TABLE IF EXISTS roles');
        $pdo->exec('DROP TABLE IF EXISTS tenants');
    }
};
