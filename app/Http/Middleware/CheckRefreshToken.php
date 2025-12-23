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

        $refreshTokenCookie = $request->cookie('refresh_token');
        if (!$refreshTokenCookie) {
            return $next($request);
        }

        $tokenModel = $this->authTokenService->getValidToken($refreshTokenCookie);

        if (!$tokenModel) {
            $response = $next($request);
            return $response->withCookie($this->authTokenService->clearCookie());
        }

        Auth::login($tokenModel->user);
        $this->authTokenService->revokeToken($tokenModel);
        $newCookie = $this->authTokenService->issueToken($tokenModel->user, $request);
        $response = $next($request);
        return $response->withCookie($newCookie);
    }
}
