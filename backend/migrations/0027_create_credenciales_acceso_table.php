<?php

/**
 * Credenciales de acceso del contribuyente (de sistemaCuarto/Synergys).
 *
 * Unifica dos tablas casi idénticas del legacy en una sola (sin redundancia):
 *   - `cuentas`  → portales fiscales/web (sistema=AFIP, RENTAS; usuario, clave)
 *   - `tarjetas` → procesadoras de tarjeta (sistema=VISA, NARANJA, FIRSTDATA; usuario, clave)
 * Se distinguen por la columna `tipo` ('fiscal' | 'tarjeta').
 *
 * La `clave` se guarda CIFRADA en reposo (App\Support\Crypto, AES-256-GCM); el estudio
 * la recupera en claro para operar en el portal del cliente.
 */
return new class {
    public function up(\PDO $pdo): void
    {
        $pdo->exec("CREATE TABLE IF NOT EXISTS credenciales_acceso (
            id            INT AUTO_INCREMENT PRIMARY KEY,
            empresa_id    INT          NOT NULL,
            tipo          VARCHAR(20)  NOT NULL DEFAULT 'fiscal' COMMENT 'fiscal / tarjeta',
            sistema       VARCHAR(60)  NOT NULL COMMENT 'AFIP, RENTAS, VISA, NARANJA, ...',
            usuario       VARCHAR(150) DEFAULT NULL,
            clave         TEXT         DEFAULT NULL COMMENT 'cifrada en reposo (Crypto)',
            estado        VARCHAR(20)  NOT NULL DEFAULT 'activa' COMMENT 'activa/inactiva/bloqueada/cerrada',
            observaciones TEXT         DEFAULT NULL,
            CONSTRAINT fk_credencial_empresa FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE,
            INDEX idx_credencial_empresa (empresa_id),
            INDEX idx_credencial_tipo (empresa_id, tipo)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS credenciales_acceso');
    }
};
