<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UpdateLastActiveTimestamp
{
    public function handle(Request $request, Closure $next)
    {
        if (Auth::check()) {
            $user = Auth::user();
            if ($user->last_active_at === null || $user->last_active_at->diffInMinutes(now()) > 5) {
                $user->last_active_at = now();
                $user->save();
            }
        }
        return $next($request);
    }
}
