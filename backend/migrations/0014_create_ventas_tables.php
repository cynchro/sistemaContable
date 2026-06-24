<?php

/**
 * Comprobantes de venta del módulo Iva (ingeniería inversa de VENTAS /
 * VENTA_DISCRIMINACION / RETENCIONES del legacy Visual IVA).
 *
 * Agregado: ventas (cabecera) → venta_discriminaciones (líneas por alícuota) →
 * venta_retenciones (por línea). Se crean/editan/borran juntos en una transacción.
 *
 * Decisiones (ver docs/ingenieria-inversa/iva.md):
 *  - El total y el IVA de cada línea los calcula el motor (IvaComprobanteCalculator);
 *    se persisten ya calculados. Los totales de período se derivan, no se guardan.
 *  - `comprobante` es columna generada = letra+punto_venta+numero (como el legacy).
 *  - Se difieren a fases posteriores: campos de factura electrónica (ELECTRO_*),
 *    imputación contable (VTA_CTA_*, debe/haber), totales producto/servicio,
 *    id_actividad y campos auxiliares. Se agregan cuando lleguen esos hitos.
 */
return new class {
    public function up(\PDO $pdo): void
    {
        $charset = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

        $pdo->exec("CREATE TABLE IF NOT EXISTS ventas (
            id                      INT AUTO_INCREMENT PRIMARY KEY,
            periodo_id              INT          NOT NULL,
            tipo_comprobante_id     INT          DEFAULT NULL,
            tipo_documento_id       INT          DEFAULT NULL,
            condicion_iva_id        INT          DEFAULT NULL,
            provincia_id            INT          DEFAULT NULL,
            rubro_id                INT          DEFAULT NULL,
            tipo_operacion_venta_id INT          DEFAULT NULL,
            tipo_moneda_id          INT          DEFAULT NULL,
            cliente_id              INT          DEFAULT NULL,
            fecha                   DATE         DEFAULT NULL,
            cliente_nombre          VARCHAR(100) DEFAULT NULL,
            cuit                    VARCHAR(13)  DEFAULT NULL,
            letra                   VARCHAR(1)   DEFAULT NULL,
            punto_venta             VARCHAR(5)   DEFAULT NULL,
            numero                  VARCHAR(8)   DEFAULT NULL,
            numero_fin              VARCHAR(8)   DEFAULT NULL,
            neto_no_grav            DECIMAL(18,2) NOT NULL DEFAULT 0,
            exento                  DECIMAL(18,2) NOT NULL DEFAULT 0,
            imp_interno             DECIMAL(18,2) NOT NULL DEFAULT 0,
            total                   DECIMAL(18,2) NOT NULL DEFAULT 0,
            tipo_cambio             DECIMAL(18,4) DEFAULT NULL,
            concepto                SMALLINT     NOT NULL DEFAULT 1,
            cai                     VARCHAR(15)  DEFAULT NULL,
            fecha_cai               DATE         DEFAULT NULL,
            comprobante             VARCHAR(255) AS (CONCAT(COALESCE(letra,''), COALESCE(punto_venta,''), COALESCE(numero,''))) STORED,
            CONSTRAINT fk_ventas_periodo   FOREIGN KEY (periodo_id)              REFERENCES periodos(id)               ON DELETE CASCADE,
            CONSTRAINT fk_ventas_tc        FOREIGN KEY (tipo_comprobante_id)     REFERENCES tipos_comprobante(id),
            CONSTRAINT fk_ventas_td        FOREIGN KEY (tipo_documento_id)       REFERENCES tipos_documento(id),
            CONSTRAINT fk_ventas_condicion FOREIGN KEY (condicion_iva_id)        REFERENCES condiciones_iva(id),
            CONSTRAINT fk_ventas_provincia FOREIGN KEY (provincia_id)            REFERENCES provincias(id),
            CONSTRAINT fk_ventas_rubro     FOREIGN KEY (rubro_id)                REFERENCES rubros(id),
            CONSTRAINT fk_ventas_tipoop    FOREIGN KEY (tipo_operacion_venta_id) REFERENCES tipos_operacion_venta(id),
            CONSTRAINT fk_ventas_moneda    FOREIGN KEY (tipo_moneda_id)          REFERENCES tipos_moneda(id),
            CONSTRAINT fk_ventas_cliente   FOREIGN KEY (cliente_id)              REFERENCES iva_clientes(id),
            INDEX idx_ventas_periodo (periodo_id)
        ) {$charset}");

        $pdo->exec("CREATE TABLE IF NOT EXISTS venta_discriminaciones (
            id               INT AUTO_INCREMENT PRIMARY KEY,
            venta_id         INT           NOT NULL,
            neto_gravado     DECIMAL(18,2) NOT NULL DEFAULT 0,
            iva_alicuota     DECIMAL(7,3)  DEFAULT NULL,
            iva_importe      DECIMAL(18,2) NOT NULL DEFAULT 0,
            iva_inc_alicuota DECIMAL(7,3)  DEFAULT NULL,
            iva_inc_importe  DECIMAL(18,2) NOT NULL DEFAULT 0,
            reintegro_t      DECIMAL(18,2) DEFAULT NULL,
            concepto         SMALLINT      DEFAULT NULL,
            CONSTRAINT fk_vdis_venta FOREIGN KEY (venta_id) REFERENCES ventas(id) ON DELETE CASCADE,
            INDEX idx_vdis_venta (venta_id)
        ) {$charset}");

        $pdo->exec("CREATE TABLE IF NOT EXISTS venta_retenciones (
            id                      INT AUTO_INCREMENT PRIMARY KEY,
            venta_discriminacion_id INT           NOT NULL,
            tipo_retencion_id       INT           DEFAULT NULL,
            porcentaje              DECIMAL(7,3)  DEFAULT NULL,
            importe                 DECIMAL(18,2) NOT NULL DEFAULT 0,
            CONSTRAINT fk_vret_dis      FOREIGN KEY (venta_discriminacion_id) REFERENCES venta_discriminaciones(id) ON DELETE CASCADE,
            CONSTRAINT fk_vret_tiporet  FOREIGN KEY (tipo_retencion_id)       REFERENCES tipos_retencion(id),
            INDEX idx_vret_dis (venta_discriminacion_id)
        ) {$charset}");
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS venta_retenciones');
        $pdo->exec('DROP TABLE IF EXISTS venta_discriminaciones');
        $pdo->exec('DROP TABLE IF EXISTS ventas');
    }
};
