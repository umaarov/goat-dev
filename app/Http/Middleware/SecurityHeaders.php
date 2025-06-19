<?php

namespace App\Http\Middleware;

use Closure;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SecurityHeaders
{
    public function handle($request, Closure $next)
    {
        $response = $next($request);

        if ($response instanceof StreamedResponse) {
            return $response;
        }

        $response->header('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        $response->header('X-Frame-Options', 'SAMEORIGIN');
        $response->header('X-Content-Type-Options', 'nosniff');
        $response->header('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->header('Permissions-Policy', 'geolocation=(), microphone=(), camera=()');

        $csp = "default-src 'self'; " .
            "img-src 'self' data: https: blob:; " .
            "script-src 'self' https://code.jquery.com https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://static.cloudflareinsights.com https://pagead2.googlesyndication.com https://fundingchoicesmessages.google.com https://www.google.com https://www.googletagservices.com https://adservice.google.com 'unsafe-inline' 'unsafe-eval'; " .
            "style-src 'self' https://fonts.googleapis.com https://cdnjs.cloudflare.com 'unsafe-inline'; " .
            "font-src 'self' https://fonts.gstatic.com; " .
            "frame-src 'self' https://googleads.g.doubleclick.net https://tpc.googlesyndication.com https://fundingchoicesmessages.google.com https://ep2.adtrafficquality.google https://www.google.com; " .
            "connect-src 'self' https://stats.g.doubleclick.net https://pagead2.googlesyndication.com https://ep2.adtrafficquality.google; " .
            "object-src 'none'; " .
            "base-uri 'self'; " .
            "form-action 'self'";

        $response->header('Content-Security-Policy', $csp);

        return $response;
    }
}
