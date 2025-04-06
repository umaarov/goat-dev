<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Share extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'post_id',
        'platform',
    ];

    final function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    final function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }
}
