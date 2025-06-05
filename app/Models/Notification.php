<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id', // The ID of the user who sent the notification
        'message',
        // Add 'read_at' if you want to track read status per user,
        // but for global notifications, it's simpler without it for now.
    ];

    /**
     * Get the user who created the notification.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
