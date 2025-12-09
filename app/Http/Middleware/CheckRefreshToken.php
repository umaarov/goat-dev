<?php

namespace App\Http\Middleware;

use App\Services\AuthTokenService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckRefreshToken
{
    protected AuthTokenService $authTokenService;

    public function __construct(AuthTokenService $authTokenService)
    {
        $this->authTokenService = $authTokenService;
    }

    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            return $next($request);
        }

        $refreshToken = $request->cookie('refresh_token');
        if (!$refreshToken) {
            return $next($request);
        }

        $tokenModel = $this->authTokenService->getValidToken($refreshToken);
        if ($tokenModel) {
            Auth::login($tokenModel->user);
        }

        return $next($request);
    }
}
