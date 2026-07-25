<?php

return [

    'paths' => [
        'api/*',
        'sanctum/csrf-cookie',
    ],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        'http://localhost:3000',
        'http://127.0.0.1:3000',
        'http://localhost:4173',
        'http://127.0.0.1:4173',
        'http://localhost:5173',
        'http://127.0.0.1:5173',
        'http://localhost:8000',
        'http://127.0.0.1:8000',
        'http://localhost:8080',
        'http://127.0.0.1:8080',
        'http://127.0.0.1:4040',
        'https://gottado.today',
        'https://www.gottado.today',
        'https://f177-122-173-28-222.ngrok-free.app',
        'https://gotta.a1professionals.net',
        'https://gottaweb.a1professionals.net',
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [
        'Cache-Control',
        'Content-Type',
    ],

    'max_age' => 0,

    'supports_credentials' => true,
];