<?php

/**
 * Imputación contable del proveedor/cliente del Padrón Único (documento "Satélite Visual IVA",
 * satelite/documento-1 (1).pdf §5): cierra la "Configuración por Contribuyente" del informe
 * 21/07/2026 (documentacion/Informe_Definitivo_Padron_Proveedores.pdf) que había quedado sin
 * conectar en la migración 0048 (cuenta_id/rubro_id de las tablas viejas se dejaron afuera
 * a propósito). Ver documentacion/analisis-satelite-visual-iva.md §7.1 para el diseño completo.
 *
 * `cuentas` es un catálogo POR EMPRESA, no por tenant (migración 0012) — por eso la imputación
 * NO puede vivir en `iva_sujetos` (padrón, a nivel tenant, compartido entre empresas con planes
 * de cuentas distintos): vive en `iva_sujeto_empresas`, que ya es exactamente la fila
 * {empresa, sujeto, rol} que necesita la "Configuración por Contribuyente" del informe.
 *
 *  - `iva_sujeto_empresas.cuenta_id`: cuenta por defecto para ese sujeto en esa empresa.
 *  - `iva_sujeto_punto_venta`: excepción por punto de venta del proveedor (documento §5.4, caso
 *    MUCHAY SRL — un mismo proveedor factura conceptos distintos según el PV que usa), también
 *    scopeada por empresa (misma razón: la cuenta destino pertenece al plan de esa empresa).
 *
 * Resolución (ImputacionContableRepository::resolverCuenta): punto de venta específico → cuenta
 * por defecto del sujeto en la empresa → sin regla (null; el caller decide, ej. bandeja de
 * pendientes — a implementar en un paso posterior).
 */
return new class {
    public function up(\PDO $pdo): void
    {
        $charset = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

        $pdo->exec(
            'ALTER TABLE iva_sujeto_empresas
                ADD COLUMN cuenta_id INT DEFAULT NULL AFTER rol,
                ADD CONSTRAINT fk_sujemp_cuenta FOREIGN KEY (cuenta_id)
                    REFERENCES cuentas(id) ON DELETE SET NULL'
        );

        $pdo->exec("CREATE TABLE IF NOT EXISTS iva_sujeto_punto_venta (
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

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS iva_sujeto_punto_venta');
        $pdo->exec(
            'ALTER TABLE iva_sujeto_empresas
                DROP FOREIGN KEY fk_sujemp_cuenta,
                DROP COLUMN cuenta_id'
        );
    }
};
