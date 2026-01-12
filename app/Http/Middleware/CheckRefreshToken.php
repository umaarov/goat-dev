<?php

namespace App\Http\Middleware;

use App\Services\AuthTokenService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
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
        $wasAuthenticated = Auth::check();
        $newCookie = null;
        if ($wasAuthenticated) {
            $newCookie = $this->authTokenService->validateAndExtendSession($request);
        }

        $response = $next($request);
        if ($wasAuthenticated && !Auth::check()) {
            return $response;
        }
        if ($newCookie) {
            return $response->withCookie($newCookie);
        }
        if (Auth::check()) {
            return $response;
        }
        $refreshToken = $request->cookie('refresh_token');
        if (!$refreshToken) {
            return $response;
        }

        $tokenModel = $this->authTokenService->getValidToken($refreshToken);

        if (!$tokenModel) {
            return $response->withCookie($this->authTokenService->clearCookie());
        }

        Auth::login($tokenModel->user);
        $request->session()->regenerate();

        $this->authTokenService->revokeToken($tokenModel);
        $loginCookie = $this->authTokenService->issueToken($tokenModel->user, $request);

        Log::channel('audit_trail')->info('[AUTH] [REFRESH_TOKEN] User authenticated via refresh token', [
            'user_id' => $tokenModel->user->id,
        ]);

        return $next($request)->withCookie($loginCookie);
    }
}
