<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SocialPromotion extends Model
{
    protected $fillable = ['post_id', 'kind', 'milestone', 'networks'];

    protected $casts = ['networks' => 'array'];

    final public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }
}
