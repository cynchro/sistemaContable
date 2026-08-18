<?php

/**
 * Dos features pedidas por el cliente (WhatsApp con Juan Pablo Haddad, 11/08/2026, sobre
 * concurrencia y permisos por contribuyente):
 *
 * 1. `empresa_locks` — "si uno está trabajando en el CUIT... no puede entrar otro al mismo...
 *    dejar que uno con clave de administrador pueda entrar pero no modificar, es modo
 *    observador." Un solo usuario "ocupa" una empresa a la vez (se ocupa al elegirla como activa
 *    en el header); mientras está ocupada, otro usuario NO-admin queda bloqueado (ni lectura),
 *    y un admin entra en modo observador (lectura sí, escritura no). Sin fila = libre. El
 *    `ultimo_ping` sostiene un heartbeat desde el frontend — una fila vieja (nadie hizo ping en
 *    el timeout) se trata como libre sin necesidad de un job de limpieza.
 *
 * 2. `usuario_empresas` — "me gustaría que de última él pueda ver sus empresas asignadas."
 *    Asignación opcional (no obligatoria): si un usuario tiene al menos una fila acá, el listado
 *    de empresas se filtra a esas; si no tiene ninguna, sigue viendo todas (no se fuerza la
 *    restricción sin que alguien la configure a propósito — el cliente pidió dejar la
 *    restricción dura de permisos como pendiente, esto es solo el filtro de visibilidad).
 */
return new class {
    public function up(\PDO $pdo): void
    {
        $charset = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

        $pdo->exec("CREATE TABLE IF NOT EXISTS empresa_locks (
            empresa_id  INT      NOT NULL PRIMARY KEY,
            usuario_id  INT      NOT NULL,
            desde       DATETIME NOT NULL,
            ultimo_ping DATETIME NOT NULL,
            CONSTRAINT fk_emplock_empresa FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE,
            CONSTRAINT fk_emplock_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
        ) {$charset}");

        $pdo->exec("CREATE TABLE IF NOT EXISTS usuario_empresas (
            usuario_id INT NOT NULL,
            empresa_id INT NOT NULL,
            PRIMARY KEY (usuario_id, empresa_id),
            CONSTRAINT fk_usuemp_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
            CONSTRAINT fk_usuemp_empresa FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE
        ) {$charset}");
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS usuario_empresas');
        $pdo->exec('DROP TABLE IF EXISTS empresa_locks');
    }
};
