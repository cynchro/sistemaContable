<?php

/**
 * Módulo Fiscal — requerimientos (de sistemaCuarto): notificaciones/pedidos formales
 * de un organismo (ente) al contribuyente, con plazos y estado de presentación.
 * Ingeniería inversa de `requerimientos` del legacy. Por empresa (contribuyente).
 */
return new class {
    public function up(\PDO $pdo): void
    {
        $cs = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

        $pdo->exec("CREATE TABLE IF NOT EXISTS requerimientos (
            id                 INT AUTO_INCREMENT PRIMARY KEY,
            empresa_id         INT          NOT NULL,
            ente               VARCHAR(60)  DEFAULT NULL COMMENT 'organismo: AFIP/ARCA, ARBA, ...',
            formulario         VARCHAR(60)  DEFAULT NULL,
            nro                VARCHAR(60)  DEFAULT NULL,
            oi_caso_nro        VARCHAR(60)  DEFAULT NULL,
            requerimiento      TEXT         DEFAULT NULL,
            agente             VARCHAR(100) DEFAULT NULL,
            fecha_notificacion DATE         DEFAULT NULL,
            plazo_dias         INT          DEFAULT NULL,
            fecha_prorroga     DATE         DEFAULT NULL,
            fecha_cumplimiento DATE         DEFAULT NULL,
            fecha_presentacion DATE         DEFAULT NULL,
            estado             VARCHAR(30)  NOT NULL DEFAULT 'notificado',
            observaciones      TEXT         DEFAULT NULL,
            CONSTRAINT fk_req_empresa FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE,
            INDEX idx_req_empresa (empresa_id),
            INDEX idx_req_estado (estado)
        ) {$cs}");
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS requerimientos');
    }
};
