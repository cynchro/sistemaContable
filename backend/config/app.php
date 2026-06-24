<?php

return [
    'name'            => $_ENV['APP_NAME'] ?? 'MonolitoModular',
    'env'             => $_ENV['APP_ENV'] ?? 'production',
    'debug'           => filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN),
    'url'             => $_ENV['APP_URL'] ?? 'http://localhost',
    'timezone'        => $_ENV['APP_TIMEZONE'] ?? 'UTC',
    // Clave para cifrar secretos en reposo (App\Support\Crypto). Cualquier passphrase.
    'encryption_key'  => $_ENV['APP_ENCRYPTION_KEY'] ?? null,
    // Comma-separated list of proxy IPs allowed to set X-Forwarded-For
    'trusted_proxies'  => array_filter(explode(',', $_ENV['TRUSTED_PROXIES'] ?? '')),
    'impersonate_url'  => $_ENV['IMPERSONALIZE_URL'] ?? '',
    'max_request_size' => (int) ($_ENV['MAX_REQUEST_SIZE'] ?? 2097152), // 2 MB
];
