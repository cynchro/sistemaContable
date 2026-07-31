<?php

/**
 * Capa de "concepto" global para la imputación contable del Padrón Único (documento "Satélite
 * Visual IVA", satelite/documento-1 (1).pdf §5.2/§5.4). Reemplaza el modelo de la migración 0049
 * (cuenta_id directo, siempre por-empresa) porque `cuentas` es un catálogo por-empresa: dos
 * empresas no comparten fila de cuenta aunque tengan "la misma" cuenta contable, así que una
 * regla no podía ser realmente global. Se reemplaza limpio (sin migrar datos existentes: no hay
 * datos reales de producción en este frente todavía, solo filas de prueba — Parte 4 del
 * documento sigue bloqueada).
 *
 * Ver documentacion/analisis-satelite-visual-iva.md para el diseño completo. Cadena de
 * resolución (ImputacionContableRepository::resolverCuenta), de más a menos específica:
 *
 *  1. iva_sujeto_punto_venta_empresa (empresa+sujeto+pv)  → concepto_id  [excepción por PV+empresa]
 *  2. iva_sujeto_punto_venta         (sujeto+pv, global)  → concepto_id  [regla de PV del proveedor]
 *  3. iva_sujeto_empresas.concepto_id (empresa+sujeto)    → concepto_id  [excepción del default por empresa]
 *  4. iva_sujetos.concepto_default_id (sujeto, global)    → concepto_id  [default del proveedor]
 *  5. empresa_concepto_cuenta (empresa+concepto)          → cuenta_id    [mapeo a la cuenta de ESA empresa]
 *
 * Si el paso 5 no tiene mapeo cargado en esa empresa, se resuelve null igual que "sin regla" hoy
 * (el comprobante queda sin imputar, no se inventa una cuenta).
 */
