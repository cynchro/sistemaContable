<?php

return [
    'driver'   => $_ENV['DB_DRIVER'] ?? 'mysql',
    'host'     => $_ENV['DB_HOST'] ?? '127.0.0.1',
    'port'     => (int) ($_ENV['DB_PORT'] ?? 3306),
    'database' => $_ENV['DB_NAME'] ?? '',
    'username' => $_ENV['DB_USER'] ?? '',
    'password' => $_ENV['DB_PASS'] ?? '',
    'charset'  => 'utf8mb4',
    'options'  => [
        \PDO::ATTR_ERRMODE    => \PDO::ERRMODE_EXCEPTION,
        // Conexiones persistentes: reutilizan la conexión entre requests del mismo
        // worker (FPM/mod_php) y evitan el handshake TCP/auth por request — una mejora
        // de latencia real. Opt-in vía DB_PERSISTENT=true. Por defecto false: con muchos
        // workers cada uno retiene una conexión, así que ajustá `max_connections` del
        // servidor antes de activarla.
        \PDO::ATTR_PERSISTENT => filter_var($_ENV['DB_PERSISTENT'] ?? false, FILTER_VALIDATE_BOOLEAN),
    ],
];
