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
        'email_verified_at',
        'email_verification_token',
        'show_voted_posts_publicly',
        'locale',
        'external_links',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'google_id',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'show_voted_posts_publicly' => 'boolean',
        'external_links' => 'array',
    ];

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
}
