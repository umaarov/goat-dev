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
            'https://lh3.googleusercontent.com',
        ],
        'object-src' => [
            'none',
        ],
    ],
];
