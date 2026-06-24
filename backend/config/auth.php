<?php

return [
    'jwt_secret'      => $_ENV['JWT_SECRET'] ?? null,
    'jwt_algo'        => $_ENV['JWT_ALGO'] ?? 'HS256',
    'jwt_ttl'         => (int) ($_ENV['JWT_TTL'] ?? 86400),
    'jwt_refresh_ttl' => (int) ($_ENV['JWT_REFRESH_TTL'] ?? 2592000),
    'jwt_issuer'      => $_ENV['APP_URL'] ?? 'monolito-modular',
];
