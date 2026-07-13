<?php

/**
 * Descripción de las actividades principal y secundaria de la empresa.
 *
 * `empresas.actividad1_id`/`actividad2_id` guardan solo el código (NAES) de la actividad,
 * sin texto. Para poder mostrar y editar la actividad de forma legible (y precargarla del
 * padrón de ARCA, que devuelve código + descripción) se agregan las columnas de texto.
 */
return new class {
    public function up(\PDO $pdo): void
    {
        $pdo->exec('ALTER TABLE empresas ADD COLUMN actividad1_desc VARCHAR(255) DEFAULT NULL AFTER actividad1_id');
        $pdo->exec('ALTER TABLE empresas ADD COLUMN actividad2_desc VARCHAR(255) DEFAULT NULL AFTER actividad2_id');
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('ALTER TABLE empresas DROP COLUMN actividad2_desc');
        $pdo->exec('ALTER TABLE empresas DROP COLUMN actividad1_desc');
    }
};
