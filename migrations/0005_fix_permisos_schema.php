<?php

return new class {
    public function up(\PDO $pdo): void
    {
        // Rename the legacy 'permiso' column to 'key' and add missing 'descripcion'
        $pdo->exec("
            ALTER TABLE permisos
                CHANGE COLUMN permiso `key` VARCHAR(45)
                    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                ADD COLUMN descripcion VARCHAR(255)
                    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL
                    AFTER `key`
        ");
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec("
            ALTER TABLE permisos
                DROP COLUMN descripcion,
                CHANGE COLUMN `key` permiso VARCHAR(45)
                    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL
        ");
    }
};
