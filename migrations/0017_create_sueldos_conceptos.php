<?php

/**
 * Módulo Sueldos — Fase 2: conceptos (haberes/descuentos con fórmula).
 * Ingeniería inversa de CONCEPTOS del legacy. Por empresa. La `formula` se evalúa
 * con FormulaEvaluator en la liquidación. `cuenta_id` imputa al plan de cuentas.
 *
 * Se difieren flags específicos de SS/LSD/SICOSS por concepto (ver doc).
 */
return new class {
    public function up(\PDO $pdo): void
    {
        $cs = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

        $pdo->exec("CREATE TABLE IF NOT EXISTS conceptos (
            id                   INT AUTO_INCREMENT PRIMARY KEY,
            empresa_id           INT          NOT NULL,
            codigo               VARCHAR(10)  NOT NULL,
            descripcion          VARCHAR(150) NOT NULL,
            formula              VARCHAR(500) DEFAULT NULL,
            tipo                 SMALLINT     NOT NULL DEFAULT 1 COMMENT '1 remun, 2 no remun, 3 descuento (según legacy)',
            titulo_impresion     VARCHAR(150) DEFAULT NULL,
            imprimir             CHAR(1)      NOT NULL DEFAULT 'S',
            cuenta_id            INT          DEFAULT NULL,
            condicion_ganancias  CHAR(1)      NOT NULL DEFAULT 'N',
            retencion_ganancias  CHAR(1)      NOT NULL DEFAULT 'N',
            exporta_a_afip       VARCHAR(1)   NOT NULL DEFAULT 'N',
            orden                INT          DEFAULT NULL,
            CONSTRAINT fk_concepto_empresa FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE,
            CONSTRAINT fk_concepto_cuenta  FOREIGN KEY (cuenta_id)  REFERENCES cuentas(id),
            INDEX idx_concepto_empresa (empresa_id)
        ) {$cs}");
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS conceptos');
    }
};
