<?php

/**
 * Campo auxiliar (texto libre) en comprobantes de venta y compra.
 *
 * Réplica del "Campo Auxiliar" del legacy Visual IVA (un dato libre configurable por
 * la empresa, sin efecto en el cálculo de IVA/total). Los demás campos del legacy
 * (tipo de documento, moneda y cotización) ya existían en el modelo:
 * ventas.tipo_documento_id / ventas.tipo_moneda_id / ventas.tipo_cambio y
 * compras.tipo_moneda_id / compras.tipo_cambio.
 */
return new class {
    public function up(\PDO $pdo): void
    {
        $pdo->exec("ALTER TABLE ventas  ADD COLUMN campo_auxiliar VARCHAR(255) DEFAULT NULL AFTER cai");
        $pdo->exec("ALTER TABLE compras ADD COLUMN campo_auxiliar VARCHAR(255) DEFAULT NULL AFTER cai");
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('ALTER TABLE ventas  DROP COLUMN campo_auxiliar');
        $pdo->exec('ALTER TABLE compras DROP COLUMN campo_auxiliar');
    }
};
