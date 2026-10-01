<?php

return [
    'csp' => [
        'enabled' => (bool) env('CSP_ENABLED', true),
    ],

    // hosts the app answers to; app.url host is always included
    'trusted_hosts' => array_values(array_filter(array_map('trim', explode(',', (string) env('TRUSTED_HOSTS', ''))))),

    'password' => [
        'min_length' => (int) env('PASSWORD_MIN_LENGTH', 10),
        'check_breached' => (bool) env('PASSWORD_CHECK_BREACHED', env('APP_ENV') === 'production'),
    ],

    'refresh' => [
        // seconds an already-rotated refresh token is still accepted (flaky networks)
        'grace_seconds' => (int) env('REFRESH_GRACE_SECONDS', 10),
    ],

    'sonar' => [
        'webhook_secret' => env('SONAR_WEBHOOK_SECRET'),
    ],
];
