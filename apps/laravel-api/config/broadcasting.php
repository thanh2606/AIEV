<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Broadcaster
    |--------------------------------------------------------------------------
    |
    | Mặc định driver "log" (an toàn, không đòi WebSocket). Set REVERB_DRIVER=reverb
    | để nối sang Laravel Reverb (config/reverb.php) khi muốn broadcast qua WebSocket.
    | Phần SSE của EventController đi riêng qua Redis Streams nên luôn hoạt động
    | độc lập với driver này.
    |
    */

    'default' => env('REVERB_DRIVER', 'log'),

    'connections' => [

        'log' => [
            'driver' => 'log',
        ],

        'null' => [
            'driver' => 'null',
        ],

        'pusher' => [
            'driver' => 'pusher',
            'key' => env('PUSHER_APP_KEY'),
            'secret' => env('PUSHER_APP_SECRET'),
            'app_id' => env('PUSHER_APP_ID'),
            'options' => [
                'cluster' => env('PUSHER_APP_CLUSTER'),
                'host' => env('PUSHER_HOST', 'api-'.env('PUSHER_APP_CLUSTER', 'mt1').'pusher.com'),
                'port' => env('PUSHER_PORT', 443),
                'scheme' => env('PUSHER_SCHEME', 'https'),
                'encrypted' => true,
            ],
        ],

        'reverb' => [
            'driver' => 'reverb',
        ],

        'ably' => [
            'driver' => 'ably',
            'key' => env('ABLY_KEY'),
        ],

        'laravel_pulse' => [
            'driver' => 'pulse',
            'path' => env('PULSE_PATH', '/laravel/pulse'),
            'placeholders' => [],
            'context' => [],
            'variables' => [],
        ],

        'pulse_user' => [
            'driver' => 'pulse-user',
            'path' => 'pulse-user',
            'placeholders' => [],
            'context' => [],
            'variables' => [],
        ],

    ],

];
