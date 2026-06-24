<?php

return [
    'default'  => $_ENV['LOG_CHANNEL'] ?? 'file',
    'level'    => $_ENV['LOG_LEVEL'] ?? 'debug',
    'channels' => [
        'file' => [
            'driver' => 'file',
            'path'   => dirname(__DIR__) . '/storage/logs/app.log',
        ],
        'stderr' => [
            'driver' => 'stderr',
        ],
    ],
];
