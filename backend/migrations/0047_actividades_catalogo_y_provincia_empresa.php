<?php

/**
 * Catálogo global de actividades (AFIP/NAES) + provincia de la empresa.
 *
 * El modal legacy (Visual IVA) elige la actividad principal/secundaria y la provincia
 * de la empresa desde desplegables. Acá:
 *  - Se crea el catálogo `actividades` (id = ID_ACTIVIDAD legacy, código NAES/AFIP,
 *    nombre = descripción). Lo siembra `seeders/ActividadesSeeder.php` con los ~3500
 *    registros reales del legacy. Es global y de solo lectura (como el resto de catálogos).
 *  - `empresas.actividad1_id`/`actividad2_id` referencian este catálogo (ya existían como
 *    INT); se agrega `provincia_id` (FK a `provincias`).
 *  - Se quitan `actividad1_desc`/`actividad2_desc` (0046): la descripción sale del catálogo.
 */
return new class {
    public function up(\PDO $pdo): void
    {
        $charset = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

        $pdo->exec("CREATE TABLE IF NOT EXISTS actividades (
            id                INT PRIMARY KEY,
            codigo            VARCHAR(15)  DEFAULT NULL,
            nombre            VARCHAR(255) NOT NULL,
            descripcion_larga TEXT         DEFAULT NULL,
            INDEX idx_actividades_codigo (codigo)
        ) {$charset}");

        $pdo->exec('ALTER TABLE empresas ADD COLUMN provincia_id INT DEFAULT NULL AFTER localidad');
        $pdo->exec(
            'ALTER TABLE empresas ADD CONSTRAINT fk_empresas_provincia '
            . 'FOREIGN KEY (provincia_id) REFERENCES provincias(id)'
        );

        $pdo->exec('ALTER TABLE empresas DROP COLUMN actividad1_desc');
        $pdo->exec('ALTER TABLE empresas DROP COLUMN actividad2_desc');

        $this->sembrarActividades($pdo);
    }

    /**
     * Carga el catálogo de actividades desde el archivo de datos del legacy, si está vacío.
     * Se hace en la migración (no sólo en el seeder) para que el deploy automático —que corre
     * `migrate` pero no los seeders— tenga el catálogo poblado. Idempotente.
     */
    private function sembrarActividades(\PDO $pdo): void
    {
        if ((int) $pdo->query('SELECT COUNT(*) FROM actividades')->fetchColumn() > 0) {
            return;
        }

        $file = dirname(__DIR__) . '/seeders/data/actividades.sql';
        $fh = @fopen($file, 'r');
        if ($fh === false) {
            return; // sin el archivo, el catálogo se puede sembrar luego con ActividadesSeeder.
        }

        $pdo->exec("SET SESSION sql_mode='NO_AUTO_VALUE_ON_ZERO'");
        $buffer = '';
        while (($linea = fgets($fh)) !== false) {
            $buffer .= $linea;
            if (preg_match('/;\s*$/', $linea)) {
                $pdo->exec($buffer);
                $buffer = '';
            }
        }
        fclose($fh);
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('ALTER TABLE empresas ADD COLUMN actividad2_desc VARCHAR(255) DEFAULT NULL');
        $pdo->exec('ALTER TABLE empresas ADD COLUMN actividad1_desc VARCHAR(255) DEFAULT NULL');
        $pdo->exec('ALTER TABLE empresas DROP FOREIGN KEY fk_empresas_provincia');
        $pdo->exec('ALTER TABLE empresas DROP COLUMN provincia_id');
        $pdo->exec('DROP TABLE IF EXISTS actividades');
    }
};
