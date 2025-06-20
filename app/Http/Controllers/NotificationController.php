<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {
        $user = Auth::user();

        $notifications = $user->notifications()->paginate(20);

        $user->unreadNotifications->markAsRead();

        return view('notifications.index', compact('notifications'));
    }

    public function getUnreadCount()
    {
        return response()->json(['count' => Auth::user()->unreadNotifications->count()]);
    }
}
