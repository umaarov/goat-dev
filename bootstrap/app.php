<?php

use App\Console\Commands\CleanupUnverifiedUsers;
use App\Console\Commands\GenerateSitemap;
use App\Console\Commands\SendPostNotifications;
use App\Http\Middleware\CheckRefreshToken;
use App\Http\Middleware\EnsurePasswordIsSet;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\UpdateLastActiveTimestamp;
use Illuminate\Auth\AuthenticationException;
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
        channels: __DIR__ . '/../routes/channels.php',
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
            CheckRefreshToken::class,
            SetLocale::class,
//            AddCspHeaders::class,
            UpdateLastActiveTimestamp::class,
        ]);
        $middleware->trustProxies(
            at: [
//                cloudflare
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

//                ezoic
//                '52.20.63.25',
//                '3.225.202.138',
//                '3.217.200.190',
//                '54.212.71.227',
//                '52.12.170.68',
//                '34.218.21.81',
//                '3.7.90.144',
//                '13.127.240.219',
//                '18.139.6.69',
//                '18.140.184.0',
//                '3.106.6.164',
//                '3.106.176.61',
//                '3.237.131.67',
//                '15.222.77.144',
//                '15.222.108.52',
//                '18.157.131.187',
//                '18.157.105.182',
//                '23.126.25.160',
//                '34.248.174.237',
//                '52.16.85.139',
//                '34.255.61.232',
//                '15.236.165.82',
//                '15.236.137.228',
//                '15.236.166.30',
//                '18.228.20.129',
//                '18.228.107.195',
//                '13.237.131.67',
//                '3.106.176.6',
//                '3.126.25.160',
//                '2600:1f10:4c55:e200::/56',
//                '2600:1f13:393:600::/56',
//                '2406:da1a:e10::/56',
//                '2406:da18:9d0:1400::/56',
//                '2406:da1c:58a:e100::/56',
//                '2600:1f11:f39:6f00::/56',
//                '2a05:d014:776:a600::/56',
//                '2a05:d018:dd:7800::/56',
//                '2a05:d012:4d8:6800::/56',
//                '2600:1f1e:342:2f00::/56',
            ],
            headers: Request::HEADER_X_FORWARDED_FOR |
            Request::HEADER_X_FORWARDED_HOST |
            Request::HEADER_X_FORWARDED_PORT |
            Request::HEADER_X_FORWARDED_PROTO
        );
        $middleware->validateCsrfTokens(except: [
            'webhooks/sonar',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (Throwable $e, \Illuminate\Http\Request $request) {

            if ($request->wantsJson()) {

                if ($e instanceof AuthenticationException) {
                    return response()->json([
                        'success' => false,
                        'error_code' => 'authentication_required',
                        'message' => 'Unauthenticated.',
                    ], 401);
                }

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
            $schedule->command('app:process-notification-schedules')->everyMinute();
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
