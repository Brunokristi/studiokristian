<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin' => \App\Http\Middleware\AdminOnly::class,
            'admin_or_coworker' => \App\Http\Middleware\AdminOrCoworker::class,
            'client.access' => \App\Http\Middleware\EnsureClientPortalAccess::class,
            'billing.api' => \App\Http\Middleware\AuthenticateBillingApi::class,
        ]);

        $middleware->redirectGuestsTo(function (Request $request): string {
            if ($request->is('client') || $request->is('client/*')) {
                return route('client.login');
            }

            return route('login');
        });

        $middleware->redirectUsersTo(function (Request $request): string {
            if ($request->is('client') || $request->is('client/*')) {
                return route('client.dashboard');
            }

            return route('dashboard');
        });

        // Public contact form is unauthenticated JSON API, not a session-backed form.
        $middleware->validateCsrfTokens(except: [
            'api/contact',
            'api/webhooks/stripe',
            'api/billing/checkout-sessions',
            'api/v1/billing/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
