<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    public function index()
    {
        $globalNotifications = Notification::where('type', Notification::TYPE_GLOBAL)
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->get();

        // $personalNotifications = collect();

        return view('notifications.index', compact('globalNotifications'));
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
            'type' => Notification::TYPE_GLOBAL,
        ]);

        return redirect()->route('notifications.index')->with('success', __('messages.notifications.sent_successfully'));
    }
}
