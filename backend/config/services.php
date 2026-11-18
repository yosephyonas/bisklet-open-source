<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

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

    'mapbox' => [
        'access_token' => env('MAPBOX_ACCESS_TOKEN'),
        'default_style' => env('MAPBOX_DEFAULT_STYLE', 'mapbox://styles/mapbox/outdoors-v12'),
        'navigation_profile' => env('MAPBOX_NAVIGATION_PROFILE', 'cycling'),
        'snap_to_road' => (bool) env('MAPBOX_SNAP_TO_ROAD', true),
    ],

    'go_gateway' => [
        'base_url' => env('GO_GATEWAY_BASE_URL', 'http://127.0.0.1:9001'),
        'command_token' => env('GO_GATEWAY_COMMAND_TOKEN'),
        'webhook_token' => env('GO_GATEWAY_WEBHOOK_TOKEN'),
        'track_interval_ride' => env('GO_GATEWAY_TRACK_INTERVAL_RIDE', 120),
        'track_interval_missing_phone' => env('GO_GATEWAY_TRACK_INTERVAL_MISSING_PHONE', 25),
        'track_missing_phone_after' => env('GO_GATEWAY_TRACK_MISSING_PHONE_AFTER', 60),
        'track_interval_parked' => env('GO_GATEWAY_TRACK_INTERVAL_PARKED', 600),
        'track_interval_idle' => env('GO_GATEWAY_TRACK_INTERVAL_IDLE', 1800),
        'track_idle_after' => env('GO_GATEWAY_TRACK_IDLE_AFTER', 1800),
        'track_interval_alarm' => env('GO_GATEWAY_TRACK_INTERVAL_ALARM', 20),
        'track_alarm_boost_seconds' => env('GO_GATEWAY_TRACK_ALARM_BOOST_SECONDS', 120),
        'track_command_min_interval' => env('GO_GATEWAY_TRACK_COMMAND_MIN_INTERVAL', 30),
    ],

    'wallet' => [
        'low_balance_threshold' => env('WALLET_LOW_BALANCE_THRESHOLD', 50),
    ],

    'payments' => [
        'webhook_secret' => env('PAYMENT_WEBHOOK_SECRET'),
        'providers' => [
            'chapa' => [
                'webhook_secret' => env('CHAPA_WEBHOOK_SECRET'),
            ],
            'telebirr' => [
                'webhook_secret' => env('TELEBIRR_WEBHOOK_SECRET'),
            ],
        ],
    ],

    'rate_limits' => [
        'api_per_minute' => env('API_RATE_LIMIT_PER_MINUTE', 120),
        'auth_per_minute' => env('AUTH_RATE_LIMIT_PER_MINUTE', 10),
        'lock_commands_per_minute' => env('LOCK_COMMAND_RATE_LIMIT_PER_MINUTE', 20),
        'gateway_webhooks_per_minute' => env('GATEWAY_WEBHOOK_RATE_LIMIT_PER_MINUTE', 600),
        'payment_webhooks_per_minute' => env('PAYMENT_WEBHOOK_RATE_LIMIT_PER_MINUTE', 120),
    ],

];
