<?php

namespace App\Console\Commands;

use App\Mail\EmailVerification;
use App\Mail\NewPostsNotification;
use App\Mail\QuestionMilestone;
use App\Mail\RegistrationExpired;
use App\Mail\UnsubscribedNotification;
use App\Mail\WelcomeMessage;
use App\Mail\WinBack;
use App\Models\Post;
use App\Models\User;
use App\Notifications\QueuedResetPassword;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

class MailSamples extends Command
{
    protected $signature = 'mail:samples
        {--to= : send every sample to this address (Mailpit when local) instead of writing files}
        {--dir=storage/app/mail-preview : where the .html and .txt files go}
        {--locale= : render in this language}
        {--user= : username to write to, default the first user}';

    protected $description = 'Render or send one sample of every email, to look at them';

    public function handle(): int
    {
        if ($this->option('to') && app()->isProduction()) {
            $this->error('samples are not sent from production');

            return self::FAILURE;
        }

        $user = ($this->option('user') ? User::where('username', $this->option('user'))->first() : User::query()->first());
        $posts = Post::with('user:id,username')->orderByDesc('total_votes')->limit(5)->get();
        if (!$user || $posts->isEmpty()) {
            $this->error('needs at least one user and one question');

            return self::FAILURE;
        }

        $locale = $this->option('locale') ?: app()->getLocale();
        app()->setLocale($locale);

        $mails = [
            'verification' => new EmailVerification($user, URL::temporarySignedRoute('verification.verify', now()->addHour(), ['id' => $user->id, 'token' => 'sample-token'])),
            'welcome' => new WelcomeMessage($user),
            'expired' => new RegistrationExpired($user),
            'unsubscribed' => new UnsubscribedNotification($user, '203.0.113.7'),
            'digest' => new NewPostsNotification($user, $posts->first(), $posts->slice(1)),
            'milestone' => new QuestionMilestone($user, $posts->first(), 100),
            'winback' => new WinBack($user, $posts->take(3)),
        ];

        if ($to = $this->option('to')) {
            foreach ($mails as $name => $mailable) {
                Mail::to($to)->locale($locale)->send($mailable);
                $this->line("sent {$name}");
            }
            // a copy that is never saved: the reset link needs a real user, the mail goes to the address given
            $recipient = $user->replicate();
            $recipient->email = $to;
            $recipient->notifyNow((new QueuedResetPassword('sample-token'))->locale($locale), ['mail']);
            $this->line('sent reset');

            return self::SUCCESS;
        }

        $dir = str_starts_with((string) $this->option('dir'), '/') ? $this->option('dir') : base_path($this->option('dir'));
        File::ensureDirectoryExists($dir);
        foreach ($mails as $name => $mailable) {
            File::put("{$dir}/{$name}.html", $mailable->render());
            File::put("{$dir}/{$name}.txt", view($mailable->content()->text, $mailable->content()->with)->render());
        }
        $reset = (new QueuedResetPassword('sample-token'))->toMail($user);
        File::put("{$dir}/reset.html", $reset->render());
        File::put("{$dir}/reset.txt", view($reset->view[1], $reset->viewData)->render());

        $this->info("written to {$dir}");

        return self::SUCCESS;
    }
}
