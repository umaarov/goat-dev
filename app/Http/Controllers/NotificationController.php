<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    public function index()
    {
        $notifications = Notification::with('user')
        ->orderBy('created_at', 'desc')
            ->get();

        return view('notifications.index', compact('notifications'));
    }

    public function store(Request $request)
    {
        if (Auth::user()->username !== 'goat') {
            return redirect()->route('notifications.index')->with('error', __('messages.notifications.cannot_send'));
        }

        $request->validate([
            'message' => 'required|string|max:255',
        ]);

        Notification::create([
            'user_id' => Auth::id(),
            'message' => $request->message,
        ]);

        return redirect()->route('notifications.index')->with('success', __('messages.notifications.sent_successfully'));
    }
}
