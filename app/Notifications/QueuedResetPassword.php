<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

// queued, so a registered email does not take longer to answer than an unknown one
class QueuedResetPassword extends ResetPassword implements ShouldQueue
{
    use Queueable;
}
