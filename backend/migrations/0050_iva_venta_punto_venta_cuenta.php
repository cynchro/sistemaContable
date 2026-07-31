<?php

/**
 * Motor de clasificación de ventas por punto de venta + tipo de comprobante (documento "Satélite
 * Visual IVA" §4, ver documentacion/analisis-satelite-visual-iva.md §7.7 paso 5): a diferencia de
 * compras (Parte 1, migración 0049), acá no hay noción de "proveedor" — el punto de venta es del
 * propio contribuyente, así que la resolución es {empresa, punto_venta[, tipo_comprobante]} →
 * cuenta, sin capa de sujeto.
 *
 * Dos tablas (mismo patrón que `actividad_punto_venta`/`actividad_alicuota`, migraciones
 * 0036/0037 — evita el problema de unicidad con NULL de MySQL: UNIQUE no bloquea dos filas con la
 * misma clave si una columna es NULL, así que "regla general" y "excepción por tipo" van
 * separadas en vez de una sola tabla con `tipo_comprobante_id` nullable):
 *  - `iva_venta_punto_venta`: regla general de ese punto de venta.
 *  - `iva_venta_punto_venta_tipo`: excepción cuando el tipo de comprobante en ese PV pisa la
 *    regla general (caso del documento: una NC del mismo PV se imputa distinto que una Factura).
 *
 * Resolución (VentaClasificacionRepository::resolverCuenta): tipo específico → regla general del
 * PV → sin regla (null).
 */
return new class {
    public function up(\PDO $pdo): void
    {
        $charset = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

        $pdo->exec("CREATE TABLE IF NOT EXISTS iva_venta_punto_venta (
            id          INT AUTO_INCREMENT PRIMARY KEY,
            empresa_id  INT        NOT NULL,
            punto_venta VARCHAR(5) NOT NULL,
            cuenta_id   INT        NOT NULL,
            CONSTRAINT fk_ivpv_empresa FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE,
            CONSTRAINT fk_ivpv_cuenta  FOREIGN KEY (cuenta_id)  REFERENCES cuentas(id)  ON DELETE CASCADE,
            UNIQUE KEY uq_ivpv (empresa_id, punto_venta),
            INDEX idx_ivpv_empresa (empresa_id)
        ) {$charset}");

        $pdo->exec("CREATE TABLE IF NOT EXISTS iva_venta_punto_venta_tipo (
            id                   INT AUTO_INCREMENT PRIMARY KEY,
            empresa_id           INT        NOT NULL,
            punto_venta          VARCHAR(5) NOT NULL,
            tipo_comprobante_id  INT        NOT NULL,
            cuenta_id            INT        NOT NULL,
            CONSTRAINT fk_ivpvt_empresa FOREIGN KEY (empresa_id)          REFERENCES empresas(id)         ON DELETE CASCADE,
            CONSTRAINT fk_ivpvt_tipo    FOREIGN KEY (tipo_comprobante_id) REFERENCES tipos_comprobante(id) ON DELETE CASCADE,
            CONSTRAINT fk_ivpvt_cuenta  FOREIGN KEY (cuenta_id)           REFERENCES cuentas(id)           ON DELETE CASCADE,
            UNIQUE KEY uq_ivpvt (empresa_id, punto_venta, tipo_comprobante_id),
            INDEX idx_ivpvt_empresa (empresa_id)
        ) {$charset}");
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS iva_venta_punto_venta_tipo');
        $pdo->exec('DROP TABLE IF EXISTS iva_venta_punto_venta');
    }
};
