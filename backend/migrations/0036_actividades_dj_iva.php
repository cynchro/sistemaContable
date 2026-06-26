<?php

/**
 * Apertura por actividad de la DJ IVA Simple (A15, respuestas del contador 2026-06-26).
 * Ver docs/ingenieria-inversa/dj-iva-simple-actividad.md (sección v2).
 *
 * Fase 1:
 *  - `empresa_actividades`: las actividades (código NAES + descripción) que tiene cada
 *    empresa (el contador carga 2-3 por empresa; no se siembra el nomenclador completo).
 *  - `actividad_punto_venta`: mapa {empresa, punto_venta → actividad} (estrategia más común).
 *  - ventas: `actividad_id` (override manual) + `es_bien_uso` (el cliente informa).
 *  - compras: `actividad_id` + `concepto_dj` (1 bienes / 2 locaciones / 3 servicios /
 *    4 inversiones de bienes de uso) para el archivo de Crédito Fiscal.
 */
return new class {
    public function up(\PDO $pdo): void
    {
        $charset = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

        $pdo->exec("CREATE TABLE IF NOT EXISTS empresa_actividades (
            id          INT AUTO_INCREMENT PRIMARY KEY,
            empresa_id  INT          NOT NULL,
            codigo      VARCHAR(10)  NOT NULL COMMENT 'código NAES (6 díg.)',
            descripcion VARCHAR(255) DEFAULT NULL,
            created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_emp_act_empresa FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE,
            UNIQUE KEY uq_emp_actividad (empresa_id, codigo),
            INDEX idx_emp_act_empresa (empresa_id)
        ) {$charset}");

        $pdo->exec("CREATE TABLE IF NOT EXISTS actividad_punto_venta (
            id           INT AUTO_INCREMENT PRIMARY KEY,
            empresa_id   INT         NOT NULL,
            punto_venta  VARCHAR(5)  NOT NULL,
            actividad_id INT         NOT NULL,
            CONSTRAINT fk_apv_empresa   FOREIGN KEY (empresa_id)   REFERENCES empresas(id)            ON DELETE CASCADE,
            CONSTRAINT fk_apv_actividad FOREIGN KEY (actividad_id) REFERENCES empresa_actividades(id) ON DELETE CASCADE,
            UNIQUE KEY uq_apv (empresa_id, punto_venta),
            INDEX idx_apv_empresa (empresa_id)
        ) {$charset}");

        if (!$this->columnExists($pdo, 'ventas', 'actividad_id')) {
            $pdo->exec(
                "ALTER TABLE ventas
                   ADD COLUMN actividad_id INT       DEFAULT NULL,
                   ADD COLUMN es_bien_uso  CHAR(1)   NOT NULL DEFAULT 'N',
                   ADD CONSTRAINT fk_ventas_actividad FOREIGN KEY (actividad_id)
                       REFERENCES empresa_actividades(id) ON DELETE SET NULL"
            );
        }

        if (!$this->columnExists($pdo, 'compras', 'actividad_id')) {
            $pdo->exec(
                "ALTER TABLE compras
                   ADD COLUMN actividad_id INT      DEFAULT NULL,
                   ADD COLUMN concepto_dj  SMALLINT DEFAULT NULL COMMENT '1 bienes/2 locaciones/3 servicios/4 inv. BU',
                   ADD CONSTRAINT fk_compras_actividad FOREIGN KEY (actividad_id)
                       REFERENCES empresa_actividades(id) ON DELETE SET NULL"
            );
        }
    }

    public function down(\PDO $pdo): void
    {
        if ($this->columnExists($pdo, 'compras', 'actividad_id')) {
            $pdo->exec('ALTER TABLE compras DROP FOREIGN KEY fk_compras_actividad,
                        DROP COLUMN actividad_id, DROP COLUMN concepto_dj');
        }
        if ($this->columnExists($pdo, 'ventas', 'actividad_id')) {
            $pdo->exec('ALTER TABLE ventas DROP FOREIGN KEY fk_ventas_actividad,
                        DROP COLUMN actividad_id, DROP COLUMN es_bien_uso');
        }
        $pdo->exec('DROP TABLE IF EXISTS actividad_punto_venta');
        $pdo->exec('DROP TABLE IF EXISTS empresa_actividades');
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
