<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function votes()
    {
        return $this->hasMany(Vote::class);
    }

    public function comments()
    {
        return $this->hasMany(Comment::class);
    }

    public function shares()
    {
        return $this->hasMany(Share::class);
    }

    public function getOptionOnePercentageAttribute()
    {
        if (empty($this->total_votes) || $this->total_votes == 0) {
            return 0;
        }
        return round(($this->option_one_votes / $this->total_votes) * 100, 1);
    }

    public function getOptionTwoPercentageAttribute()
    {
        if (empty($this->total_votes) || $this->total_votes == 0) {
            return 0;
        }
        return round(($this->option_two_votes / $this->total_votes) * 100, 1);
    }
}
