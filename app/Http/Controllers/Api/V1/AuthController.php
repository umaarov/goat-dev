<?php

namespace App\Http\Controllers\Api\V1;

use App\Events\UserRegistered;
use App\Http\Requests\Api\V1\ForgotPasswordRequest;
use App\Http\Requests\Api\V1\LoginRequest;
use App\Http\Requests\Api\V1\RefreshRequest;
use App\Http\Requests\Api\V1\RegisterRequest;
use App\Http\Requests\Api\V1\ResetPasswordRequest;
use App\Http\Requests\Api\V1\VerifyEmailRequest;
use App\Http\Resources\SessionResource;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\ApiTokenService;
use App\Services\AvatarService;
use App\Services\EmailVerificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;

class AuthController extends ApiController
{
    public function __construct(
        private ApiTokenService $tokens,
        private AvatarService $avatarService,
        private EmailVerificationService $emailVerificationService,
    ) {}

    /**
     * POST /auth/register
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create([
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'username' => $request->username,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'email_verification_token' => $this->emailVerificationService->generateToken(),
        ]);

        if ($request->hasFile('profile_picture')) {
            $user->profile_picture = $request->file('profile_picture')->store('profile_pictures', 'public');
        } else {
            $user->profile_picture = $this->avatarService->generateInitialsAvatar(
                $user->id,
                $user->first_name,
                $user->last_name ?? ''
            );
        }
        $user->save();

        $this->emailVerificationService->sendVerificationEmail($user);

        Log::channel('audit_trail')->info('[API] [REGISTER] New user registered.', [
            'user_id' => $user->id,
            'ip' => $request->ip(),
        ]);

        return $this->created([
            'verification_required' => true,
            'message' => __('messages.registration_successful_verify_email'),
            'user' => (new UserResource($user))->resolve($request),
        ]);
    }

    /**
     * POST /auth/login
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $identifier = $request->input('login_identifier');
        $field = filter_var($identifier, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

        $user = User::withTrashed()->where($field, $identifier)->first();

        if (! $user || ! $user->password || ! Hash::check($request->password, $user->password)) {
            Log::channel('audit_trail')->warning('[API] [LOGIN] Failed attempt.', [
                'identifier' => $identifier,
                'ip' => $request->ip(),
            ]);

            return $this->error(__('messages.error_invalid_login_credentials'), 401, 'invalid_credentials');
        }

        // Reactivate a soft-deleted account on successful login (within 30 days), mirroring the web flow.
        if ($user->trashed()) {
            if (! $user->deleted_at || $user->deleted_at->lte(now()->subDays(30))) {
                return $this->error(__('messages.error_invalid_login_credentials'), 401, 'invalid_credentials');
            }

            $user->restore();
            $user->first_name = $user->original_first_name ?? 'User';
            $user->last_name = $user->original_last_name;
            $user->email = $user->original_email ?? $user->email;
            $user->profile_picture = $user->original_profile_picture
                ?? $this->avatarService->generateInitialsAvatar($user->id, $user->first_name, $user->last_name ?? '');
            $user->original_first_name = null;
            $user->original_last_name = null;
            $user->original_email = null;
            $user->original_profile_picture = null;
            $user->save();

            Log::channel('audit_trail')->notice('[API] [REACTIVATE] Account restored on login.', ['user_id' => $user->id]);
        }

        if (! $user->email_verified_at) {
            return $this->error(__('messages.error_email_not_verified_login'), 403, 'email_not_verified');
        }

        return $this->respondWithTokens($user, $request);
    }

    /**
     * POST /auth/refresh
     */
    public function refresh(RefreshRequest $request): JsonResponse
    {
        $tokens = $this->tokens->refresh($request->input('refresh_token'), $request);

        if (! $tokens) {
            return $this->error('The refresh token is invalid or has expired.', 401, 'invalid_refresh_token');
        }

        return $this->ok($tokens);
    }

    /**
     * POST /auth/logout
     */
    public function logout(Request $request): JsonResponse
    {
        $this->tokens->revokeRefreshToken($request->input('refresh_token'));
        $request->user()->currentAccessToken()?->delete();

        return $this->message(__('messages.logged_out_successfully'));
    }

    /**
     * POST /auth/logout-all
     */
    public function logoutAll(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->tokens->revokeAllRefreshTokens($user);
        $user->tokens()->delete();

        return $this->message('Logged out from all devices.');
    }

    /**
     * GET /auth/me
     */
    public function me(Request $request): JsonResponse
    {
        return $this->ok(new UserResource($request->user()));
    }

    /**
     * POST /auth/email/resend
     */
    public function resendVerification(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->email_verified_at) {
            return $this->message(__('messages.email_already_verified'));
        }

        $this->emailVerificationService->sendVerificationEmail($user);

        return $this->message(__('messages.verification_link_sent'));
    }

    /**
     * POST /auth/email/verify
     */
    public function verifyEmail(VerifyEmailRequest $request): JsonResponse
    {
        $user = User::findOrFail($request->input('id'));

        if ($user->email_verified_at) {
            return $this->respondWithTokens($user, $request);
        }

        if (! $this->emailVerificationService->verify($user, $request->input('token'))) {
            return $this->error(__('messages.error_invalid_verification_token'), 422, 'invalid_verification_token');
        }

        event(new UserRegistered($user));

        return $this->respondWithTokens($user, $request);
    }

    /**
     * POST /auth/password/forgot
     */
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $status = Password::sendResetLink($request->only('email'));

        // Always report success to avoid leaking which emails are registered.
        return $this->message(__($status));
    }

    /**
     * POST /auth/password/reset
     */
    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill(['password' => Hash::make($password)])->save();
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return $this->message(__($status));
        }

        return $this->error(__($status), 422, 'password_reset_failed');
    }

    /**
     * GET /auth/sessions
     */
    public function sessions(Request $request): JsonResponse
    {
        return $this->ok(
            SessionResource::collection($this->tokens->activeSessions($request->user()))->resolve($request)
        );
    }

    /**
     * DELETE /auth/sessions/{id}
     */
    public function destroySession(Request $request, int $id): JsonResponse
    {
        $session = $request->user()->refreshTokens()->whereNull('revoked_at')->find($id);

        if (! $session) {
            return $this->error('Session not found.', 404, 'not_found');
        }

        $session->update(['revoked_at' => now()]);

        return $this->message('Session revoked.');
    }

    private function respondWithTokens(User $user, Request $request): JsonResponse
    {
        $deviceName = $request->input('device_name', 'mobile');
        $tokens = $this->tokens->issueTokens($user, $request, $deviceName);

        Log::channel('audit_trail')->info('[API] [LOGIN] Tokens issued.', [
            'user_id' => $user->id,
            'ip' => $request->ip(),
        ]);

        return $this->ok(array_merge($tokens, [
            'user' => (new UserResource($user))->resolve($request),
        ]));
    }
}
