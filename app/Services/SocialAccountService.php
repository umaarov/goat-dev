<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Resolves a social-provider identity into a local User account.
 *
 * Consolidates the find-or-create / account-linking logic that the web
 * Auth\AuthController performs per-provider (handleGoogleUser, handleXUser,
 * handleTelegramUser, handleGithubUser) into one provider-agnostic service so
 * the mobile API can reuse the exact same rules.
 *
 * Callers must first verify the provider token natively and pass a normalised
 * SocialUserData object.
 */
class SocialAccountService
{
    public function __construct(private AvatarService $avatarService) {}

    /**
     * Find an existing user for the given verified social identity, or create one.
     *
     * @param  string  $provider  one of: google|x|telegram|github
     * @return array{user:User,created:bool}
     */
    public function resolve(string $provider, SocialUserData $data): array
    {
        $idColumn = "{$provider}_id";

        // 1. Already linked by provider id.
        $user = User::where($idColumn, $data->id)->first();
        if ($user) {
            $this->syncUsernameColumn($user, $provider, $data);

            return ['user' => $user, 'created' => false];
        }

        // 2. Known email -> link the provider to the existing account.
        if ($data->email) {
            $user = User::where('email', $data->email)->first();
            if ($user) {
                $user->{$idColumn} = $data->id;
                if ($provider === 'github') {
                    $user->is_developer = true;
                }
                $user->email_verified_at = $user->email_verified_at ?? now();
                $this->syncUsernameColumn($user, $provider, $data);
                $user->save();

                return ['user' => $user, 'created' => false];
            }
        }

        // 3. Brand-new account.
        return ['user' => $this->createUser($provider, $data), 'created' => true];
    }

    /**
     * Link a verified social identity to an already-authenticated user.
     *
     * @return array{linked:bool,message:string}
     */
    public function link(User $user, string $provider, SocialUserData $data): array
    {
        $idColumn = "{$provider}_id";

        $conflict = User::where($idColumn, $data->id)->where('id', '!=', $user->id)->exists();
        if ($conflict) {
            return ['linked' => false, 'message' => "This {$provider} account is already linked to another user."];
        }

        $user->{$idColumn} = $data->id;
        if ($provider === 'github') {
            $user->is_developer = true;
        }
        $this->syncUsernameColumn($user, $provider, $data);
        $user->save();

        Log::channel('audit_trail')->info('[API] [SOCIAL] User linked a provider.', [
            'user_id' => $user->id,
            'provider' => $provider,
        ]);

        return ['linked' => true, 'message' => ucfirst($provider).' account linked successfully.'];
    }

    private function createUser(string $provider, SocialUserData $data): User
    {
        $idColumn = "{$provider}_id";
        $displayName = $data->name ?: $data->nickname ?: 'user';
        $username = $this->generateUniqueUsername($displayName, $provider === 'telegram' ? (int) $data->id : null);

        $nameParts = explode(' ', trim($displayName), 2);
        $firstName = $nameParts[0] ?: 'User';
        $lastName = $nameParts[1] ?? null;

        $email = $data->email ?: $data->id."@{$provider}-user.local";
        if (User::where('email', $email)->exists()) {
            $email = $data->id.'_'.Str::random(5)."@{$provider}-user.local";
        }

        $user = User::create([
            'first_name' => $firstName,
            'last_name' => $lastName,
            'username' => $username,
            'email' => $email,
            $idColumn => $data->id,
            'is_developer' => $provider === 'github',
            'email_verified_at' => now(),
            'password' => null,
        ]);

        $this->syncUsernameColumn($user, $provider, $data);

        if ($data->avatar) {
            $user->profile_picture = $data->avatar;
        } else {
            $user->profile_picture = $this->avatarService->generateInitialsAvatar(
                $user->id,
                $user->first_name,
                $user->last_name ?? ''
            );
        }
        $user->save();

        Log::channel('audit_trail')->info('[API] [SOCIAL] New user created from social provider.', [
            'user_id' => $user->id,
            'provider' => $provider,
        ]);

        return $user;
    }

    private function syncUsernameColumn(User $user, string $provider, SocialUserData $data): void
    {
        if (! $data->nickname) {
            return;
        }

        if ($provider === 'x' && empty($user->x_username)) {
            $user->x_username = $data->nickname;
        } elseif ($provider === 'telegram' && empty($user->telegram_username)) {
            $user->telegram_username = $data->nickname;
        }
    }

    /**
     * Generate a unique, policy-compliant username from a display name.
     * Mirrors Auth\AuthController::generateUniqueUsername.
     */
    private function generateUniqueUsername(string $name, ?int $providerNumericId = null): string
    {
        $maxLength = 24;
        $minLength = 5;

        $baseUsername = preg_replace('/[^a-zA-Z0-9_-]/', '', $name);

        if (empty($baseUsername)) {
            $baseUsername = $providerNumericId ? 'user'.$providerNumericId : 'user'.Str::random(8);
        }

        if (! preg_match('/^[a-zA-Z]/', $baseUsername)) {
            $baseUsername = 'u'.$baseUsername;
        }

        if (strlen($baseUsername) < $minLength) {
            $baseUsername .= Str::lower(Str::random($minLength - strlen($baseUsername)));
        }
        $baseUsername = substr($baseUsername, 0, 20);

        $cacheKey = 'username_check_'.md5($baseUsername);
        if (Cache::has($cacheKey)) {
            $cachedUsername = Cache::get($cacheKey);
            if (! User::where('username', $cachedUsername)->exists()) {
                return $cachedUsername;
            }
            Cache::forget($cacheKey);
        }

        $username = $baseUsername;
        $counter = 1;
        $attempt = 0;
        $maxAttempts = 1000;

        while (User::where('username', $username)->exists()) {
            if ($attempt++ >= $maxAttempts) {
                do {
                    $username = 'user'.Str::lower(Str::random(10));
                } while (User::where('username', $username)->exists());
                break;
            }

            $suffix = '_'.$counter++;
            $trimLength = $maxLength - strlen($suffix);
            $username = substr($baseUsername, 0, $trimLength).$suffix;
        }

        Cache::put($cacheKey, $username, now()->addMinutes(10));

        return $username;
    }
}
