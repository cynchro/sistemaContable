<?php

/**
 * Módulo Sueldos — Fase 3 (2/2): novedades de liquidación.
 * Ingeniería inversa de NOVEDADES del legacy: por liquidación + empleado + concepto,
 * la CANTIDAD e IMPORTE que alimentan las variables CAN / IMP de la fórmula.
 * Sólo se liquidan los conceptos con novedad cargada.
 */
return new class {
    public function up(\PDO $pdo): void
    {
        $cs = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

        $pdo->exec("CREATE TABLE IF NOT EXISTS novedades (
            id             INT AUTO_INCREMENT PRIMARY KEY,
            liquidacion_id INT           NOT NULL,
            empleado_id    INT           NOT NULL,
            concepto_id    INT           NOT NULL,
            cantidad       DECIMAL(12,2) NOT NULL DEFAULT 0,
            importe        DECIMAL(18,2) NOT NULL DEFAULT 0,
            CONSTRAINT fk_nov_liq      FOREIGN KEY (liquidacion_id) REFERENCES liquidaciones(id) ON DELETE CASCADE,
            CONSTRAINT fk_nov_empleado FOREIGN KEY (empleado_id)    REFERENCES empleados(id)     ON DELETE CASCADE,
            CONSTRAINT fk_nov_concepto FOREIGN KEY (concepto_id)    REFERENCES conceptos(id)     ON DELETE CASCADE,
            UNIQUE KEY uq_nov (liquidacion_id, empleado_id, concepto_id),
            INDEX idx_nov_liq_emp (liquidacion_id, empleado_id)
        ) {$cs}");
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS novedades');
    }
};
