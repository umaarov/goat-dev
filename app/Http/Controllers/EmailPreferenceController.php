<?php

namespace App\Http\Controllers;

use App\Mail\UnsubscribedNotification;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;

// Debate-update emails: the links in the emails are signed (no login, no stored token, no expiry).
// GET only shows a page, because inbox scanners open links; the POST changes the setting, and is also
// what mail clients send for one-click unsubscribe (RFC 8058).
class EmailPreferenceController extends Controller
{
    public function show(Request $request, int $user): View|Response
    {
        $account = $this->account($request, $user);

        return $account ? $this->page($account, $account->receives_notifications ? 'confirm' : 'already') : $this->invalid();
    }

    public function unsubscribe(Request $request, int $user): View|Response
    {
        $account = $this->account($request, $user);
        if (!$account) {
            return $this->invalid();
        }

        // only the first request changes anything, so a repeated click never sends a second confirmation
        $changed = DB::table('users')->where('id', $account->id)->where('receives_notifications', true)->update(['receives_notifications' => false]) > 0;

        if ($changed) {
            Log::channel('audit_trail')->info('[EMAIL] [UNSUBSCRIBE] Debate updates switched off.', ['user_id' => $account->id, 'ip_address' => $request->ip()]);
            Mail::to($account)->queue(new UnsubscribedNotification($account, (string) $request->ip()));
        }

        // a mail client's one-click request wants a plain 200, not a page
        if ($request->input('List-Unsubscribe') === 'One-Click') {
            return response('OK', 200, ['Content-Type' => 'text/plain']);
        }

        return $this->page($account, 'done');
    }

    public function resubscribe(Request $request, int $user): View|Response
    {
        $account = $this->account($request, $user);
        if (!$account) {
            return $this->invalid();
        }

        if (DB::table('users')->where('id', $account->id)->where('receives_notifications', false)->update(['receives_notifications' => true]) > 0) {
            Log::channel('audit_trail')->info('[EMAIL] [RESUBSCRIBE] Debate updates switched on.', ['user_id' => $account->id, 'ip_address' => $request->ip()]);
        }

        return $this->page($account, 'on');
    }

    private function account(Request $request, int $user): ?User
    {
        return $request->hasValidSignature() ? User::find($user) : null;
    }

    private function page(User $account, string $state): View
    {
        return view('notifications.email-preferences', [
            'state' => $state,
            'unsubscribeUrl' => URL::signedRoute('notifications.email.unsubscribe', ['user' => $account->id]),
            'resubscribeUrl' => URL::signedRoute('notifications.email.resubscribe', ['user' => $account->id]),
        ]);
    }

    private function invalid(): Response
    {
        return response()->view('notifications.email-preferences', ['state' => 'invalid', 'unsubscribeUrl' => null, 'resubscribeUrl' => null], 403);
    }
}
