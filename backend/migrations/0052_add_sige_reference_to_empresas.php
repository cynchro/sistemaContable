<?php

/**
 * Trazabilidad de origen SIGE en `empresas`.
 *
 * Cuando el alta de empresa se autocompleta desde el SIGE (sistemaCuarto, vía
 * GET /sige/{cuit}/sugerencia), se guarda el `persona_id` de origen y cuándo se
 * sincronizó. No es indispensable para el autocompletar en sí (que es un fetch
 * puntual sin persistir vínculo), pero es la única forma barata de saber después
 * "esta empresa vino del SIGE" para auditoría/soporte, y la base para una futura
 * re-sincronización idempotente sin matchear a ciegas por CUIT.
 */
return new class {
    public function up(\PDO $pdo): void
    {
        $pdo->exec('ALTER TABLE empresas ADD COLUMN sige_persona_id INT DEFAULT NULL AFTER contabilidad');
        $pdo->exec('ALTER TABLE empresas ADD COLUMN sige_synced_at DATETIME DEFAULT NULL AFTER sige_persona_id');
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('ALTER TABLE empresas DROP COLUMN sige_synced_at');
        $pdo->exec('ALTER TABLE empresas DROP COLUMN sige_persona_id');
    }
};
