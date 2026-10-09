<?php

namespace App\Models;

use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements HasLocalePreference
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'first_name',
        'last_name',
        'username',
        'email',
        'password',
        'profile_picture',
        'header_background',
        'google_id',
        'x_id',
        'telegram_id',
        'github_id',
        'is_developer',
        'email_verified_at',
        'email_verification_token',
        'show_voted_posts_publicly',
        'locale',
        'external_links',
        'receives_notifications',
        'ai_insight_preference',
        'ai_generations_monthly_count',
        'ai_generations_daily_count',
        'last_ai_generation_date',
        'last_notified_at',
        'last_active_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'google_id',
        'x_id',
        'telegram_id',
        'github_id',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_notified_at' => 'datetime',
        'last_active_at' => 'datetime',
        'winback_sent_at' => 'datetime',
        'password' => 'hashed',
        'receives_notifications' => 'boolean',
        'show_voted_posts_publicly' => 'boolean',
        'external_links' => 'array',
        'is_developer' => 'boolean',
    ];

    public function isAdmin(): bool
    {
        return $this->id == config('app.admin_user_id');
    }

    final function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    final function votes(): HasMany
    {
        return $this->hasMany(Vote::class);
    }

    final function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    final function shares(): HasMany
    {
        return $this->hasMany(Share::class);
    }

    final function votedPosts(): BelongsToMany
    {
        return $this->belongsToMany(Post::class, 'votes', 'user_id', 'post_id')
            ->withPivot('vote_option', 'created_at')
            ->withTimestamps();
    }

    final function refreshTokens(): HasMany
    {
        return $this->hasMany(RefreshToken::class);
    }

    // mail to this user is written in their saved language, when they have one
    final public function preferredLocale(): ?string
    {
        return array_key_exists((string) $this->locale, (array) config('app.available_locales')) ? $this->locale : null;
    }

    // the reset form was filled in in the current language, and the queue worker does not know it
    final public function sendPasswordResetNotification($token): void
    {
        $this->notify((new \App\Notifications\QueuedResetPassword($token))->locale(app()->getLocale()));
    }

    /** Kill every long-lived credential: web refresh tokens, mobile refresh tokens, API access tokens. */
    final public function revokeAllCredentials(): void
    {
        $this->refreshTokens()->whereNull('revoked_at')->update(['revoked_at' => now(), 'grace_period_ends_at' => null]);
        $this->tokens()->delete();

        // also ends every live web session; callers that keep the current device re-stamp its session
        $this->forceFill(['sessions_invalid_before' => time()])->saveQuietly();
    }

    final function deviceTokens(): HasMany
    {
        return $this->hasMany(DeviceToken::class);
    }

    /**
     * Push targets for the FCM notification channel.
     * Returns DeviceToken models so the channel can prune invalid ones.
     */
    public function routeNotificationForFcm(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->deviceTokens()->get();
    }

    public function getActiveAuthMethodsCount(): int
    {
        $methods = [
            $this->password,
            $this->google_id,
            $this->x_id,
            $this->telegram_id,
            $this->github_id,
        ];

        return count(array_filter($methods));
    }
}
