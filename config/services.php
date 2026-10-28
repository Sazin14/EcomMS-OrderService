<?php

return [

    'postmark' => ['key' => env('POSTMARK_API_KEY')],

    'resend' => ['key' => env('RESEND_API_KEY')],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // Services THIS service calls. "internal_key" is a copy of the key the CALLED service expects.
    'inventory' => [
        'url' => env('INVENTORY_SERVICE_URL', 'http://127.0.0.1:8002'),
        'internal_key' => env('INVENTORY_INTERNAL_KEY'),
    ],

    'product' => [
        'url' => env('PRODUCT_SERVICE_URL', 'http://127.0.0.1:8001'),
        'internal_key' => env('PRODUCT_INTERNAL_KEY'),
    ],

    // The key OTHER services must send to THIS service (only used by routes with the "internal" middleware).
    'internal_key' => env('INTERNAL_SERVICE_KEY'),

];
