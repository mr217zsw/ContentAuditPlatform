<?php

return [

    'apps' => [
        [
            'id' => env('REVERB_APP_ID'),
            'key' => env('REVERB_APP_KEY'),
            'secret' => env('REVERB_APP_SECRET'),
            'max_connections' => 100,
            'enable_client_messages' => false,
            'enable_statistics' => true,
        ],
    ],

    'scaling' => [
        'enabled' => true,
        'channel' => 'reverb',
        'server' => [
            'host' => env('REVERB_HOST', '0.0.0.0'),
            'port' => env('REVERB_PORT', 8080),
        ],
    ],

    'prune_server_connections' => 60 * 60 * 24,

];
