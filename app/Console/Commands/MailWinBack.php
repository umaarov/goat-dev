<?php

namespace App\Console\Commands;

use App\Mail\WinBack;
use App\Models\Post;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class MailWinBack extends Command
{
    protected $signature = 'mail:win-back {--dry-run : only list who would get the email}';

    protected $description = 'Email people who stopped visiting, a few per day, warmest first';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $config = config('promotion.winback');

        if (!$dry && !$config['enabled']) {
            $this->warn('Win-back is off (WINBACK_ENABLED).');

            return self::SUCCESS;
        }

        $posts = Post::query()->whereNotNull('option_one_image')->orderByDesc('total_votes')->limit(3)->get();
        $lastSeen = DB::raw('coalesce(last_active_at, created_at)');

        $users = User::query()
            ->where('receives_notifications', true)
            ->whereNotNull('email')
            ->whereNotNull('email_verified_at')
            ->whereBetween($lastSeen, [now()->subDays($config['inactive_max_days']), now()->subDays($config['inactive_min_days'])])
            ->where(fn ($q) => $q->whereNull('winback_sent_at')->orWhere('winback_sent_at', '<', now()->subDays(90)))
            ->orderByDesc($lastSeen)
            ->limit($config['per_day'])
            ->get();

        if ($posts->isEmpty()) {
            return self::SUCCESS;
        }

        foreach ($users as $user) {
            $this->line("win-back: #{$user->id} {$user->username}");

            if (!$dry) {
                Mail::to($user)->queue(new WinBack($user, $posts));
                $user->forceFill(['winback_sent_at' => now()])->saveQuietly();
            }
        }

        return self::SUCCESS;
    }
}
