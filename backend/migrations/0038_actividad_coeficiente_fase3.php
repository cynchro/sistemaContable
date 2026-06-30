<?php

/**
 * Estrategia "porcentajes fijos" de asignación de actividad (A15, Fase 3).
 * Ver docs/ingenieria-inversa/dj-iva-simple-actividad.md (v2).
 *
 * A diferencia de las otras estrategias, NO se resuelve por comprobante: el neto del
 * período se reparte entre las actividades según un **coeficiente** (0 a 1) por actividad,
 * que en conjunto suman 1. Caso Acevedo (un solo punto de venta que vende de todo).
 *
 * Activación: si la empresa tiene filas en `actividad_coeficiente`, el exporter de la DJ
 * usa este modo (reparte el total) en lugar de resolver por comprobante.
 */
return new class {
    public function up(\PDO $pdo): void
    {
        $charset = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

        $pdo->exec("CREATE TABLE IF NOT EXISTS actividad_coeficiente (
            id           INT AUTO_INCREMENT PRIMARY KEY,
            empresa_id   INT          NOT NULL,
            actividad_id INT          NOT NULL,
            coeficiente  DECIMAL(9,8) NOT NULL DEFAULT 0 COMMENT 'participación 0..1 (suman 1)',
            CONSTRAINT fk_acoef_empresa   FOREIGN KEY (empresa_id)   REFERENCES empresas(id)            ON DELETE CASCADE,
            CONSTRAINT fk_acoef_actividad FOREIGN KEY (actividad_id) REFERENCES empresa_actividades(id) ON DELETE CASCADE,
            UNIQUE KEY uq_acoef (empresa_id, actividad_id),
            INDEX idx_acoef_empresa (empresa_id)
        ) {$charset}");
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS actividad_coeficiente');
    }
};
