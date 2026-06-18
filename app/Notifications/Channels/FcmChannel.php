<?php

namespace App\Notifications\Channels;

use App\Services\FcmService;
use Illuminate\Notifications\Notification;

/**
 * Custom notification channel that delivers push messages via FCM.
 *
 * A notification opts in by (a) listing this channel in via() and (b) defining
 * a toFcm($notifiable): FcmMessage method. The notifiable supplies its device
 * tokens through routeNotificationForFcm(). Tokens FCM reports as unregistered
 * are pruned automatically.
 */
class FcmChannel
{
    public function __construct(private FcmService $fcm) {}

    public function send(object $notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toFcm')) {
            return;
        }

        $tokens = $notifiable->routeNotificationFor('fcm', $notification);
        if ($tokens === null || (is_countable($tokens) && count($tokens) === 0)) {
            return;
        }

        $message = $notification->toFcm($notifiable);

        foreach ($tokens as $deviceToken) {
            $result = $this->fcm->send($deviceToken->token, $message);

            if ($result === 'invalid') {
                $deviceToken->delete();
            } elseif ($result === 'ok') {
                $deviceToken->forceFill(['last_used_at' => now()])->saveQuietly();
            }
        }
    }
}
