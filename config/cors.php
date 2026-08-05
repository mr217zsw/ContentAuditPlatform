<?php

return [

    'paths' => ['api/*', 'api/health', 'api/health/*', 'api/metrics', 'sanctum/csrf-cookie', 'health', 'health/*', 'metrics'],

    'allowed_methods' => ['*'],

    'allowed_origins' => [env('APP_URL', 'http://localhost:5173')],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 86400,

    'supports_credentials' => true,

];
