<?php

/**
 * Módulo Sueldos — snapshot del legajo al liquidar (análogo a PERSONAL_LIQUIDACIONES
 * del legacy). Congela los datos del empleado y las variables usadas (básico,
 * antigüedad) al momento de liquidar, para que el recibo histórico sea reproducible
 * aunque luego se edite el legajo.
 */
return new class {
    public function up(\PDO $pdo): void
    {
        $cs = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

        $pdo->exec("CREATE TABLE IF NOT EXISTS liquidacion_empleados (
            id               INT AUTO_INCREMENT PRIMARY KEY,
            liquidacion_id   INT          NOT NULL,
            empleado_id      INT          NOT NULL,
            legajo           INT          DEFAULT NULL,
            nombres          VARCHAR(100) DEFAULT NULL,
            primer_apellido  VARCHAR(60)  DEFAULT NULL,
            segundo_apellido VARCHAR(60)  DEFAULT NULL,
            cuil             VARCHAR(13)  DEFAULT NULL,
            fecha_ingreso    DATE         DEFAULT NULL,
            basico           DECIMAL(18,2) NOT NULL DEFAULT 0,
            antiguedad       INT          NOT NULL DEFAULT 0,
            CONSTRAINT fk_liqemp_liq      FOREIGN KEY (liquidacion_id) REFERENCES liquidaciones(id) ON DELETE CASCADE,
            CONSTRAINT fk_liqemp_empleado FOREIGN KEY (empleado_id)    REFERENCES empleados(id)     ON DELETE CASCADE,
            UNIQUE KEY uq_liqemp (liquidacion_id, empleado_id)
        ) {$cs}");
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS liquidacion_empleados');
    }
};
