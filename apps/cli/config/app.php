<?php

return [
    'id' => 'cli',
    'env' => env('APP_ENV', 'prod'),
    'debug' => env('APP_DEBUG', false),
    'version' => '1.1.1',
    'timezone' => 'PRC',
    'master_key' => env('MASTER_KEY'),
    'params' => [],
    'aliases' => [
    ],
    'components' => [
        'db' => env('DB_URL'),
        'redis' => env('REDIS_URL'),
        'logger' => ['level' => env('LOGGER_LEVEL', 'info')]
    ],
    'services' => [],
    'plugins' => [
        'tracer',
    ]
];