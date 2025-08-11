<?php

namespace App\Http\Controllers;

use App\Mail\UnsubscribedNotification;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

// <-- Import Log for debugging

class NotificationController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth')->only(['index', 'getUnreadCount']);
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

    public function unsubscribe(Request $request, string $token): JsonResponse
    {
        $tokenRecord = DB::table('unsubscribe_tokens')->where('token', $token)->first();

        if (!$tokenRecord || Carbon::parse($tokenRecord->expires_at)->isPast()) {
            if ($tokenRecord) {
                DB::table('unsubscribe_tokens')->where('id', $tokenRecord->id)->delete();
            }
            return response()->json([
                'status' => 'error',
                'message' => 'This unsubscribe link is invalid or has expired.'
            ], 410);
        }

        $user = User::find($tokenRecord->user_id);

        if ($user && $user->receives_notifications) {
            $user->update(['receives_notifications' => false]);
            Mail::to($user)->queue(new UnsubscribedNotification($user, $request->ip()));
            Log::info("User {$user->id} successfully unsubscribed.");
        } else {
            Log::warning("Unsubscribe attempt for user {$user->id} who was already unsubscribed or not found.");
        }

        DB::table('unsubscribe_tokens')->where('id', $tokenRecord->id)->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'You have been successfully unsubscribed. A confirmation email has been sent.'
        ]);
    }
}
