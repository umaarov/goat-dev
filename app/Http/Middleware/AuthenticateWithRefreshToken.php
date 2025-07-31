<?php

namespace App\Http\Middleware;

use App\Services\AuthTokenService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateWithRefreshToken
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

        $refreshTokenCookie = $request->cookie('refresh_token');

        if (!$refreshTokenCookie) {
            return $next($request);
        }

        $token = $this->authTokenService->getValidToken($refreshTokenCookie);

        if (!$token) {
            return $next($request)->withCookie($this->authTokenService->clearCookie());
        }

        Auth::login($token->user);
        $request->session()->regenerate();
        $this->authTokenService->revokeToken($token);
        $newCookie = $this->authTokenService->issueToken($token->user, $request);

        $response = $next($request);
        $response->withCookie($newCookie);

        return $response;
    }
}
