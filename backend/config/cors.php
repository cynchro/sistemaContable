<?php

return [
    'allowed_origins'    => isset($_ENV['CORS_ALLOWED_ORIGINS'])
        ? explode(',', $_ENV['CORS_ALLOWED_ORIGINS'])
        : [],
    'allowed_methods'    => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
    'allowed_headers'    => ['Content-Type', 'Authorization', 'X-Requested-With'],
    'expose_headers'     => [],
    'max_age'            => 0,
    'allow_credentials'  => filter_var($_ENV['CORS_ALLOW_CREDENTIALS'] ?? false, FILTER_VALIDATE_BOOLEAN),
];
