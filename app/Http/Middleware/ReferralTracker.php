<?php

namespace App\Http\Middleware;

use App\Models\ReferralClick;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ReferralTracker
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->has('ref')) {
            $referrer = $request->input('ref');

            // Basic sanitization: allow only alphanumeric, dots, hyphens, underscores
            $referrer = preg_replace('/[^a-zA-Z0-9._-]/', '', $referrer);

            if (!empty($referrer)) {
                ReferralClick::create([
                    'referrer'   => $referrer,
                    'url'        => $request->fullUrl(),
                    'ip'         => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]);
            }
        }

        return $next($request);
    }
}
