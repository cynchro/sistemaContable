<?php

/**
 * Botón "Liquidar IVA" (plan del 25/08/2026): cola de pedidos de automatización contra el
 * Portal IVA de ARCA, consumida por el worker del bot externo (`cositasVarias/extractor/`, fuera
 * de este repo) vía los endpoints `/iva/liquidaciones/*`. El usuario crea el pedido desde la UI
 * de `ecosistema`; el worker lo toma, corre el flujo ya probado en vivo (traer/subir) y reporta
 * el resultado acá — nunca al revés (el worker sigue siendo cliente HTTP de `ecosistema`, nunca
 * lo contrario).
 */
return new class {
    public function up(\PDO $pdo): void
    {
        $charset = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

        $pdo->exec("CREATE TABLE IF NOT EXISTS iva_liquidaciones (
            id           INT AUTO_INCREMENT PRIMARY KEY,
            empresa_id   INT          NOT NULL,
            periodo_id   INT          NOT NULL,
            direccion    VARCHAR(10)  NOT NULL, -- traer | subir | ambos
            libro        VARCHAR(10)  NOT NULL, -- ventas | compras | ambos
            periodo_arca VARCHAR(7)   NOT NULL, -- MM/YYYY, formato que espera el bot
            estado       VARCHAR(20)  NOT NULL DEFAULT 'pendiente',
            resultado    TEXT         NULL,      -- JSON: detalle por libro (agregados/errores)
            creado_por   INT          NOT NULL,
            tomada_en    DATETIME     NULL,
            terminada_en DATETIME     NULL,
            created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            CONSTRAINT fk_ivaliq_empresa FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE,
            CONSTRAINT fk_ivaliq_periodo FOREIGN KEY (periodo_id) REFERENCES periodos(id) ON DELETE CASCADE,
            CONSTRAINT fk_ivaliq_usuario FOREIGN KEY (creado_por) REFERENCES usuarios(id) ON DELETE CASCADE,
            INDEX idx_ivaliq_estado (estado, created_at),
            INDEX idx_ivaliq_empresa_periodo (empresa_id, periodo_id)
        ) {$charset}");
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS iva_liquidaciones');
    }
};
