<?php

use App\Http\Middleware\EnsureUserCanAccessPaqueteria;
use App\Http\Middleware\EnsureUserIsSuperAdmin;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'superadmin' => EnsureUserIsSuperAdmin::class,
            'paqueteria.access' => EnsureUserCanAccessPaqueteria::class,
        ]);

        // OpenPay CSRF exclusion removed along with the gateway itself
        // — there's no public endpoint OpenPay could POST to right now.
        // Re-add 'webhooks/openpay' here if/when the gateway comes back.
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
