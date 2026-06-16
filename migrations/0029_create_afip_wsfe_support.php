<?php

/**
 * Soporte de factura electrónica (WSFEv1) sobre el módulo Iva:
 *  - `puntos_venta`: registro de puntos de venta por empresa (para numeración/validación).
 *  - columnas de autorización en `ventas`: CAE y su vencimiento, resultado AFIP (A/R/P),
 *    observaciones (JSON) y fechas de servicio (conceptos 2/3).
 *
 * Los códigos AFIP que dependen de combinaciones (CbteTipo = tipo+letra, Id de alícuota,
 * CondicionIVAReceptorId) se resuelven con tablas oficiales en código (Afip\Wsfe\*Resolver),
 * no como columnas. Idempotente ante columnas/tablas ya existentes.
 */
return new class {
    public function up(\PDO $pdo): void
    {
        $pdo->exec("CREATE TABLE IF NOT EXISTS puntos_venta (
            id          INT AUTO_INCREMENT PRIMARY KEY,
            empresa_id  INT          NOT NULL,
            numero      INT          NOT NULL,
            descripcion VARCHAR(120) DEFAULT NULL,
            tipo_emision VARCHAR(10) NOT NULL DEFAULT 'CAE' COMMENT 'CAE / CAEA',
            activo      CHAR(1)      NOT NULL DEFAULT 'S',
            CONSTRAINT fk_pv_empresa FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE,
            UNIQUE KEY uq_pv_empresa_numero (empresa_id, numero)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        foreach ([
            'cae'            => "VARCHAR(14) DEFAULT NULL COMMENT 'CAE de AFIP'",
            'cae_vto'        => 'DATE DEFAULT NULL',
            'afip_resultado' => "CHAR(1) DEFAULT NULL COMMENT 'A aprobado / R rechazado / P parcial'",
            'afip_obs'       => 'TEXT DEFAULT NULL',
            'fch_serv_desde' => 'DATE DEFAULT NULL',
            'fch_serv_hasta' => 'DATE DEFAULT NULL',
            'fch_vto_pago'   => 'DATE DEFAULT NULL',
        ] as $col => $def) {
            if (!$this->columnExists($pdo, 'ventas', $col)) {
                $pdo->exec("ALTER TABLE ventas ADD COLUMN {$col} {$def}");
            }
        }
    }

    public function down(\PDO $pdo): void
    {
        foreach (
            ['cae', 'cae_vto', 'afip_resultado', 'afip_obs', 'fch_serv_desde', 'fch_serv_hasta', 'fch_vto_pago'] as $col
        ) {
            if ($this->columnExists($pdo, 'ventas', $col)) {
                $pdo->exec("ALTER TABLE ventas DROP COLUMN {$col}");
            }
        }

        $pdo->exec('DROP TABLE IF EXISTS puntos_venta');
    }

    private function columnExists(\PDO $pdo, string $table, string $column): bool
    {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.columns
             WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?'
        );
        $stmt->execute([$table, $column]);

        return (int) $stmt->fetchColumn() > 0;
    }
};
