<?php

return [
    'policy' => [
        'default-src' => [
            'self',
        ],
        'script-src' => [
            'self',
            'https://code.jquery.com',
            'https://cdn.jsdelivr.net',
            'https://cdnjs.cloudflare.com',
            'https://static.cloudflareinsights.com',
            'https://pagead2.googlesyndication.com',
            'https://fundingchoicesmessages.google.com',
            'https://www.google.com',
            'https://www.googletagservices.com',
            'https://adservice.google.com',
            "'unsafe-inline'",
            "'unsafe-eval'",
        ],
        'style-src' => [
            'self',
            'https://fonts.googleapis.com',
            'https://cdnjs.cloudflare.com',
            "'unsafe-inline'",
        ],
        'font-src' => [
            'self',
            'https://fonts.gstatic.com',
        ],
        'img-src' => [
            'self',
            'data:',
            'https:',
        ],
        'object-src' => [
            'none',
        ],
        'connect-src' => [
            'self',
            'https://stats.g.doubleclick.net',
            'https://pagead2.googlesyndication.com',
        ],
        'base-uri' => [
            'self',
        ],
        'form-action' => [
            'self',
        ],
        'frame-src' => [
            'self',
            'https://googleads.g.doubleclick.net',
            'https://tpc.googlesyndication.com',
            'https://fundingchoicesmessages.google.com'
        ],
    ],

    'report_uri' => null,

    'enabled' => env('CSP_ENABLED', true),

    'policy_header' => 'Content-Security-Policy',

    'nonce_generator' => '',

    'add_nonce_to' => [],
];
