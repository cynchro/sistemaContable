<?php

/**
 * Módulo Sueldos — Fase 3: liquidaciones y recibos.
 * Ingeniería inversa de LIQUIDACIONES / RECIBOS / NOVEDADES del legacy.
 *
 * - `liquidaciones`: corrida de liquidación por empresa y período (periodo_liquidado),
 *   con rango de fechas y tipo (mensual, SAC, etc.).
 * - `recibos`: resultado por empleado y concepto (item, importe calculado por el
 *   FormulaEvaluator, cantidad de la novedad, tipo del concepto).
 *
 * La lógica de liquidación vivía en el cliente Delphi (no en la DB); se reconstruye
 * en LiquidacionCalculator desde el modelo + la semántica de fórmulas. La carga de
 * novedades (CANTIDAD/IMPORTE por concepto) se modela en la capa de servicio.
 *
 * Diferido: snapshot completo del legajo/conceptos (PERSONAL_LIQUIDACIONES /
 * CONCEPTOS_LIQUIDACION del legacy) — por ahora el recibo referencia empleado/concepto.
 */
return new class {
    public function up(\PDO $pdo): void
    {
        $cs = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

        $pdo->exec("CREATE TABLE IF NOT EXISTS liquidaciones (
            id                INT AUTO_INCREMENT PRIMARY KEY,
            empresa_id        INT          NOT NULL,
            periodo_liquidado VARCHAR(20)  NOT NULL COMMENT 'p. ej. 2026-01',
            descripcion       VARCHAR(150) DEFAULT NULL,
            tipo              SMALLINT     NOT NULL DEFAULT 1 COMMENT '1 mensual, 2 SAC, ... (según legacy)',
            fecha_desde       DATE         DEFAULT NULL,
            fecha_hasta       DATE         DEFAULT NULL,
            fecha_pago        DATE         DEFAULT NULL,
            activa            CHAR(1)      NOT NULL DEFAULT 'S',
            bloqueada         CHAR(1)      NOT NULL DEFAULT 'N',
            CONSTRAINT fk_liq_empresa FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE,
            INDEX idx_liq_empresa (empresa_id)
        ) {$cs}");

        $pdo->exec("CREATE TABLE IF NOT EXISTS recibos (
            id             INT AUTO_INCREMENT PRIMARY KEY,
            liquidacion_id INT          NOT NULL,
            empleado_id    INT          NOT NULL,
            item           INT          NOT NULL DEFAULT 0,
            concepto_id    INT          DEFAULT NULL,
            codigo         VARCHAR(10)  DEFAULT NULL,
            descripcion    VARCHAR(150) DEFAULT NULL,
            formula        VARCHAR(500) DEFAULT NULL,
            cantidad       DECIMAL(12,2) NOT NULL DEFAULT 0,
            importe        DECIMAL(18,2) NOT NULL DEFAULT 0,
            tipo           SMALLINT     NOT NULL DEFAULT 1,
            CONSTRAINT fk_recibo_liq      FOREIGN KEY (liquidacion_id) REFERENCES liquidaciones(id) ON DELETE CASCADE,
            CONSTRAINT fk_recibo_empleado FOREIGN KEY (empleado_id)    REFERENCES empleados(id)     ON DELETE CASCADE,
            CONSTRAINT fk_recibo_concepto FOREIGN KEY (concepto_id)    REFERENCES conceptos(id),
            INDEX idx_recibo_liq (liquidacion_id),
            INDEX idx_recibo_liq_emp (liquidacion_id, empleado_id)
        ) {$cs}");
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS recibos');
        $pdo->exec('DROP TABLE IF EXISTS liquidaciones');
    }
};
