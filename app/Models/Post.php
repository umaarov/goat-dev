<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Post extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'question',
        'option_one_title',
        'option_one_image',
        'option_two_title',
        'option_two_image',
        'option_one_votes',
        'option_two_votes',
        'total_votes',
        'view_count',
    ];

    protected $appends = [
        'option_one_percentage',
        'option_two_percentage',
    ];

    final function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    final function votes(): HasMany
    {
        return $this->hasMany(Vote::class);
    }

    final function voters(): HasMany
    {
        return $this->belongsToMany(User::class, 'votes', 'post_id', 'user_id')
            ->withPivot('vote_option')
            ->withTimestamps();
    }

    final function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    final function shares(): HasMany
    {
        return $this->hasMany(Share::class);
    }

    final function getOptionOnePercentageAttribute(): float|int
    {
        if (empty($this->total_votes) || $this->total_votes == 0) {
            return 0;
        }
        return round(($this->option_one_votes / $this->total_votes) * 100, 1);
    }

    final function getOptionTwoPercentageAttribute(): float|int
    {
        if (empty($this->total_votes) || $this->total_votes == 0) {
            return 0;
        }
        return round(($this->option_two_votes / $this->total_votes) * 100, 1);
    }
}
