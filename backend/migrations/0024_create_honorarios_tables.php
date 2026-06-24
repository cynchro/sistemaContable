<?php

/**
 * Módulo Honorarios (de sistemaCuarto/Synergys): honorarios profesionales del estudio.
 * Ingeniería inversa de servicios + factores_complejidad (+ tarea_servicios) del legacy.
 *
 * - `servicios`: catálogo por tenant, con `uc` (unidades de cuenta) y a qué tipo de
 *   persona aplica (PF/PJ).
 * - `factores_complejidad`: multiplicador por nivel de complejidad.
 * - `honorarios`: documento (presupuesto/liquidación de honorarios) por empresa
 *   (contribuyente), con `valor_uc` (valor de la UC al momento) y líneas.
 * - `honorario_lineas`: servicio + complejidad + cantidad → importe calculado
 *   (= uc · valor_uc · factor · cantidad).
 */
return new class {
    public function up(\PDO $pdo): void
    {
        $cs = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

        $pdo->exec("CREATE TABLE IF NOT EXISTS servicios (
            id          INT AUTO_INCREMENT PRIMARY KEY,
            tenant_id   CHAR(36)     NOT NULL,
            codigo      VARCHAR(20)  DEFAULT NULL,
            seccion     VARCHAR(100) DEFAULT NULL,
            descripcion VARCHAR(255) NOT NULL,
            uc          DECIMAL(12,2) NOT NULL DEFAULT 0 COMMENT 'unidades de cuenta',
            aplica_pf   CHAR(1)      NOT NULL DEFAULT 'S',
            aplica_pj   CHAR(1)      NOT NULL DEFAULT 'S',
            activo      CHAR(1)      NOT NULL DEFAULT 'S',
            CONSTRAINT fk_servicio_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
            INDEX idx_servicio_tenant (tenant_id)
        ) {$cs}");

        $pdo->exec("CREATE TABLE IF NOT EXISTS factores_complejidad (
            id        INT AUTO_INCREMENT PRIMARY KEY,
            tenant_id CHAR(36)    NOT NULL,
            nivel     VARCHAR(30) NOT NULL COMMENT 'baja / media / alta ...',
            factor    DECIMAL(6,3) NOT NULL DEFAULT 1,
            label     VARCHAR(60) DEFAULT NULL,
            orden     INT         DEFAULT NULL,
            CONSTRAINT fk_factor_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
            INDEX idx_factor_tenant (tenant_id)
        ) {$cs}");

        $pdo->exec("CREATE TABLE IF NOT EXISTS honorarios (
            id          INT AUTO_INCREMENT PRIMARY KEY,
            empresa_id  INT          NOT NULL,
            fecha       DATE         DEFAULT NULL,
            valor_uc    DECIMAL(12,2) NOT NULL DEFAULT 0,
            descripcion VARCHAR(255) DEFAULT NULL,
            estado      VARCHAR(30)  NOT NULL DEFAULT 'borrador',
            total       DECIMAL(18,2) NOT NULL DEFAULT 0,
            CONSTRAINT fk_honorario_empresa FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE,
            INDEX idx_honorario_empresa (empresa_id)
        ) {$cs}");

        $pdo->exec("CREATE TABLE IF NOT EXISTS honorario_lineas (
            id           INT AUTO_INCREMENT PRIMARY KEY,
            honorario_id INT          NOT NULL,
            servicio_id  INT          DEFAULT NULL,
            codigo       VARCHAR(20)  DEFAULT NULL,
            descripcion  VARCHAR(255) DEFAULT NULL,
            uc           DECIMAL(12,2) NOT NULL DEFAULT 0,
            complejidad  VARCHAR(30)  DEFAULT NULL,
            factor       DECIMAL(6,3) NOT NULL DEFAULT 1,
            cantidad     DECIMAL(12,2) NOT NULL DEFAULT 1,
            importe      DECIMAL(18,2) NOT NULL DEFAULT 0,
            CONSTRAINT fk_honlinea_honorario FOREIGN KEY (honorario_id) REFERENCES honorarios(id) ON DELETE CASCADE,
            CONSTRAINT fk_honlinea_servicio  FOREIGN KEY (servicio_id)  REFERENCES servicios(id),
            INDEX idx_honlinea_honorario (honorario_id)
        ) {$cs}");
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS honorario_lineas');
        $pdo->exec('DROP TABLE IF EXISTS honorarios');
        $pdo->exec('DROP TABLE IF EXISTS factores_complejidad');
        $pdo->exec('DROP TABLE IF EXISTS servicios');
    }
};
