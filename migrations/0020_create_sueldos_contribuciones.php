<?php

/**
 * Módulo Sueldos — Fase 4: contribuciones patronales.
 * Ingeniería inversa de CONTRIBUCIONES_PATRONALES / CONTRIBUCIONES_RECIBO_DETALLE.
 *
 * - `contribuciones`: definiciones por empresa (porcentaje sobre la base + importe fijo).
 * - `contribuciones_liquidadas`: resultado por liquidación+empleado (base, %, fijo, total).
 *
 * La base imponible es el total remunerativo del recibo (+ no remunerativo si la
 * contribución lo incluye). Se DIFIEREN las nuances de detracción y topes del legacy
 * (schema incompleto en esos campos; ver docs/ingenieria-inversa/sueldos.md).
 */
return new class {
    public function up(\PDO $pdo): void
    {
        $cs = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

        $pdo->exec("CREATE TABLE IF NOT EXISTS contribuciones (
            id            INT AUTO_INCREMENT PRIMARY KEY,
            empresa_id    INT          NOT NULL,
            descripcion   VARCHAR(100) NOT NULL,
            porcentaje    DECIMAL(7,3) NOT NULL DEFAULT 0,
            importe_fijo  DECIMAL(18,2) NOT NULL DEFAULT 0,
            incluye_norem CHAR(1)      NOT NULL DEFAULT 'N',
            cuenta_id     INT          DEFAULT NULL,
            orden         INT          DEFAULT NULL,
            CONSTRAINT fk_contrib_empresa FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE,
            CONSTRAINT fk_contrib_cuenta  FOREIGN KEY (cuenta_id)  REFERENCES cuentas(id),
            INDEX idx_contrib_empresa (empresa_id)
        ) {$cs}");

        $pdo->exec("CREATE TABLE IF NOT EXISTS contribuciones_liquidadas (
            id             INT AUTO_INCREMENT PRIMARY KEY,
            liquidacion_id INT          NOT NULL,
            empleado_id    INT          NOT NULL,
            contribucion_id INT         DEFAULT NULL,
            descripcion    VARCHAR(100) DEFAULT NULL,
            base_imponible DECIMAL(18,2) NOT NULL DEFAULT 0,
            porcentaje     DECIMAL(7,3) NOT NULL DEFAULT 0,
            importe_fijo   DECIMAL(18,2) NOT NULL DEFAULT 0,
            importe_total  DECIMAL(18,2) NOT NULL DEFAULT 0,
            CONSTRAINT fk_contliq_liq      FOREIGN KEY (liquidacion_id)  REFERENCES liquidaciones(id) ON DELETE CASCADE,
            CONSTRAINT fk_contliq_empleado FOREIGN KEY (empleado_id)     REFERENCES empleados(id)     ON DELETE CASCADE,
            CONSTRAINT fk_contliq_contrib  FOREIGN KEY (contribucion_id) REFERENCES contribuciones(id),
            INDEX idx_contliq_liq_emp (liquidacion_id, empleado_id)
        ) {$cs}");
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS contribuciones_liquidadas');
        $pdo->exec('DROP TABLE IF EXISTS contribuciones');
    }
};
