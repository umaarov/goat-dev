<?php

namespace App\Http\Middleware;

use App\Models\ReferralClick;
use Closure;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ReferralTracker
{
    public function handle(Request $request, Closure $next): Response
    {
        // only a plain ?ref=value counts; ?ref[]=x and friends are ignored, not a crash
        if (is_string($request->query('ref'))) {
            $referrer = $request->query('ref');

            // Basic sanitization: allow only alphanumeric, dots, hyphens, underscores
            $referrer = preg_replace('/[^a-zA-Z0-9._-]/', '', $referrer);

            if (!empty($referrer) && RateLimiter::attempt('ref-click:'.$request->ip(), 20, fn () => true, 60)) {
                ReferralClick::create([
                    'referrer'   => substr($referrer, 0, 64),
                    // column sizes: url 1024, user_agent 500
                    'url'        => Str::limit($request->fullUrl(), 1000, ''),
                    'ip'         => $request->ip(),
                    'user_agent' => Str::limit((string) $request->userAgent(), 500, '') ?: null,
                ]);
            }

            // Build the redirect URL without the 'ref' parameter
            $query = $request->query();
            unset($query['ref']);

            $redirectUrl = $request->url();
            if (!empty($query)) {
                $redirectUrl .= '?' . http_build_query($query);
            }

            return redirect($redirectUrl, 302);
        }

        return $next($request);
    }
}
