<?php

namespace App\Notifications;

use App\Support\MailText;
use Illuminate\Bus\Queueable;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

// queued, so a registered email does not take longer to answer than an unknown one
class QueuedResetPassword extends ResetPassword implements ShouldQueue
{
    // without it Laravel reads $delay, $connection and $queue that do not exist
    use Queueable;

    public function toMail($notifiable): MailMessage
    {
        $minutes = (int) config('auth.passwords.'.config('auth.defaults.passwords').'.expire', 60);

        return (new MailMessage)
            ->subject(__('mail.reset.subject'))
            ->view(['emails.auth.reset', 'emails.text.reset'], [
                'url' => $this->resetUrl($notifiable),
                'name' => MailText::name($notifiable),
                'time' => MailText::duration($minutes),
            ]);
    }
}
