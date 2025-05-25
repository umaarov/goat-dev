<?php

namespace App\Http\Middleware;

use Closure;

class SecurityHeaders
{
    public function handle($request, Closure $next)
    {
        $response = $next($request);
        $response->header('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        $response->header('X-Frame-Options', 'SAMEORIGIN');
        $response->header('X-Content-Type-Options', 'nosniff');
        $response->header('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->header('Permissions-Policy', 'geolocation=(), microphone=(), camera=()');
        return $response;
    }
}
