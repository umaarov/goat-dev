<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RefreshToken extends Model
{
    use HasFactory, Prunable;

    protected $fillable = [
        'user_id',
        'token',
        'expires_at',
        'revoked_at',
        'grace_period_ends_at',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'revoked_at' => 'datetime',
        'grace_period_ends_at' => 'datetime',
    ];

    final function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function prunable()
    {
        return static::where('expires_at', '<=', now()->subDay());
    }

    // final function isExpired(): bool
    // {
    //     return $this->expires_at->isPast();
    // }
}
