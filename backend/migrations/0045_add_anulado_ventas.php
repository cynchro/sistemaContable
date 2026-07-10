<?php

/**
 * Marca de comprobante de venta ANULADO + su fecha de anulación.
 *
 * El Libro IVA Digital (Portal IVA) tiene un archivo propio de comprobantes anulados
 * (`LIBRO_IVA_DIGITAL_CBTES_VENTAS_ANULADOS`, 44 pos.: fecha, tipo, punto de venta,
 * número y fecha de anulación — ver imagenes/disenio_registro_IVA_digital.pdf, pág. 8).
 * Hasta ahora el modelo no distinguía un comprobante anulado. Estas columnas permiten
 * marcarlo (`anulado = 'S'`) con su `fecha_anulacion` y generar ese archivo.
 *
 * Un comprobante anulado sigue existiendo (no se borra): se informa aparte en el régimen.
 */
return new class {
    public function up(\PDO $pdo): void
    {
        $pdo->exec("ALTER TABLE ventas ADD COLUMN anulado CHAR(1) NOT NULL DEFAULT 'N' AFTER total_informado");
        $pdo->exec("ALTER TABLE ventas ADD COLUMN fecha_anulacion DATE DEFAULT NULL AFTER anulado");
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('ALTER TABLE ventas DROP COLUMN fecha_anulacion');
        $pdo->exec('ALTER TABLE ventas DROP COLUMN anulado');
    }
};
