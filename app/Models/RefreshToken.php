<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RefreshToken extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'token',
        'expires_at',
        'revoked_at',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    final function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // final function isExpired(): bool
    // {
    //     return $this->expires_at->isPast();
    // }
}
