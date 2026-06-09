<?php

return [
    'default' => 'sqlite',
    'connections' => [
        'mysql' => [
            'driver' => 'mysql',
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', 3306),
            'database' => env('DB_DATABASE', 'kailyn'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => 'utf8mb4',
        ],
        'sqlite' => [
            'driver' => 'sqlite',
            'database' => __DIR__ . '/../database/kailyn.sqlite',
        ],
    ],
];
