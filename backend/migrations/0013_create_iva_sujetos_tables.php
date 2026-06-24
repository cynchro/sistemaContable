<?php

/**
 * Sujetos del módulo Iva: clientes y proveedores (ingeniería inversa de
 * CLIENTES / PROVEEDORES del sistema legacy Visual IVA).
 *
 * Decisiones (ver docs/ingenieria-inversa/iva.md):
 *  - Prefijo `iva_` para no colisionar con la tabla demo `clientes` del framework.
 *  - Acotados por `empresa_id` (la empresa pertenece al tenant=estudio).
 *  - FKs a catálogos: condiciones_iva, provincias (globales), cuentas, rubros.
 *  - Se conserva `esglobal` por fidelidad (sujeto compartido entre empresas en el
 *    legacy); por ahora las consultas filtran por empresa. La validación de que
 *    cuenta/rubro pertenezcan al mismo ámbito se hará en el Service más adelante.
 *  - Los CAI secundarios (CAI2..5) del legacy se omiten por poco uso; queda el
 *    CAI principal en proveedores.
 */
return new class {
    public function up(\PDO $pdo): void
    {
        $charset = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

        $pdo->exec("CREATE TABLE IF NOT EXISTS iva_clientes (
            id               INT AUTO_INCREMENT PRIMARY KEY,
            empresa_id       INT          NOT NULL,
            condicion_iva_id INT          DEFAULT NULL,
            provincia_id     INT          DEFAULT NULL,
            cuenta_id        INT          DEFAULT NULL,
            rubro_id         INT          DEFAULT NULL,
            nombre           VARCHAR(100) NOT NULL,
            cuit             VARCHAR(13)  DEFAULT NULL,
            domicilio        VARCHAR(100) DEFAULT NULL,
            localidad        VARCHAR(50)  DEFAULT NULL,
            telefono         VARCHAR(25)  DEFAULT NULL,
            ingresos_brutos  VARCHAR(20)  DEFAULT NULL,
            esglobal         CHAR(1)      NOT NULL DEFAULT 'N',
            CONSTRAINT fk_ivacli_empresa   FOREIGN KEY (empresa_id)       REFERENCES empresas(id)        ON DELETE CASCADE,
            CONSTRAINT fk_ivacli_condicion FOREIGN KEY (condicion_iva_id) REFERENCES condiciones_iva(id),
            CONSTRAINT fk_ivacli_provincia FOREIGN KEY (provincia_id)     REFERENCES provincias(id),
            CONSTRAINT fk_ivacli_cuenta    FOREIGN KEY (cuenta_id)        REFERENCES cuentas(id),
            CONSTRAINT fk_ivacli_rubro     FOREIGN KEY (rubro_id)         REFERENCES rubros(id),
            INDEX idx_ivacli_empresa (empresa_id)
        ) {$charset}");

        $pdo->exec("CREATE TABLE IF NOT EXISTS iva_proveedores (
            id               INT AUTO_INCREMENT PRIMARY KEY,
            empresa_id       INT          NOT NULL,
            condicion_iva_id INT          DEFAULT NULL,
            provincia_id     INT          DEFAULT NULL,
            cuenta_id        INT          DEFAULT NULL,
            rubro_id         INT          DEFAULT NULL,
            nombre           VARCHAR(100) NOT NULL,
            cuit             VARCHAR(13)  DEFAULT NULL,
            domicilio        VARCHAR(100) DEFAULT NULL,
            localidad        VARCHAR(50)  DEFAULT NULL,
            telefono         VARCHAR(25)  DEFAULT NULL,
            cp               VARCHAR(8)   DEFAULT NULL,
            ingresos_brutos  VARCHAR(20)  DEFAULT NULL,
            cai              VARCHAR(15)  DEFAULT NULL,
            fecha_cai        DATE         DEFAULT NULL,
            esglobal         CHAR(1)      NOT NULL DEFAULT 'N',
            CONSTRAINT fk_ivaprov_empresa   FOREIGN KEY (empresa_id)       REFERENCES empresas(id)        ON DELETE CASCADE,
            CONSTRAINT fk_ivaprov_condicion FOREIGN KEY (condicion_iva_id) REFERENCES condiciones_iva(id),
            CONSTRAINT fk_ivaprov_provincia FOREIGN KEY (provincia_id)     REFERENCES provincias(id),
            CONSTRAINT fk_ivaprov_cuenta    FOREIGN KEY (cuenta_id)        REFERENCES cuentas(id),
            CONSTRAINT fk_ivaprov_rubro     FOREIGN KEY (rubro_id)         REFERENCES rubros(id),
            INDEX idx_ivaprov_empresa (empresa_id)
        ) {$charset}");
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS iva_proveedores');
        $pdo->exec('DROP TABLE IF EXISTS iva_clientes');
    }
};
