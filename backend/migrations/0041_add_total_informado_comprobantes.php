<?php

/**
 * Total informado del comprobante en ventas y compras.
 *
 * Nuestro motor deriva el total (`neto + iva + percepciones`). Para el Libro IVA Digital,
 * AFIP espera el **total real del comprobante** (el que figura en la factura / el que ARCA
 * ya tiene), que puede diferir del recalculado por el redondeo propio de AFIP (validado
 * contra los archivos reales de GRUPO MAZZUCO — ver docs/ingenieria-inversa/
 * analisis-chat-federico-julio2026.md §4/§5). Cuando `total_informado` está presente, el
 * Libro IVA Digital lo usa en el campo `total` en vez del derivado; si es NULL, sigue el
 * derivado. El importador lo setea desde la columna "Importe Total" del CSV de ARCA.
 */
return new class {
    public function up(\PDO $pdo): void
    {
        $pdo->exec("ALTER TABLE ventas  ADD COLUMN total_informado DECIMAL(18,2) DEFAULT NULL AFTER total");
        $pdo->exec("ALTER TABLE compras ADD COLUMN total_informado DECIMAL(18,2) DEFAULT NULL AFTER total");
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('ALTER TABLE ventas  DROP COLUMN total_informado');
        $pdo->exec('ALTER TABLE compras DROP COLUMN total_informado');
    }
};
