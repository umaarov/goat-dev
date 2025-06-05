<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    use HasFactory;

    const TYPE_GLOBAL = 'global';
    const TYPE_PERSONAL = 'personal';

    protected $fillable = [
        'user_id',
        'message',
        'type',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
