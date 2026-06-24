<?php

/**
 * CRM del contribuyente (de sistemaCuarto/Synergys): cierra la unificación de la
 * entidad canónica `empresas` (= Persona del legacy) agregando campos CRM, y suma
 * `socios` (integrantes/socios del contribuyente).
 *
 * Idempotente ante columnas ya existentes (chequea information_schema).
 */
return new class {
    public function up(\PDO $pdo): void
    {
        foreach ([
            'email'        => 'VARCHAR(100) DEFAULT NULL',
            'contacto'     => 'VARCHAR(150) DEFAULT NULL',
            'tipo_persona' => "VARCHAR(20) DEFAULT NULL COMMENT 'fisica / juridica'",
            'inscripcion'  => 'VARCHAR(50) DEFAULT NULL',
            'contabilidad' => 'VARCHAR(50) DEFAULT NULL',
        ] as $col => $def) {
            if (!$this->columnExists($pdo, 'empresas', $col)) {
                $pdo->exec("ALTER TABLE empresas ADD COLUMN {$col} {$def}");
            }
        }

        $pdo->exec("CREATE TABLE IF NOT EXISTS socios (
            id                   INT AUTO_INCREMENT PRIMARY KEY,
            empresa_id           INT          NOT NULL,
            nombre               VARCHAR(150) NOT NULL,
            tipo_persona         VARCHAR(20)  DEFAULT NULL,
            cuit                 VARCHAR(13)  DEFAULT NULL,
            contacto             VARCHAR(150) DEFAULT NULL,
            telefono             VARCHAR(50)  DEFAULT NULL,
            email                VARCHAR(100) DEFAULT NULL,
            categoria            VARCHAR(80)  DEFAULT NULL,
            cargo                VARCHAR(80)  DEFAULT NULL,
            fecha_ingreso        DATE         DEFAULT NULL,
            participacion        DECIMAL(6,2) DEFAULT NULL COMMENT 'porcentaje',
            cliente              CHAR(1)      NOT NULL DEFAULT 'N',
            colaborador          VARCHAR(120) DEFAULT NULL,
            colaborador_telefono VARCHAR(50)  DEFAULT NULL,
            colaborador_email    VARCHAR(100) DEFAULT NULL,
            observacion          TEXT         DEFAULT NULL,
            is_activo            CHAR(1)      NOT NULL DEFAULT 'S',
            CONSTRAINT fk_socio_empresa FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE,
            INDEX idx_socio_empresa (empresa_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS socios');

        foreach (['email', 'contacto', 'tipo_persona', 'inscripcion', 'contabilidad'] as $col) {
            if ($this->columnExists($pdo, 'empresas', $col)) {
                $pdo->exec("ALTER TABLE empresas DROP COLUMN {$col}");
            }
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
