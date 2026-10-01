<?php

namespace App\Http\Controllers;

use App\Mail\UnsubscribedNotification;
use App\Models\User;
use Exception;
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
            return response()->json(['status' => 'ERROR', 'message' => 'LINK_INVALID_OR_EXPIRED'], 410);
        }

        $user = User::find($tokenRecord->user_id);

        if (!$user) {
            DB::table('unsubscribe_tokens')->where('id', $tokenRecord->id)->delete();
            Log::warning("Unsubscribe attempt for non-existent user. Token ID: {$tokenRecord->id}");
            return response()->json(['status' => 'ERROR', 'message' => 'USER_NOT_FOUND'], 404);
        }

        try {
            DB::transaction(function () use ($user, $tokenRecord) {
                DB::table('users')->where('id', $user->id)->update(['receives_notifications' => false]);
                DB::table('unsubscribe_tokens')->where('id', $tokenRecord->id)->delete();
            });
        } catch (Exception $e) {
            Log::error('Unsubscribe failed', ['user_id' => $user->id, 'error' => $e->getMessage()]);

            return response()->json(['status' => 'ERROR', 'message' => 'SERVER_ERROR'], 500);
        }

        Mail::to($user)->queue(new UnsubscribedNotification($user, $request->ip()));

        return response()->json([
            'status' => 'SUCCESS',
            'message' => 'UNSUBSCRIBED'
        ]);
    }
}
