<?php

use App\Console\Commands\CleanupUnverifiedUsers;
use App\Console\Commands\GenerateSitemap;
use App\Console\Commands\SendPostNotifications;
use App\Http\Middleware\EnsurePasswordIsSet;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetLocale;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\HandleCors;
use Spatie\ResponseCache\Middlewares\CacheResponse;
use Symfony\Component\HttpFoundation\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
//        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'cache.response' => CacheResponse::class,
            'password.confirm' => RequirePassword::class,
            'password.is_set' => EnsurePasswordIsSet::class,
        ]);
        $middleware->append(HandleCors::class);
//        $middleware->prepend(EnsureEmailIsVerified::class);
        $middleware->append(SecurityHeaders::class);
        $middleware->web(append: [
            SetLocale::class,
//            AddCspHeaders::class,
        ]);
        $middleware->trustProxies(
            at: [
                '103.21.244.0/22',
                '103.22.200.0/22',
                '103.31.4.0/22',
                '104.16.0.0/13',
                '104.24.0.0/14',
                '108.162.192.0/18',
                '131.0.72.0/22',
                '141.101.64.0/18',
                '162.158.0.0/15',
                '172.64.0.0/13',
                '173.245.48.0/20',
                '188.114.96.0/20',
                '190.93.240.0/20',
                '197.234.240.0/22',
                '198.41.128.0/17',

                '2400:cb00::/32',
                '2606:4700::/32',
                '2803:f800::/32',
                '2405:b500::/32',
                '2405:8100::/32',
                '2a06:98c0::/29',
                '2c0f:f248::/32',
            ],
            headers: Request::HEADER_X_FORWARDED_FOR |
            Request::HEADER_X_FORWARDED_HOST |
            Request::HEADER_X_FORWARDED_PORT |
            Request::HEADER_X_FORWARDED_PROTO
        );
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (Throwable $e, Request $request) {

            if ($request->wantsJson()) {

                $status = 500;
                $errorCode = 'internal_server_error';

                if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpException) {
                    $status = $e->getStatusCode();
                    $errorCode = match ($status) {
                        401 => 'authentication_required',
                        403 => 'access_forbidden',
                        404 => 'not_found',
                        429 => 'rate_limit_exceeded',
                        default => 'unexpected_error',
                    };
                }

                return response()->json([
                    'success' => false,
                    'error_code' => $errorCode,
                    'message' => $e->getMessage(),
                ], $status);
            }

            return null;
        });
    })->withCommands(
        (array)CleanupUnverifiedUsers::class,
        SendPostNotifications::class,
        GenerateSitemap::class
    )->withSchedule(
        function ($schedule) {
            $schedule->command('users:cleanup-unverified')
                ->everyTenMinutes();
            $schedule->command('sitemap:generate')
                ->dailyAt('02:00');
            $schedule->command('app:send-post-notifications')
                ->hourly();
        }
    )
    ->withProviders([
        Illuminate\Auth\AuthServiceProvider::class,
        Illuminate\Broadcasting\BroadcastServiceProvider::class,
        Illuminate\Bus\BusServiceProvider::class,
        Illuminate\Cache\CacheServiceProvider::class,
        Illuminate\Foundation\Providers\ConsoleSupportServiceProvider::class,
        Illuminate\Cookie\CookieServiceProvider::class,
        Illuminate\Database\DatabaseServiceProvider::class,
        Illuminate\Encryption\EncryptionServiceProvider::class,
        Illuminate\Filesystem\FilesystemServiceProvider::class,
        Illuminate\Foundation\Providers\FoundationServiceProvider::class,
        Illuminate\Hashing\HashServiceProvider::class,
        Illuminate\Mail\MailServiceProvider::class,
        Illuminate\Notifications\NotificationServiceProvider::class,
        Illuminate\Pagination\PaginationServiceProvider::class,
        Illuminate\Pipeline\PipelineServiceProvider::class,
        Illuminate\Queue\QueueServiceProvider::class,
        Illuminate\Redis\RedisServiceProvider::class,
        Illuminate\Auth\Passwords\PasswordResetServiceProvider::class,
        Illuminate\Session\SessionServiceProvider::class,
        Illuminate\Translation\TranslationServiceProvider::class,
        Illuminate\Validation\ValidationServiceProvider::class,
        Illuminate\View\ViewServiceProvider::class,
        Spatie\ResponseCache\ResponseCacheServiceProvider::class,
    ])
    ->create();
