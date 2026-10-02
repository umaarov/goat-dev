<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

class RouteServiceProvider extends ServiceProvider
{
    public const HOME = '/';

    public function boot(): void
    {
        $this->configureRateLimiting();

        $this->routes(function () {
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api.php'));

            Route::middleware('web')
                ->group(base_path('routes/web.php'));
        });
    }

    protected function configureRateLimiting(): void
    {
        $actor = fn (Request $r) => $r->user()?->id ?: $r->ip();
        // limiters run before validation, so input can be an array: never cast it blindly
        $text = fn (mixed $v) => is_scalar($v) ? (string) $v : '';

        RateLimiter::for('api', fn (Request $r) => [
            Limit::perMinute(120)->by($actor($r)),
            Limit::perMinute(600)->by($r->ip()),
        ]);

        // site-wide ceiling per IP; generous because mobile carriers share IPs
        RateLimiter::for('web', fn (Request $r) => Limit::perMinute(600)->by($r->ip()));

        // credential stuffing: per account and per IP, independently
        RateLimiter::for('login', function (Request $r) use ($text) {
            $id = Str::lower($text($r->input('login_identifier') ?? $r->input('email')));

            return [
                Limit::perMinute(5)->by('login:'.sha1($id).'|'.$r->ip()),
                Limit::perMinute(10)->by('login-acct:'.sha1($id)),
                Limit::perMinute(30)->by('login-ip:'.$r->ip()),
            ];
        });

        RateLimiter::for('register', fn (Request $r) => [
            Limit::perMinute(5)->by($r->ip()),
            Limit::perHour(20)->by($r->ip()),
        ]);

        RateLimiter::for('password-reset', function (Request $r) use ($text) {
            $email = Str::lower($text($r->input('email')));

            return [
                Limit::perMinute(3)->by('pr-ip:'.$r->ip()),
                Limit::perHour(5)->by('pr-mail:'.sha1($email)),
            ];
        });

        // password changes, re-auth, profile secrets
        RateLimiter::for('sensitive', fn (Request $r) => Limit::perMinute(6)->by('s:'.$actor($r)));

        RateLimiter::for('search', fn (Request $r) => Limit::perMinute(30)->by('q:'.$actor($r)));

        // paid image generation (Cloudflare) + moderation per call
        RateLimiter::for('ai-generate', fn (Request $r) => [
            Limit::perMinute(2)->by('ai-m:'.$actor($r)),
            Limit::perHour(6)->by('ai-h:'.$actor($r)),
            Limit::perDay(20)->by('ai-d:'.$actor($r)),
        ]);

        // edits re-run paid moderation (DeepSeek/Groq) on every save
        RateLimiter::for('moderated-write', fn (Request $r) => [
            Limit::perMinute(12)->by('mw-m:'.$actor($r)),
            Limit::perHour(120)->by('mw-h:'.$actor($r)),
        ]);

        RateLimiter::for('webhook', fn (Request $r) => Limit::perMinute(20)->by('wh:'.$r->ip()));

        RateLimiter::for('csp-report', fn (Request $r) => Limit::perMinute(120)->by('csp:'.$r->ip()));
    }
}
