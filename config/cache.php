<?php

return [
    'default' => env('CACHE_DRIVER', 'file'),
    'prefix' => env('CACHE_PREFIX', 'kailyn_'),
    'stores' => [
        'file' => [
            'driver' => 'file',
            'path' => storage_path('cache'),
        ],
        'redis' => [
            'driver' => 'redis',
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'port' => env('REDIS_PORT', 6379),
            'password' => env('REDIS_PASSWORD'),
            'database' => env('REDIS_DB', 0),
            'timeout' => 0.0,
        ],
        'database' => [
            'driver' => 'database',
            'connection' => env('DB_CONNECTION', 'sqlite'),
            'table' => 'cache',
        ],
    ],
];
