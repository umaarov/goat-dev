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
        if (Auth::check()) {
            return $next($request);
        }
        $refreshToken = $request->cookie('refresh_token');
        if (!$refreshToken) {
            return $next($request);
        }

        $tokenModel = $this->authTokenService->getValidToken($refreshToken);

        if (!$tokenModel) {
            return $next($request)->withCookie($this->authTokenService->clearCookie());
        }

        Auth::login($tokenModel->user);
        $request->session()->regenerate();
        Log::channel('audit_trail')->info('[AUTH] [REFRESH] User logged in via refresh token', [
            'user_id' => $tokenModel->user->id
        ]);

        if ($tokenModel->revoked_at) {
            return $next($request);
        }
        if ($this->authTokenService->shouldRotate($tokenModel)) {
            $newCookie = $this->authTokenService->rotateToken($tokenModel, $request);
            return $next($request)->withCookie($newCookie);
        }
        return $next($request);
    }
}
