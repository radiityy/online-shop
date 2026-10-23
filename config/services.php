<?php

return [

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI'),
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'binderbyte' => [
        'key' => env('BINDERBYTE_API_KEY'),
        'base_url' => env('BINDERBYTE_BASE_URL', 'https://api.binderbyte.com/v1'),
    ],

    'biteship' => [
        'key' => env('BITESHIP_API_KEY'),
        'base_url' => env('BITESHIP_BASE_URL', 'https://api.biteship.com'),
        'couriers' => env('BITESHIP_COURIERS', 'jnt,jne,sicepat,anteraja'),
        'preferred_courier' => env('BITESHIP_PREFERRED_COURIER', 'jnt'),
        'insurance' => (bool) env('BITESHIP_INSURANCE', false),
        'timeout' => (int) env('BITESHIP_TIMEOUT', 10),
        'rates_cache_seconds' => (int) env('BITESHIP_RATES_CACHE_SECONDS', 600),
    ],

];
