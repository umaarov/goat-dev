<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReferralClick extends Model
{
    protected $fillable = [
        'referrer',
        'url',
        'ip',
        'user_agent',
    ];
}
