<?php

/**
 * Múltiples CAI por proveedor (hasta 5 con fecha de vencimiento, réplica del legacy
 * Visual IVA). Se guarda como JSON en una columna (lista acotada, se lee/edita como un
 * todo — mismo patrón que `vencimientos.tributos`). El `cai`/`fecha_cai` simple sigue
 * como CAI "principal" para la carga de compra.
 */
return new class {
    public function up(\PDO $pdo): void
    {
        $pdo->exec("ALTER TABLE iva_proveedores ADD COLUMN cais TEXT DEFAULT NULL AFTER fecha_cai");
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('ALTER TABLE iva_proveedores DROP COLUMN cais');
    }
};
