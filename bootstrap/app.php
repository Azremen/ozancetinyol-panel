<?php

use App\Http\Middleware\BlockIpMiddleware;
use App\Http\Middleware\CspMiddleware;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        then: function () {
            // Image resize endpoint — separate file so it stays clean in web.php
            Route::middleware('web')
                ->group(base_path('routes/img.php'));
        },
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(BlockIpMiddleware::class);
        $middleware->prepend(CspMiddleware::class);
    })
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->command('activitylog:clean --days=30 --force --no-interaction')
            ->weekly()
            ->appendOutputTo(storage_path('logs/activitylog-clean.log'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->respond(function (Response $response): Response {
            if ($response->getStatusCode() >= 400) {
                $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
            }

            return app(CspMiddleware::class)->applyHeaders(request(), $response);
        });
    })->create();
