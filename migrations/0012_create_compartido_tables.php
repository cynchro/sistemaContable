<?php

/**
 * Esquema base del módulo Compartido (ingeniería inversa del sistema IVA legacy).
 *
 * Mapea las tablas de estructura/catálogos de Visual IVA a snake_case:
 *  - Catálogos AFIP globales (compartidos por todos los tenants, se siembran una vez):
 *    condiciones_iva, provincias, tipos_comprobante, tipos_documento, tipos_moneda,
 *    tipos_retencion, tipos_operacion_compra, tipos_operacion_venta.
 *  - Por tenant (estudio): rubros.
 *  - Por empresa: empresas (raíz de aislamiento, lleva tenant_id), periodos, cuentas.
 *
 * Decisiones (ver docs/ingenieria-inversa/iva.md): EMPRESA es entidad de dominio bajo el
 * tenant=estudio; los totales de período NO se persisten (se derivan); se podan campos de
 * licencia/integración legacy y los de facturación electrónica (fase AFIP posterior).
 */
return new class {
    public function up(\PDO $pdo): void
    {
        $charset = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

        // ── Catálogos AFIP globales ───────────────────────────────────────────
        $pdo->exec("CREATE TABLE IF NOT EXISTS condiciones_iva (
            id          INT AUTO_INCREMENT PRIMARY KEY,
            codigo      VARCHAR(5)  DEFAULT NULL,
            nombre      VARCHAR(50) NOT NULL,
            codigo_afip VARCHAR(2)  DEFAULT NULL
        ) {$charset}");

        $pdo->exec("CREATE TABLE IF NOT EXISTS provincias (
            id           INT AUTO_INCREMENT PRIMARY KEY,
            nombre       VARCHAR(50) NOT NULL,
            cp           DECIMAL(4,0) DEFAULT NULL,
            jurisdiccion SMALLINT     DEFAULT NULL
        ) {$charset}");

        $pdo->exec("CREATE TABLE IF NOT EXISTS tipos_comprobante (
            id        INT AUTO_INCREMENT PRIMARY KEY,
            codigo    VARCHAR(2)  DEFAULT NULL,
            nombre    VARCHAR(50) NOT NULL,
            cod_citi  VARCHAR(3)  DEFAULT ' ',
            acredita  CHAR(1)     NOT NULL DEFAULT 'N',
            signo     INT         NOT NULL DEFAULT 1 COMMENT 'TC_SIGNO: +1 suma, -1 resta (notas de crédito)'
        ) {$charset}");

        $pdo->exec("CREATE TABLE IF NOT EXISTS tipos_documento (
            id       INT AUTO_INCREMENT PRIMARY KEY,
            nombre   VARCHAR(30) NOT NULL,
            cod_afip INT         DEFAULT NULL
        ) {$charset}");

        $pdo->exec("CREATE TABLE IF NOT EXISTS tipos_moneda (
            id          INT AUTO_INCREMENT PRIMARY KEY,
            codigo_afip VARCHAR(5)   DEFAULT NULL,
            nombre      VARCHAR(100) NOT NULL
        ) {$charset}");

        $pdo->exec("CREATE TABLE IF NOT EXISTS tipos_operacion_compra (
            id     INT AUTO_INCREMENT PRIMARY KEY,
            codigo VARCHAR(15)  DEFAULT NULL,
            nombre VARCHAR(100) NOT NULL
        ) {$charset}");

        $pdo->exec("CREATE TABLE IF NOT EXISTS tipos_operacion_venta (
            id     INT AUTO_INCREMENT PRIMARY KEY,
            codigo VARCHAR(15)  DEFAULT NULL,
            nombre VARCHAR(100) NOT NULL
        ) {$charset}");

        $pdo->exec("CREATE TABLE IF NOT EXISTS tipos_retencion (
            id           INT AUTO_INCREMENT PRIMARY KEY,
            cod_afip     VARCHAR(10)   DEFAULT NULL,
            alicuota     DECIMAL(6,2)  NOT NULL DEFAULT 0,
            nombre       VARCHAR(200)  NOT NULL,
            tipo_rg3685  SMALLINT      NOT NULL DEFAULT 0,
            provincia_id INT           DEFAULT NULL,
            CONSTRAINT fk_tiporet_provincia FOREIGN KEY (provincia_id) REFERENCES provincias(id)
        ) {$charset}");

        // ── Por tenant (estudio) ──────────────────────────────────────────────
        $pdo->exec("CREATE TABLE IF NOT EXISTS rubros (
            id        INT AUTO_INCREMENT PRIMARY KEY,
            tenant_id CHAR(36)     NOT NULL,
            nombre    VARCHAR(300) NOT NULL,
            codigo    VARCHAR(7)   DEFAULT NULL,
            CONSTRAINT fk_rubros_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
            INDEX idx_rubros_tenant (tenant_id)
        ) {$charset}");

        // ── Por empresa ───────────────────────────────────────────────────────
        $pdo->exec("CREATE TABLE IF NOT EXISTS empresas (
            id                       INT AUTO_INCREMENT PRIMARY KEY,
            tenant_id                CHAR(36)     NOT NULL,
            condicion_iva_id         INT          DEFAULT NULL,
            nombre                   VARCHAR(150) NOT NULL,
            cuit                     VARCHAR(13)  DEFAULT NULL,
            domicilio                VARCHAR(150) DEFAULT NULL,
            localidad                VARCHAR(100) DEFAULT NULL,
            telefono                 VARCHAR(50)  DEFAULT NULL,
            ingresos_brutos          VARCHAR(20)  DEFAULT NULL,
            establecimiento          VARCHAR(12)  DEFAULT NULL,
            nro_libro_iva            VARCHAR(12)  DEFAULT NULL,
            nro_libro_iva_ven        VARCHAR(12)  DEFAULT NULL,
            fecha_inicio_actividades DATE         DEFAULT NULL,
            actividad1_id            INT          DEFAULT NULL,
            actividad2_id            INT          DEFAULT NULL,
            exporta                  CHAR(1)      NOT NULL DEFAULT 'N',
            inactiva                 CHAR(1)      NOT NULL DEFAULT 'N',
            CONSTRAINT fk_empresas_tenant    FOREIGN KEY (tenant_id)        REFERENCES tenants(id)        ON DELETE CASCADE,
            CONSTRAINT fk_empresas_condicion FOREIGN KEY (condicion_iva_id) REFERENCES condiciones_iva(id),
            INDEX idx_empresas_tenant (tenant_id)
        ) {$charset}");

        $pdo->exec("CREATE TABLE IF NOT EXISTS periodos (
            id         INT AUTO_INCREMENT PRIMARY KEY,
            empresa_id INT         NOT NULL,
            nombre     VARCHAR(50) NOT NULL,
            fecha_ini  DATE        DEFAULT NULL,
            fecha_fin  DATE        DEFAULT NULL,
            cerrado    CHAR(1)     NOT NULL DEFAULT 'N',
            CONSTRAINT fk_periodos_empresa FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE,
            INDEX idx_periodos_empresa (empresa_id)
        ) {$charset}");

        $pdo->exec("CREATE TABLE IF NOT EXISTS cuentas (
            id         INT AUTO_INCREMENT PRIMARY KEY,
            empresa_id INT          NOT NULL,
            codigo     VARCHAR(20)  DEFAULT NULL,
            nombre     VARCHAR(100) NOT NULL,
            CONSTRAINT fk_cuentas_empresa FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE,
            INDEX idx_cuentas_empresa (empresa_id)
        ) {$charset}");
    }

    public function down(\PDO $pdo): void
    {
        foreach ([
            'cuentas', 'periodos', 'empresas', 'rubros', 'tipos_retencion',
            'tipos_operacion_venta', 'tipos_operacion_compra', 'tipos_moneda',
            'tipos_documento', 'tipos_comprobante', 'provincias', 'condiciones_iva',
        ] as $table) {
            $pdo->exec("DROP TABLE IF EXISTS {$table}");
        }
    }
};
