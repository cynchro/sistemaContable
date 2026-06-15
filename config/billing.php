<?php

// Configuración del módulo opcional de billing (app/Modules/Billing).
// Solo se usa si está instalado cynchro/modux-billing + un adaptador de pasarela.

return [
    'default' => $_ENV['BILLING_GATEWAY'] ?? 'stripe',

    'gateways' => [
        'stripe' => [
            'api_key'        => $_ENV['STRIPE_API_KEY'] ?? '',
            'webhook_secret' => $_ENV['STRIPE_WEBHOOK_SECRET'] ?? '',
            'success_url'    => $_ENV['STRIPE_SUCCESS_URL'] ?? '',
            'cancel_url'     => $_ENV['STRIPE_CANCEL_URL'] ?? '',
        ],
        'mercadopago' => [
            'access_token'   => $_ENV['MP_ACCESS_TOKEN'] ?? '',
            'webhook_secret' => $_ENV['MP_WEBHOOK_SECRET'] ?? '',
            'back_url'       => $_ENV['MP_BACK_URL'] ?? '',
        ],
    ],
];
