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
        'order_timeout' => (int) env('BITESHIP_ORDER_TIMEOUT', 30),
        'webhook_header' => env('BITESHIP_WEBHOOK_HEADER', 'X-Biteship-Secret'),
        'webhook_secret' => env('BITESHIP_WEBHOOK_SECRET'),
    ],

    'midtrans' => [
        'server_key' => env('MIDTRANS_SERVER_KEY'),
        'client_key' => env('MIDTRANS_CLIENT_KEY'),
        'is_production' => (bool) env('MIDTRANS_IS_PRODUCTION', false),
        'sandbox_url' => 'https://api.sandbox.midtrans.com',
        'production_url' => 'https://api.midtrans.com',
        'timeout' => (int) env('MIDTRANS_TIMEOUT', 15),
        'expiry_minutes' => (int) env('MIDTRANS_EXPIRY_MINUTES', 60),
        'auto_refund' => (bool) env('MIDTRANS_AUTO_REFUND', false),
        'methods' => [
            'qris' => [
                'label' => 'QRIS',
                'payment_type' => 'qris',
                'bank' => null,
                'fee_flat' => 0,
                'fee_percent' => 0.7,
                'enabled' => true,
            ],
            'gopay' => [
                'label' => 'GoPay',
                'payment_type' => 'gopay',
                'bank' => null,
                'fee_flat' => 0,
                'fee_percent' => 2,
                'enabled' => true,
            ],
            'bca_va' => [
                'label' => 'BCA Virtual Account',
                'payment_type' => 'bank_transfer',
                'bank' => 'bca',
                'fee_flat' => 4000,
                'fee_percent' => 0,
                'enabled' => true,
            ],
            'bni_va' => [
                'label' => 'BNI Virtual Account',
                'payment_type' => 'bank_transfer',
                'bank' => 'bni',
                'fee_flat' => 4000,
                'fee_percent' => 0,
                'enabled' => true,
            ],
            'bri_va' => [
                'label' => 'BRI Virtual Account',
                'payment_type' => 'bank_transfer',
                'bank' => 'bri',
                'fee_flat' => 4000,
                'fee_percent' => 0,
                'enabled' => true,
            ],
            'permata_va' => [
                'label' => 'Permata Virtual Account',
                'payment_type' => 'permata',
                'bank' => 'permata',
                'fee_flat' => 4000,
                'fee_percent' => 0,
                'enabled' => true,
            ],
        ],
    ],

];