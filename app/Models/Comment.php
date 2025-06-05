<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;

class Comment extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'post_id',
        'content',
        'likes_count',
    ];

//    protected $appends = ['is_liked_by_current_user', 'likes_count_accessor'];

    final function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    final function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function likes(): HasMany
    {
        return $this->hasMany(CommentLike::class);
    }

    public function getIsLikedByCurrentUserAttribute(): bool
    {
        if (!Auth::check()) {
            return false;
        }
        return $this->likes()->where('user_id', Auth::id())->exists();
    }

    // public function getLikesCountAccessorAttribute(): int
    // {
    //     return $this->likes()->count();
    // }
}
