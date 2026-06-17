<?php

/**
 * Permite que cada estudio (tenant) agregue sus propios tipos de retención/percepción
 * además de los estándar de AFIP. `tenant_id` NULL = estándar global (sembrado, read-only);
 * con valor = propio del estudio (editable). Idempotente.
 */
return new class {
    public function up(\PDO $pdo): void
    {
        if (!$this->columnExists($pdo, 'tipos_retencion', 'tenant_id')) {
            $pdo->exec('ALTER TABLE tipos_retencion ADD COLUMN tenant_id CHAR(36) DEFAULT NULL');
            $pdo->exec('ALTER TABLE tipos_retencion ADD INDEX idx_tiporet_tenant (tenant_id)');
        }
    }

    public function down(\PDO $pdo): void
    {
        if ($this->columnExists($pdo, 'tipos_retencion', 'tenant_id')) {
            $pdo->exec('ALTER TABLE tipos_retencion DROP COLUMN tenant_id');
        }
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
