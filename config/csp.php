<?php

use App\Csp\CustomPolicy;

return [
    'policy' => CustomPolicy::class,

    'add_nonce_to' => [],

    'report_only_policy' => '',
    'report_uri' => env('CSP_REPORT_URI'),
    'enabled' => env('CSP_ENABLED', true),
    'policy_header' => 'Content-Security-Policy',
    'report_only_header' => 'Content-Security-Policy-Report-Only',
];
