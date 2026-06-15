<?php

/**
 * Comprobantes de compra del módulo Iva (ingeniería inversa de COMPRAS /
 * COMPRAS_DISCRIMINACION / RETENC_COMPRAS del legacy Visual IVA).
 *
 * Simétrico a ventas (ver migración 0014), lado proveedor. Diferencias del legacy:
 *  - la discriminación lleva `cf_computable` (crédito fiscal computable) en lugar
 *    de reintegro_t/concepto;
 *  - el comprobante referencia tipo_operacion_compra (no tipo_documento ni actividad).
 * Se difieren: factura electrónica e imputación contable (igual que ventas).
 */
return new class {
    public function up(\PDO $pdo): void
    {
        $charset = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

        $pdo->exec("CREATE TABLE IF NOT EXISTS compras (
            id                       INT AUTO_INCREMENT PRIMARY KEY,
            periodo_id               INT          NOT NULL,
            tipo_comprobante_id      INT          DEFAULT NULL,
            condicion_iva_id         INT          DEFAULT NULL,
            provincia_id             INT          DEFAULT NULL,
            rubro_id                 INT          DEFAULT NULL,
            tipo_operacion_compra_id INT          DEFAULT NULL,
            tipo_moneda_id           INT          DEFAULT NULL,
            proveedor_id             INT          DEFAULT NULL,
            fecha                    DATE         DEFAULT NULL,
            proveedor_nombre         VARCHAR(100) DEFAULT NULL,
            cuit                     VARCHAR(13)  DEFAULT NULL,
            letra                    VARCHAR(1)   DEFAULT NULL,
            punto_venta              VARCHAR(5)   DEFAULT NULL,
            numero                   VARCHAR(8)   DEFAULT NULL,
            neto_no_grav             DECIMAL(18,2) NOT NULL DEFAULT 0,
            exento                   DECIMAL(18,2) NOT NULL DEFAULT 0,
            imp_interno              DECIMAL(18,2) NOT NULL DEFAULT 0,
            total                    DECIMAL(18,2) NOT NULL DEFAULT 0,
            tipo_cambio              DECIMAL(18,4) DEFAULT NULL,
            concepto                 SMALLINT     NOT NULL DEFAULT 1,
            cai                      VARCHAR(15)  DEFAULT NULL,
            fecha_cai                DATE         DEFAULT NULL,
            comprobante              VARCHAR(255) AS (CONCAT(COALESCE(letra,''), COALESCE(punto_venta,''), COALESCE(numero,''))) STORED,
            CONSTRAINT fk_compras_periodo   FOREIGN KEY (periodo_id)               REFERENCES periodos(id)                ON DELETE CASCADE,
            CONSTRAINT fk_compras_tc        FOREIGN KEY (tipo_comprobante_id)      REFERENCES tipos_comprobante(id),
            CONSTRAINT fk_compras_condicion FOREIGN KEY (condicion_iva_id)         REFERENCES condiciones_iva(id),
            CONSTRAINT fk_compras_provincia FOREIGN KEY (provincia_id)             REFERENCES provincias(id),
            CONSTRAINT fk_compras_rubro     FOREIGN KEY (rubro_id)                 REFERENCES rubros(id),
            CONSTRAINT fk_compras_tipoop    FOREIGN KEY (tipo_operacion_compra_id) REFERENCES tipos_operacion_compra(id),
            CONSTRAINT fk_compras_moneda    FOREIGN KEY (tipo_moneda_id)           REFERENCES tipos_moneda(id),
            CONSTRAINT fk_compras_proveedor FOREIGN KEY (proveedor_id)             REFERENCES iva_proveedores(id),
            INDEX idx_compras_periodo (periodo_id)
        ) {$charset}");

        $pdo->exec("CREATE TABLE IF NOT EXISTS compra_discriminaciones (
            id               INT AUTO_INCREMENT PRIMARY KEY,
            compra_id        INT           NOT NULL,
            neto_gravado     DECIMAL(18,2) NOT NULL DEFAULT 0,
            iva_alicuota     DECIMAL(7,3)  DEFAULT NULL,
            iva_importe      DECIMAL(18,2) NOT NULL DEFAULT 0,
            iva_inc_alicuota DECIMAL(7,3)  DEFAULT NULL,
            iva_inc_importe  DECIMAL(18,2) NOT NULL DEFAULT 0,
            cf_computable    DECIMAL(18,2) NOT NULL DEFAULT 0,
            CONSTRAINT fk_cdis_compra FOREIGN KEY (compra_id) REFERENCES compras(id) ON DELETE CASCADE,
            INDEX idx_cdis_compra (compra_id)
        ) {$charset}");

        $pdo->exec("CREATE TABLE IF NOT EXISTS compra_retenciones (
            id                       INT AUTO_INCREMENT PRIMARY KEY,
            compra_discriminacion_id INT           NOT NULL,
            tipo_retencion_id        INT           DEFAULT NULL,
            porcentaje               DECIMAL(7,3)  DEFAULT NULL,
            importe                  DECIMAL(18,2) NOT NULL DEFAULT 0,
            CONSTRAINT fk_cret_dis     FOREIGN KEY (compra_discriminacion_id) REFERENCES compra_discriminaciones(id) ON DELETE CASCADE,
            CONSTRAINT fk_cret_tiporet FOREIGN KEY (tipo_retencion_id)        REFERENCES tipos_retencion(id),
            INDEX idx_cret_dis (compra_discriminacion_id)
        ) {$charset}");
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS compra_retenciones');
        $pdo->exec('DROP TABLE IF EXISTS compra_discriminaciones');
        $pdo->exec('DROP TABLE IF EXISTS compras');
    }
};