return new class {
    public function up(\PDO $pdo): void
    {
        $charset = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

        $pdo->exec("CREATE TABLE IF NOT EXISTS iva_conceptos (
            id        INT AUTO_INCREMENT PRIMARY KEY,
            tenant_id CHAR(36)     NOT NULL,
            nombre    VARCHAR(120) NOT NULL,
            UNIQUE KEY uq_concepto_tenant_nombre (tenant_id, nombre)
        ) {$charset}");

        $pdo->exec("CREATE TABLE IF NOT EXISTS empresa_concepto_cuenta (
            id          INT AUTO_INCREMENT PRIMARY KEY,
            empresa_id  INT NOT NULL,
            concepto_id INT NOT NULL,
            cuenta_id   INT NOT NULL,
            CONSTRAINT fk_ecc_empresa  FOREIGN KEY (empresa_id)  REFERENCES empresas(id)      ON DELETE CASCADE,
            CONSTRAINT fk_ecc_concepto FOREIGN KEY (concepto_id) REFERENCES iva_conceptos(id) ON DELETE CASCADE,
            CONSTRAINT fk_ecc_cuenta   FOREIGN KEY (cuenta_id)   REFERENCES cuentas(id)       ON DELETE CASCADE,
            UNIQUE KEY uq_ecc (empresa_id, concepto_id)
        ) {$charset}");

        $pdo->exec(
            'ALTER TABLE iva_sujetos
                ADD COLUMN concepto_default_id INT DEFAULT NULL AFTER cais,
                ADD CONSTRAINT fk_sujeto_concepto_default FOREIGN KEY (concepto_default_id)
                    REFERENCES iva_conceptos(id) ON DELETE SET NULL'
        );

        $pdo->exec(
            'ALTER TABLE iva_sujeto_empresas
                DROP FOREIGN KEY fk_sujemp_cuenta,
                DROP COLUMN cuenta_id,
                ADD COLUMN concepto_id INT DEFAULT NULL AFTER rol,
                ADD CONSTRAINT fk_sujemp_concepto FOREIGN KEY (concepto_id)
                    REFERENCES iva_conceptos(id) ON DELETE SET NULL'
        );

        // Regla GLOBAL de punto de venta del proveedor (ya no por empresa, ver §5.4).
        $pdo->exec('DROP TABLE IF EXISTS iva_sujeto_punto_venta');
        $pdo->exec("CREATE TABLE iva_sujeto_punto_venta (
            id          INT AUTO_INCREMENT PRIMARY KEY,
            sujeto_id   INT        NOT NULL,
            punto_venta VARCHAR(5) NOT NULL,
            concepto_id INT        NOT NULL,
            CONSTRAINT fk_supv_sujeto   FOREIGN KEY (sujeto_id)   REFERENCES iva_sujetos(id)    ON DELETE CASCADE,
            CONSTRAINT fk_supv_concepto FOREIGN KEY (concepto_id) REFERENCES iva_conceptos(id)  ON DELETE CASCADE,
            UNIQUE KEY uq_supv (sujeto_id, punto_venta)
        ) {$charset}");

        // Excepción de esa regla para UNA empresa puntual (§5.4, "excepción por contribuyente").
        $pdo->exec("CREATE TABLE iva_sujeto_punto_venta_empresa (
            id          INT AUTO_INCREMENT PRIMARY KEY,
            empresa_id  INT        NOT NULL,
            sujeto_id   INT        NOT NULL,
            punto_venta VARCHAR(5) NOT NULL,
            concepto_id INT        NOT NULL,
            CONSTRAINT fk_supve_empresa  FOREIGN KEY (empresa_id)  REFERENCES empresas(id)      ON DELETE CASCADE,
            CONSTRAINT fk_supve_sujeto   FOREIGN KEY (sujeto_id)   REFERENCES iva_sujetos(id)   ON DELETE CASCADE,
            CONSTRAINT fk_supve_concepto FOREIGN KEY (concepto_id) REFERENCES iva_conceptos(id) ON DELETE CASCADE,
            UNIQUE KEY uq_supve (empresa_id, sujeto_id, punto_venta),
            INDEX idx_supve_empresa (empresa_id, sujeto_id)
        ) {$charset}");
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS iva_sujeto_punto_venta_empresa');
        $pdo->exec('DROP TABLE IF EXISTS iva_sujeto_punto_venta');

        $pdo->exec(
            'ALTER TABLE iva_sujeto_empresas
                DROP FOREIGN KEY fk_sujemp_concepto,
                DROP COLUMN concepto_id,
                ADD COLUMN cuenta_id INT DEFAULT NULL AFTER rol,
                ADD CONSTRAINT fk_sujemp_cuenta FOREIGN KEY (cuenta_id)
                    REFERENCES cuentas(id) ON DELETE SET NULL'
        );

        $pdo->exec(
            'ALTER TABLE iva_sujetos
                DROP FOREIGN KEY fk_sujeto_concepto_default,
                DROP COLUMN concepto_default_id'
        );

        $pdo->exec('DROP TABLE IF EXISTS empresa_concepto_cuenta');
        $pdo->exec('DROP TABLE IF EXISTS iva_conceptos');

        $charset = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
        $pdo->exec("CREATE TABLE iva_sujeto_punto_venta (
            id          INT AUTO_INCREMENT PRIMARY KEY,
            empresa_id  INT        NOT NULL,
            sujeto_id   INT        NOT NULL,
            punto_venta VARCHAR(5) NOT NULL,
            cuenta_id   INT        NOT NULL,
            CONSTRAINT fk_supv_empresa FOREIGN KEY (empresa_id) REFERENCES empresas(id)    ON DELETE CASCADE,
            CONSTRAINT fk_supv_sujeto  FOREIGN KEY (sujeto_id)  REFERENCES iva_sujetos(id) ON DELETE CASCADE,
            CONSTRAINT fk_supv_cuenta  FOREIGN KEY (cuenta_id)  REFERENCES cuentas(id)     ON DELETE CASCADE,
            UNIQUE KEY uq_supv (empresa_id, sujeto_id, punto_venta),
            INDEX idx_supv_empresa (empresa_id, sujeto_id)
        ) {$charset}");
    }
};
