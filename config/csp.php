<?php

use App\Csp\CustomPolicy;

return [

    'policy' => CustomPolicy::class,

    'report_only_policy' => '',

    'report_uri' => env('CSP_REPORT_URI'),

    'enabled' => env('CSP_ENABLED', true),

    'report_only_header' => 'Content-Security-Policy-Report-Only',

    'policy_header' => 'Content-Security-Policy',
];
