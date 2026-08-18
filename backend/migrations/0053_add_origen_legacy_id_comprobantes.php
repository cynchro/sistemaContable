<?php

/**
 * Trazabilidad + idempotencia para la migración histórica de comprobantes reales de
 * Visual IVA (Etapa 5 del satélite, documento-1.md §9 — "escalado a todos los
 * contribuyentes"): 402.563 compras + 1.146.963 ventas reales, 2015-2026.
 *
 * `origen_legacy_id` guarda el CMP_ID/VTA_ID original de Firebird. Nullable (los
 * comprobantes cargados a mano o por el importador CSV no lo llevan) + UNIQUE (permite
 * detectar y saltear filas ya importadas si el seeder se vuelve a correr, sin volver a
 * consultar toda la tabla comprobante por comprobante).
 */

return new class {
    public function up(\PDO $pdo): void
    {
        $pdo->exec('ALTER TABLE compras ADD COLUMN origen_legacy_id INT DEFAULT NULL AFTER id');
        $pdo->exec('ALTER TABLE compras ADD UNIQUE KEY uq_compras_origen_legacy (origen_legacy_id)');
        $pdo->exec('ALTER TABLE ventas ADD COLUMN origen_legacy_id INT DEFAULT NULL AFTER id');
        $pdo->exec('ALTER TABLE ventas ADD UNIQUE KEY uq_ventas_origen_legacy (origen_legacy_id)');
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('ALTER TABLE ventas DROP KEY uq_ventas_origen_legacy, DROP COLUMN origen_legacy_id');
        $pdo->exec('ALTER TABLE compras DROP KEY uq_compras_origen_legacy, DROP COLUMN origen_legacy_id');
    }
};
