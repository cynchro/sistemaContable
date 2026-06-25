<?php

/**
 * Auditoría de operaciones del módulo IVA (registro de cambios — el legacy tenía una
 * tabla LOG). Cada escritura (POST/PUT/PATCH/DELETE) exitosa sobre las rutas de IVA
 * registra quién (user_id), cuándo, qué endpoint (método + uri), sobre qué entidad
 * (route params) y con qué datos (payload), más el status resultante.
 *
 * Lo escribe {@see App\Modules\Iva\Audit\AuditMiddleware} (nivel HTTP, no invasivo en
 * los services). Acotado por tenant. Sólo-lectura vía GET /iva/auditoria.
 */
return new class {
    public function up(\PDO $pdo): void
    {
        $charset = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

        $pdo->exec("CREATE TABLE IF NOT EXISTS iva_audit_log (
            id         BIGINT AUTO_INCREMENT PRIMARY KEY,
            tenant_id  CHAR(36)     NOT NULL,
            user_id    INT          DEFAULT NULL,
            metodo     VARCHAR(10)  NOT NULL,
            uri        VARCHAR(255) NOT NULL,
            params     TEXT         DEFAULT NULL,
            datos      MEDIUMTEXT   DEFAULT NULL,
            status     SMALLINT     NOT NULL,
            created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_audit_tenant (tenant_id, id),
            INDEX idx_audit_user (user_id)
        ) {$charset}");
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS iva_audit_log');
    }
};
