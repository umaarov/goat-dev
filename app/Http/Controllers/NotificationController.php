<?php

namespace App\Http\Controllers;

use App\Mail\UnsubscribedNotification;
use App\Models\User;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\URL;
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

    // links of emails sent before the signed ones; they answer with a page, not JSON
    public function unsubscribe(Request $request, string $token): Response
    {
        $tokenRecord = DB::table('unsubscribe_tokens')->where('token', $token)->first();

        if (!$tokenRecord || Carbon::parse($tokenRecord->expires_at)->isPast()) {
            if ($tokenRecord) {
                DB::table('unsubscribe_tokens')->where('id', $tokenRecord->id)->delete();
            }

            return $this->resultPage('invalid', 410);
        }

        $user = User::find($tokenRecord->user_id);
        if (!$user) {
            DB::table('unsubscribe_tokens')->where('id', $tokenRecord->id)->delete();
            Log::warning("Unsubscribe attempt for non-existent user. Token ID: {$tokenRecord->id}");

            return $this->resultPage('invalid', 404);
        }

        try {
            DB::transaction(function () use ($user, $tokenRecord) {
                DB::table('users')->where('id', $user->id)->update(['receives_notifications' => false]);
                DB::table('unsubscribe_tokens')->where('id', $tokenRecord->id)->delete();
            });
        } catch (Exception $e) {
            Log::error('Unsubscribe failed', ['user_id' => $user->id, 'error' => $e->getMessage()]);

            return $this->resultPage('invalid', 500);
        }

        Mail::to($user)->queue(new UnsubscribedNotification($user, (string) $request->ip()));

        return $this->resultPage('done', 200, $user);
    }

    private function resultPage(string $state, int $status, ?User $user = null): Response
    {
        return response()->view('notifications.email-preferences', [
            'state' => $state,
            'unsubscribeUrl' => null,
            'resubscribeUrl' => $user ? URL::signedRoute('notifications.email.resubscribe', ['user' => $user->id]) : null,
        ], $status);
    }
}
