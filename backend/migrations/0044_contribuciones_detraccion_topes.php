<?php

/**
 * Sueldos — Contribuciones patronales: detracción (Dto 99/2019) y topes mín/máx (B6).
 *
 * Spec del manual "Contribuciones Patronales v5.80" (Visual Sueldos), normativo:
 *  - Detracción: monto fijo que se resta de la base imponible de las contribuciones con
 *    destino a SIPA (jubilación/PAMI/FNE). Se configura a nivel de EMPRESA (importe que se
 *    actualiza periódicamente) y por contribución se marca si aplica.
 *  - Topes: algunas contribuciones acotan la base a un mínimo/máximo. Por contribución se
 *    marca si aplican y con qué valores.
 *
 * Los VALORES (monto de detracción, topes) son parámetros que carga el estudio por período,
 * como el porcentaje y el importe fijo; acá solo se agrega el modelo para soportarlos.
 */

return new class {
    public function up(\PDO $pdo): void
    {
        $pdo->exec("ALTER TABLE contribuciones
            ADD COLUMN aplica_detraccion CHAR(1)       NOT NULL DEFAULT 'N' AFTER incluye_norem,
            ADD COLUMN aplica_topes      CHAR(1)       NOT NULL DEFAULT 'N' AFTER aplica_detraccion,
            ADD COLUMN tope_min          DECIMAL(18,2) DEFAULT NULL         AFTER aplica_topes,
            ADD COLUMN tope_max          DECIMAL(18,2) DEFAULT NULL         AFTER tope_min");

        // Monto de la detracción vigente (Dto 99/2019), a nivel de empresa.
        $pdo->exec('ALTER TABLE sueldos_empresa_config
            ADD COLUMN detraccion_monto DECIMAL(18,2) DEFAULT NULL');
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('ALTER TABLE contribuciones
            DROP COLUMN aplica_detraccion,
            DROP COLUMN aplica_topes,
            DROP COLUMN tope_min,
            DROP COLUMN tope_max');
        $pdo->exec('ALTER TABLE sueldos_empresa_config DROP COLUMN detraccion_monto');
    }
};
