<?php

/**
 * Añade la columna de ejemplo `nombre` al CRUD de scaffolding `clientes`.
 *
 * Va como migración propia (no editando 0004) para respetar la inmutabilidad de
 * las migraciones ya publicadas: las instalaciones nuevas y las existentes
 * convergen al mismo esquema. Los checks contra information_schema la hacen
 * idempotente ante reejecuciones o columnas añadidas a mano.
 */
return new class {
    public function up(\PDO $pdo): void
    {
        if ($this->columnExists($pdo, 'clientes', 'nombre')) {
            return;
        }
        $pdo->exec('ALTER TABLE clientes ADD COLUMN nombre VARCHAR(255) NOT NULL');
    }

    public function down(\PDO $pdo): void
    {
        if (!$this->columnExists($pdo, 'clientes', 'nombre')) {
            return;
        }
        $pdo->exec('ALTER TABLE clientes DROP COLUMN nombre');
    }

    private function columnExists(\PDO $pdo, string $table, string $column): bool
    {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.columns
             WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?'
        );
        $stmt->execute([$table, $column]);

        return (int) $stmt->fetchColumn() > 0;
    }
};
