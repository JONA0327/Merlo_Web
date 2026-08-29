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

        // OpenPay posts the webhook directly from their servers —
        // there is no CSRF token to validate against. The controller
        // verifies the payload (see OpenPayService::verifyWebhook)
        // before acting on it.
        $middleware->validateCsrfTokens(except: [
            'webhooks/openpay',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
