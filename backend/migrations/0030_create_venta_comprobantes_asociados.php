<?php

/**
 * Comprobantes asociados de una venta (CbtesAsoc de WSFEv1). Obligatorios al emitir
 * notas de crédito/débito electrónicas: referencian la(s) factura(s) original(es).
 * Forman parte del agregado Venta (se crean/borran junto con la cabecera).
 */
return new class {
    public function up(\PDO $pdo): void
    {
        $pdo->exec("CREATE TABLE IF NOT EXISTS venta_comprobantes_asociados (
            id                  INT AUTO_INCREMENT PRIMARY KEY,
            venta_id            INT          NOT NULL,
            tipo_comprobante_id INT          DEFAULT NULL,
            letra               VARCHAR(1)   DEFAULT NULL,
            punto_venta         VARCHAR(5)   DEFAULT NULL,
            numero              VARCHAR(8)   DEFAULT NULL,
            cuit                VARCHAR(13)  DEFAULT NULL,
            fecha               DATE         DEFAULT NULL,
            CONSTRAINT fk_vasoc_venta FOREIGN KEY (venta_id) REFERENCES ventas(id) ON DELETE CASCADE,
            CONSTRAINT fk_vasoc_tc FOREIGN KEY (tipo_comprobante_id) REFERENCES tipos_comprobante(id),
            INDEX idx_vasoc_venta (venta_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS venta_comprobantes_asociados');
    }
};
