<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Prepares every request in the API group.
 *
 *  - Forces JSON handling so FormRequest validation failures, authentication
 *    errors and the global exception handler emit JSON (via wantsJson()/
 *    expectsJson()) instead of attempting a web redirect.
 *  - Makes `sanctum` the default guard so that `$request->user()` resolves the
 *    bearer token even on PUBLIC routes (optional auth). This lets per-user
 *    fields such as a post's `user_vote` or a comment's `is_liked_by_current_user`
 *    populate for authenticated callers on otherwise-public endpoints, while
 *    `auth:sanctum` still enforces authentication where it is applied.
 */
class ForceJsonResponse
{
    public function handle(Request $request, Closure $next): Response
    {
        $request->headers->set('Accept', 'application/json');

        Auth::shouldUse('sanctum');

        return $next($request);
    }
}
