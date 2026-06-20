<?php

/**
 * Percepciones a nivel comprobante (decisión de dominio confirmada por el contador —
 * ver respuestas.md A1/A2).
 *
 * Cambio de modelo: en ventas/compras, lo que el legacy guardaba como "retenciones"
 * colgando de cada línea de discriminación (venta_retenciones / compra_retenciones)
 * son en realidad PERCEPCIONES del comprobante (el emisor es agente de percepción).
 * El contador confirmó que esas percepciones SÍ integran el total que paga el cliente
 * (factura Saint-Gobain de ejemplo: Subtotal + IVA + Perc. IVA 3% + Perc. IIBB 2,5% =
 * Importe final). Por eso se modelan a nivel comprobante y se suman al total.
 *
 * Además, la BASE de cálculo depende del tipo y la provincia (respuestas.md A2):
 *  - Percepción IVA: 3% sobre neto al 21% + 1,5% sobre neto al 10,5% (base 'iva_percepcion').
 *  - Perc. IIBB (Catamarca 2,5%) y Municipal (Catamarca 0,6%): sobre el neto total
 *    ('neto_gravado'); con impuestos internos, sobre neto + imp_interno
 *    ('neto_mas_imp_interno').
 * Se agrega `base_calculo` a tipos_retencion para parametrizarlo por tipo.
 *
 * Las tablas venta_retenciones / compra_retenciones se reemplazan por
 * venta_percepciones / compra_percepciones (a nivel comprobante). El sistema es
 * greenfield (sin datos reales aún), así que se descartan las viejas.
 */
return new class {
    public function up(\PDO $pdo): void
    {
        $charset = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

        // 1) Parametrización de la base de cálculo por tipo de percepción (A2).
        if (!$this->columnExists($pdo, 'tipos_retencion', 'base_calculo')) {
            $pdo->exec(
                "ALTER TABLE tipos_retencion
                 ADD COLUMN base_calculo VARCHAR(30) NOT NULL DEFAULT 'neto_gravado'"
            );
        }
        // La Percepción IVA estándar (tipo_rg3685 = 1) usa la base tramada por alícuota.
        $pdo->exec(
            "UPDATE tipos_retencion SET base_calculo = 'iva_percepcion'
             WHERE tipo_rg3685 = 1 AND tenant_id IS NULL"
        );

        // 2) Percepciones de venta a nivel comprobante.
        $pdo->exec("CREATE TABLE IF NOT EXISTS venta_percepciones (
            id                INT AUTO_INCREMENT PRIMARY KEY,
            venta_id          INT           NOT NULL,
            tipo_retencion_id INT           DEFAULT NULL,
            provincia_id      INT           DEFAULT NULL,
            base              DECIMAL(18,2) NOT NULL DEFAULT 0,
            alicuota          DECIMAL(7,3)  DEFAULT NULL,
            importe           DECIMAL(18,2) NOT NULL DEFAULT 0,
            CONSTRAINT fk_vperc_venta     FOREIGN KEY (venta_id)          REFERENCES ventas(id)           ON DELETE CASCADE,
            CONSTRAINT fk_vperc_tiporet   FOREIGN KEY (tipo_retencion_id) REFERENCES tipos_retencion(id),
            CONSTRAINT fk_vperc_provincia FOREIGN KEY (provincia_id)      REFERENCES provincias(id),
            INDEX idx_vperc_venta (venta_id)
        ) {$charset}");

        // 3) Percepciones de compra a nivel comprobante (las sufridas → A4: pagos a cuenta).
        $pdo->exec("CREATE TABLE IF NOT EXISTS compra_percepciones (
            id                INT AUTO_INCREMENT PRIMARY KEY,
            compra_id         INT           NOT NULL,
            tipo_retencion_id INT           DEFAULT NULL,
            provincia_id      INT           DEFAULT NULL,
            base              DECIMAL(18,2) NOT NULL DEFAULT 0,
            alicuota          DECIMAL(7,3)  DEFAULT NULL,
            importe           DECIMAL(18,2) NOT NULL DEFAULT 0,
            CONSTRAINT fk_cperc_compra    FOREIGN KEY (compra_id)         REFERENCES compras(id)          ON DELETE CASCADE,
            CONSTRAINT fk_cperc_tiporet   FOREIGN KEY (tipo_retencion_id) REFERENCES tipos_retencion(id),
            CONSTRAINT fk_cperc_provincia FOREIGN KEY (provincia_id)      REFERENCES provincias(id),
            INDEX idx_cperc_compra (compra_id)
        ) {$charset}");

        // 4) Las viejas tablas por línea quedan reemplazadas por las de comprobante.
        $pdo->exec('DROP TABLE IF EXISTS venta_retenciones');
        $pdo->exec('DROP TABLE IF EXISTS compra_retenciones');
    }

    public function down(\PDO $pdo): void
    {
        $charset = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

        $pdo->exec('DROP TABLE IF EXISTS venta_percepciones');
        $pdo->exec('DROP TABLE IF EXISTS compra_percepciones');

        if ($this->columnExists($pdo, 'tipos_retencion', 'base_calculo')) {
            $pdo->exec('ALTER TABLE tipos_retencion DROP COLUMN base_calculo');
        }

        // Restaura las tablas por línea (estado previo de 0014/0015).
        $pdo->exec("CREATE TABLE IF NOT EXISTS venta_retenciones (
            id                      INT AUTO_INCREMENT PRIMARY KEY,
            venta_discriminacion_id INT           NOT NULL,
            tipo_retencion_id       INT           DEFAULT NULL,
            porcentaje              DECIMAL(7,3)  DEFAULT NULL,
            importe                 DECIMAL(18,2) NOT NULL DEFAULT 0,
            CONSTRAINT fk_vret_dis      FOREIGN KEY (venta_discriminacion_id) REFERENCES venta_discriminaciones(id) ON DELETE CASCADE,
            CONSTRAINT fk_vret_tiporet  FOREIGN KEY (tipo_retencion_id)       REFERENCES tipos_retencion(id),
            INDEX idx_vret_dis (venta_discriminacion_id)
        ) {$charset}");
        $pdo->exec("CREATE TABLE IF NOT EXISTS compra_retenciones (
            id                       INT AUTO_INCREMENT PRIMARY KEY,
            compra_discriminacion_id INT           NOT NULL,
            tipo_retencion_id        INT           DEFAULT NULL,
            porcentaje               DECIMAL(7,3)  DEFAULT NULL,
            importe                  DECIMAL(18,2) NOT NULL DEFAULT 0,
            CONSTRAINT fk_cret_dis     FOREIGN KEY (compra_discriminacion_id) REFERENCES compra_discriminaciones(id) ON DELETE CASCADE,
            CONSTRAINT fk_cret_tiporet FOREIGN KEY (tipo_retencion_id)        REFERENCES tipos_retencion(id),
            INDEX idx_cret_dis (compra_discriminacion_id)
        ) {$charset}");
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
