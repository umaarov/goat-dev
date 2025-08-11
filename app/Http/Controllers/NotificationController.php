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

        $userId = $user->id;
        Log::critical("--- STARTING UNSUBSCRIBE DIAGNOSTIC FOR USER: {$userId} ---");

        try {
            $initialValue = DB::table('users')->where('id', $userId)->value('receives_notifications');
            Log::critical("DIAGNOSTIC (STEP 1): Value BEFORE update is: [{$initialValue}]");

            DB::beginTransaction();
            Log::critical("DIAGNOSTIC (STEP 2): Manual transaction started.");

            DB::table('users')->where('id', $userId)->update(['receives_notifications' => false]);
            Log::critical("DIAGNOSTIC (STEP 3): UPDATE statement executed.");

            $valueInsideTransaction = DB::table('users')->where('id', $userId)->value('receives_notifications');
            Log::critical("DIAGNOSTIC (STEP 4): Value INSIDE transaction, AFTER update is: [{$valueInsideTransaction}]");

            if ($valueInsideTransaction == 1) {
                DB::rollBack(); // Abort everything.
                Log::emergency("DIAGNOSTIC (FAILURE): Value is still 1 inside the transaction. PROBLEM IS A 'BEFORE UPDATE' TRIGGER ON YOUR 'users' TABLE. The trigger is reverting the change instantly. Talk to your DBA or check your database schema.");
                return response()->json(['status' => 'ERROR', 'message' => 'SERVER_ERROR'], 500);
            }

            DB::commit();
            Log::critical("DIAGNOSTIC (STEP 5): Transaction committed.");

        } catch (Exception $e) {
            DB::rollBack();
            Log::error("DIAGNOSTIC (FAILURE): The entire transaction failed and was rolled back. Error: " . $e->getMessage());
            return response()->json(['status' => 'ERROR', 'message' => 'SERVER_ERROR'], 500);
        }

        $finalValue = DB::table('users')->where('id', $userId)->value('receives_notifications');
        Log::critical("DIAGNOSTIC (STEP 6): Value AFTER transaction commit is: [{$finalValue}]");

        if ($finalValue == 1) {
            Log::emergency("DIAGNOSTIC (FAILURE): Value reverted to 1 AFTER the transaction committed. PROBLEM IS AN 'AFTER UPDATE' TRIGGER OR DATABASE REPLICATION LAG. Check your database triggers and/or replication status.");
        } else {
            Log::info("DIAGNOSTIC (SUCCESS): The value was successfully updated to 0 for user {$userId}.");
        }

        Mail::to($user)->queue(new UnsubscribedNotification($user, $request->ip()));
        DB::table('unsubscribe_tokens')->where('id', $tokenRecord->id)->delete();
        Log::critical("--- ENDING UNSUBSCRIBE DIAGNOSTIC FOR USER: {$userId} ---");

        return response()->json([
            'status' => 'SUCCESS',
            'message' => 'UNSUBSCRIBED'
        ]);
    }
}
