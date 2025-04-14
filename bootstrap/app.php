<?php

use App\Console\Commands\CleanupUnverifiedUsers;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\HandleCors;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
//        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->append(HandleCors::class);
//        $middleware->prepend(EnsureEmailIsVerified::class);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->withCommands(
        (array)CleanupUnverifiedUsers::class,
    )->withSchedule(
        function ($schedule) {
            $schedule->command('users:cleanup-unverified')
                ->everyTenMinutes();
        }
    )
    ->create();
