<?php

/**
 * Módulo Fiscal (de sistemaCuarto/Synergys): obligaciones fiscales del contribuyente.
 * Ver docs/ingenieria-inversa/sistemacuarto.md.
 *
 * - `tributos`: catálogo jerárquico por tenant (estudio).
 * - `vencimientos`: obligaciones por empresa (contribuyente) con workflow de estados.
 *
 * `Persona` (contribuyente) del legacy = `empresas` canónica (sin redundancia).
 */
return new class {
    public function up(\PDO $pdo): void
    {
        $cs = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

        $pdo->exec("CREATE TABLE IF NOT EXISTS tributos (
            id           INT AUTO_INCREMENT PRIMARY KEY,
            tenant_id    CHAR(36)     NOT NULL,
            parent_id    INT          DEFAULT NULL,
            nombre       VARCHAR(150) NOT NULL,
            tipo_persona VARCHAR(20)  DEFAULT NULL COMMENT 'fisica / juridica',
            subcategoria VARCHAR(100) DEFAULT NULL,
            is_activo    CHAR(1)      NOT NULL DEFAULT 'S',
            CONSTRAINT fk_tributo_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
            CONSTRAINT fk_tributo_parent FOREIGN KEY (parent_id) REFERENCES tributos(id) ON DELETE SET NULL,
            INDEX idx_tributo_tenant (tenant_id)
        ) {$cs}");

        $pdo->exec("CREATE TABLE IF NOT EXISTS vencimientos (
            id                      INT AUTO_INCREMENT PRIMARY KEY,
            empresa_id              INT          NOT NULL,
            agencia                 VARCHAR(30)  DEFAULT NULL COMMENT 'AFIP/ARCA, ARBA, municipal, ...',
            jurisdiccion            VARCHAR(50)  DEFAULT NULL,
            tributos                JSON         DEFAULT NULL,
            titulo                  VARCHAR(150) DEFAULT NULL,
            descripcion             VARCHAR(255) DEFAULT NULL,
            fecha_vencimiento       DATETIME     DEFAULT NULL,
            estado                  VARCHAR(40)  NOT NULL DEFAULT 'creado',
            observaciones           TEXT         DEFAULT NULL,
            observaciones_estado    TEXT         DEFAULT NULL,
            usuario_creador_id      INT          DEFAULT NULL,
            usuario_actualizador_id INT          DEFAULT NULL,
            is_activo               CHAR(1)      NOT NULL DEFAULT 'S',
            CONSTRAINT fk_venc_empresa FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE,
            INDEX idx_venc_empresa (empresa_id),
            INDEX idx_venc_fecha (fecha_vencimiento),
            INDEX idx_venc_estado (estado)
        ) {$cs}");
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS vencimientos');
        $pdo->exec('DROP TABLE IF EXISTS tributos');
    }
};
