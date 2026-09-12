<?php

return [
    'default' => 'reverb',
    'servers' => [
        'reverb' => [
            'host' => env('REVERB_SERVER_HOST', '0.0.0.0'),
            'port' => env('REVERB_SERVER_PORT', 8080),
            'path' => env('REVERB_SERVER_PATH', ''),
            'hostname' => env('REVERB_HOST'),
            'options' => ['tls' => []],
            'max_request_size' => 10000,
            'scaling' => [
                'enabled' => false,
                'channel' => 'reverb',
                'server' => ['url' => env('REDIS_URL'), 'host' => env('REDIS_HOST', '127.0.0.1'),
                    'port' => env('REDIS_PORT', '6379'), 'username' => env('REDIS_USERNAME'),
                    'password' => env('REDIS_PASSWORD'), 'database' => env('REDIS_DB', '0'), 'timeout' => 60],
            ],
            'pulse_ingest_interval' => 15,
            'telescope_ingest_interval' => 15,
        ],
    ],
    'apps' => [
        'provider' => 'config',
        'apps' => [[
            'key' => env('REVERB_APP_KEY'),
            'secret' => env('REVERB_APP_SECRET'),
            'app_id' => env('REVERB_APP_ID'),
            'options' => ['host' => env('REVERB_HOST'), 'port' => env('REVERB_PORT', 8080),
                'scheme' => env('REVERB_SCHEME', 'http'), 'useTLS' => env('REVERB_SCHEME', 'http') === 'https'],
            'allowed_origins' => array_filter(array_map('trim', explode(',', env('REVERB_ALLOWED_ORIGINS', 'localhost,127.0.0.1')))),
            'ping_interval' => 60,
            'activity_timeout' => 30,
            'max_connections' => null,
            'max_message_size' => 10000,
            'accept_client_events_from' => 'none',
        ]],
    ],
];
