<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $nonce = base64_encode(random_bytes(16));
        $placeholder = self::placeholder();
        $request->attributes->set('csp_nonce', $nonce);

        // views render a placeholder so cached HTML stays valid; the real nonce is swapped in below
        view()->share('cspNonce', $placeholder);
        Vite::useCspNonce($placeholder);

        /** @var Response $response */
        $response = $next($request);

        $this->injectNonce($response, $placeholder, $nonce);

        $secure = $request->isSecure();
        $headers = $response->headers;

        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('X-Frame-Options', 'SAMEORIGIN');
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(), usb=(), accelerometer=(), gyroscope=(), magnetometer=(), browsing-topics=()');
        $headers->set('Cross-Origin-Opener-Policy', 'same-origin');
        $headers->set('X-Permitted-Cross-Domain-Policies', 'none');
        $headers->remove('X-Powered-By');

        if ($secure) {
            $headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains; preload');
        }

        if (config('security.csp.enabled')) {
            $headers->set('Content-Security-Policy', $this->enforcedPolicy($secure, $nonce).'; report-uri /csp-report');
            $headers->set('Content-Security-Policy-Report-Only', $this->monitoredPolicy($secure));
        }

        if ($request->is('api/v1/auth/*', 'login', 'register', 'forgot-password', 'reset-password*', 'confirm-password')) {
            $headers->set('Cache-Control', 'no-store, private');
            $headers->set('Pragma', 'no-cache');
        }

        return $response;
    }

    // secret per deployment, so injected markup cannot pre-claim a valid nonce
    public static function placeholder(): string
    {
        return 'csp-'.substr(hash_hmac('sha256', 'csp-nonce-placeholder', (string) config('app.key')), 0, 32);
    }

    private function injectNonce(Response $response, string $placeholder, string $nonce): void
    {
        if ($response instanceof StreamedResponse || $response instanceof BinaryFileResponse) {
            return;
        }

        $type = (string) $response->headers->get('Content-Type');
        $content = $response->getContent();

        if ($content !== false && $content !== '' && str_contains($type, 'text/html') && str_contains($content, $placeholder)) {
            $response->setContent(str_replace($placeholder, $nonce, $content));
        }
    }

    /**
     * Enforced: nothing but nonced scripts can run (no injected tags, no inline handlers, no javascript: URLs).
     * No 'unsafe-eval': Alpine runs its CSP build (logic in public/js/alpine-components.js).
     * 'unsafe-inline' and https: are CSP2 fallbacks that browsers ignore once a nonce is present.
     */
    private function enforcedPolicy(bool $secure, string $nonce): string
    {
        $directives = [
            "script-src 'nonce-{$nonce}' 'strict-dynamic' 'unsafe-inline' https:",
            "script-src-attr 'none'",
            "worker-src 'self' blob:",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'self'",
        ];

        if ($secure) {
            $directives[] = 'upgrade-insecure-requests';
        }

        return implode('; ', $directives);
    }

    /** Report-only: everything else (images, styles, fonts, connections, frames), to watch before enforcing. */
    private function monitoredPolicy(bool $secure): string
    {
        $connect = $secure ? "'self' https: wss:" : "'self' https: wss: ws:";

        // no default-src: scripts are governed by the enforced policy above
        $directives = [
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdnjs.cloudflare.com",
            "img-src 'self' data: blob: https:",
            "font-src 'self' data: https://fonts.gstatic.com https://cdnjs.cloudflare.com",
            "connect-src {$connect}",
            'frame-src https:',
            "media-src 'self' blob: https:",
            'report-uri /csp-report',
        ];

        return implode('; ', $directives);
    }
}
