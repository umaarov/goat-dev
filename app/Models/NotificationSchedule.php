<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationSchedule extends Model
{
    use HasFactory;
    protected $fillable = ['user_id', 'send_at'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
