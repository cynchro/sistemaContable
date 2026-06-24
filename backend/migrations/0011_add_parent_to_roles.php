<?php

return new class {
    public function up(\PDO $pdo): void
    {
        // Jerarquía de roles: un rol puede heredar los permisos de su rol padre.
        // ON DELETE SET NULL: borrar el padre no borra al hijo, solo lo desvincula.
        $pdo->exec("
            ALTER TABLE roles
                ADD COLUMN parent_id INT DEFAULT NULL AFTER nombre,
                ADD CONSTRAINT fk_roles_parent
                    FOREIGN KEY (parent_id) REFERENCES roles(id) ON DELETE SET NULL
        ");
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('ALTER TABLE roles DROP FOREIGN KEY fk_roles_parent');
        $pdo->exec('ALTER TABLE roles DROP COLUMN parent_id');
    }
};
