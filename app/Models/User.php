<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

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
        'password' => 'hashed',
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
