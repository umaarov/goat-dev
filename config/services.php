<?php

return [
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
    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI'),
        'guzzle' => [
            'connect_timeout' => 10,
            'timeout' => 15,
        ],
    ],

    'telegram' => [
        'bot_token' => env('TELEGRAM_BOT_TOKEN'),
        'bot_username' => env('TELEGRAM_BOT_USERNAME'),
        'chat_id' => env('TELEGRAM_CHAT_ID'),
    ],

    'x' => [
        'api_key' => env('X_API_KEY'),
        'api_secret_key' => env('X_API_SECRET_KEY'),
        'access_token' => env('X_ACCESS_TOKEN'),
        'access_token_secret' => env('X_ACCESS_TOKEN_SECRET'),
        'client_id' => env('X_CLIENT_ID'),
        'client_secret' => env('X_CLIENT_SECRET'),
        'redirect' => env('APP_URL') . '/auth/x/callback',
    ],

    'github' => [
        'client_id' => env('GITHUB_CLIENT_ID'),
        'client_secret' => env('GITHUB_CLIENT_SECRET'),
        'redirect' => env('GITHUB_REDIRECT_URI'),
    ],

    'instagram' => [
        'business_account_id' => env('INSTAGRAM_BUSINESS_ACCOUNT_ID'),
        'access_token' => env('INSTAGRAM_ACCESS_TOKEN'),
        'graph_api_version' => 'v23.0',
        'graph_url' => 'https://graph.facebook.com',
    ],

    'groq' => [
        'api_key' => env('GROQ_API_KEY'),
        'model' => env('GROQ_MODEL'),
        'prompts' => [
            'text' => env('GROQ_PROMPT_TEXT'),
            'image' => env('GROQ_PROMPT_IMAGE'),
            'url' => env('GROQ_PROMPT_URL'),
        ],
    ],
];
