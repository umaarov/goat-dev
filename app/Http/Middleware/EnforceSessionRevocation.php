<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

// "log out other devices" for any session driver: a session stamped before the user's cutoff is dead
class EnforceSessionRevocation
{
    public const KEY = 'auth_issued_at';

    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if ($user && $request->hasSession()) {
            $issued = $request->session()->get(self::KEY);
            $cutoff = (int) $user->sessions_invalid_before;

            // sessions are stamped at login; an unstamped one predates this feature, so it cannot
            // prove it was issued after a revocation
            if ($issued === null && $cutoff === 0) {
                $request->session()->put(self::KEY, time());
            } elseif ($cutoff > 0 && (int) $issued < $cutoff) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return $request->expectsJson()
                    ? response()->json(['message' => 'Session expired.'], 401)
                    : redirect()->route('login');
            }
        }

        return $next($request);
    }
}
