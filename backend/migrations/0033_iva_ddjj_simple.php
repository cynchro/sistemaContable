<?php

/**
 * Historial de DDJJ "IVA Simple" (F.2051 del Portal IVA) por período.
 *
 * Hasta ahora el F.2051 se calculaba on-the-fly y los arrastres (saldo técnico a favor
 * anterior y saldo de libre disponibilidad anterior) se pasaban por query. Persistir la
 * DDJJ de cada período permite tomar esos arrastres de la DDJJ del período inmediato
 * anterior, en vez de tipearlos a mano (ver app/Modules/Iva/pendientes.md §H).
 *
 * Una fila por (empresa, período): re-presentar es un upsert (no se versiona; el
 * historial lo da tener una DDJJ por período). Las retenciones/percepciones/pagos
 * sufridos siguen siendo un insumo del período (constancias), no se derivan.
 */
return new class {
    public function up(\PDO $pdo): void
    {
        $charset = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

        $pdo->exec("CREATE TABLE IF NOT EXISTS iva_ddjj_simple (
            id                                  INT AUTO_INCREMENT PRIMARY KEY,
            empresa_id                          INT           NOT NULL,
            periodo_id                          INT           NOT NULL,
            debito_fiscal                       DECIMAL(18,2) NOT NULL DEFAULT 0,
            credito_fiscal                      DECIMAL(18,2) NOT NULL DEFAULT 0,
            saldo_tecnico_anterior              DECIMAL(18,2) NOT NULL DEFAULT 0,
            saldo_tecnico_a_favor_arca          DECIMAL(18,2) NOT NULL DEFAULT 0,
            saldo_tecnico_a_favor_contribuyente DECIMAL(18,2) NOT NULL DEFAULT 0,
            saldo_libre_disponibilidad_anterior DECIMAL(18,2) NOT NULL DEFAULT 0,
            retenciones_percepciones_pagos      DECIMAL(18,2) NOT NULL DEFAULT 0,
            saldo_a_pagar                       DECIMAL(18,2) NOT NULL DEFAULT 0,
            saldo_libre_disponibilidad_periodo  DECIMAL(18,2) NOT NULL DEFAULT 0,
            presentada_at                       TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP
                                                ON UPDATE CURRENT_TIMESTAMP,
            CONSTRAINT fk_ddjjsimple_empresa FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE,
            CONSTRAINT fk_ddjjsimple_periodo FOREIGN KEY (periodo_id) REFERENCES periodos(id) ON DELETE CASCADE,
            UNIQUE KEY uq_ddjjsimple_periodo (empresa_id, periodo_id)
        ) {$charset}");
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS iva_ddjj_simple');
    }
};
