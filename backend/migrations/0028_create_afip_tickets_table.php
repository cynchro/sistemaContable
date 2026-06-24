<?php

/**
 * Cache de Tickets de Acceso (TA) del WSAA de AFIP.
 *
 * El TA (token + sign) vale ~12h y se reutiliza en todas las llamadas a los WS de
 * negocio durante ese lapso. Se cachea en DB (no en memoria) para que sobreviva
 * entre requests/procesos. Clave única por (cuit, service): un TA por servicio AFIP
 * (p. ej. 'wsfe') y CUIT emisor.
 */
return new class {
    public function up(\PDO $pdo): void
    {
        $pdo->exec("CREATE TABLE IF NOT EXISTS afip_tickets (
            id              INT AUTO_INCREMENT PRIMARY KEY,
            cuit            VARCHAR(13)  NOT NULL,
            service         VARCHAR(40)  NOT NULL,
            token           TEXT         NOT NULL,
            sign            TEXT         NOT NULL,
            generation_time DATETIME     NOT NULL,
            expiration_time DATETIME     NOT NULL,
            UNIQUE KEY uq_afip_ticket (cuit, service)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS afip_tickets');
    }
};
