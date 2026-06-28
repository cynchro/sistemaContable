<?php

/**
 * Estrategias adicionales de asignación de actividad para la DJ IVA Simple (A15, Fase 2).
 * Ver docs/ingenieria-inversa/dj-iva-simple-actividad.md (v2).
 *
 *  - `actividad_alicuota`: mapa {empresa, alícuota → actividad}. Caso construcción:
 *    10,5% → residencial, 21% → no residencial. Se resuelve por LÍNEA (cada alícuota).
 *  - `actividad_receptor`: mapa {empresa, cliente → actividad}. Caso "todo lo facturado
 *    a tal CUIT va a tal actividad" (ej. Minera Galaxy Lithium → 99000).
 *
 * Precedencia de resolución (en DjIvaSimpleRepository): actividad del comprobante (override)
 * → receptor → punto de venta → alícuota → actividad por defecto.
 */
return new class {
    public function up(\PDO $pdo): void
    {
        $charset = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

        $pdo->exec("CREATE TABLE IF NOT EXISTS actividad_alicuota (
            id           INT AUTO_INCREMENT PRIMARY KEY,
            empresa_id   INT          NOT NULL,
            alicuota     DECIMAL(7,3) NOT NULL,
            actividad_id INT          NOT NULL,
            CONSTRAINT fk_aali_empresa   FOREIGN KEY (empresa_id)   REFERENCES empresas(id)            ON DELETE CASCADE,
            CONSTRAINT fk_aali_actividad FOREIGN KEY (actividad_id) REFERENCES empresa_actividades(id) ON DELETE CASCADE,
            UNIQUE KEY uq_aali (empresa_id, alicuota),
            INDEX idx_aali_empresa (empresa_id)
        ) {$charset}");

        $pdo->exec("CREATE TABLE IF NOT EXISTS actividad_receptor (
            id           INT AUTO_INCREMENT PRIMARY KEY,
            empresa_id   INT NOT NULL,
            cliente_id   INT NOT NULL,
            actividad_id INT NOT NULL,
            CONSTRAINT fk_arec_empresa   FOREIGN KEY (empresa_id)   REFERENCES empresas(id)            ON DELETE CASCADE,
            CONSTRAINT fk_arec_cliente   FOREIGN KEY (cliente_id)   REFERENCES iva_clientes(id)        ON DELETE CASCADE,
            CONSTRAINT fk_arec_actividad FOREIGN KEY (actividad_id) REFERENCES empresa_actividades(id) ON DELETE CASCADE,
            UNIQUE KEY uq_arec (empresa_id, cliente_id),
            INDEX idx_arec_empresa (empresa_id)
        ) {$charset}");
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS actividad_alicuota');
        $pdo->exec('DROP TABLE IF EXISTS actividad_receptor');
    }
};
