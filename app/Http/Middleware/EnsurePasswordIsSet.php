<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordIsSet
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check() && !Auth::user()->password) {
            return redirect()->route('password.set.form')->with('info', __('messages.password_set_required_info'));
        }

        return $next($request);
    }
}
