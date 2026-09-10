<?php

use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Middleware\EnsurePermission;
use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'permission' => EnsurePermission::class,
            'role' => EnsureRole::class,
            'active' => EnsureAccountIsActive::class,
        ]);

        $middleware->web(append: [
            SecurityHeaders::class,
        ]);

        // RFC 8058 one-click unsubscribe: the mailbox provider POSTs straight to
        // the URL in the List-Unsubscribe header and carries no session, so it
        // cannot present a CSRF token. The per-recipient tracking token in the
        // path is the credential, and the action is idempotent.
        $middleware->validateCsrfTokens(except: [
            'unsubscribe/*',
        ]);

        // Without this, every request behind a load balancer looks like it came
        // from the proxy — which would collapse the IP-keyed rate limiters into
        // a single shared bucket and report the wrong scheme to url() helpers.
        $middleware->trustProxies(
            at: env('TRUSTED_PROXIES') === '*' ? '*' : array_filter(explode(',', (string) env('TRUSTED_PROXIES'))),
        );
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Anything listed here is stripped from the flashed old-input bag, so a
        // validation bounce cannot round-trip a credential back into the session.
        $exceptions->dontFlash([
            'current_password',
            'password',
            'password_confirmation',
            'smtp_password',
        ]);
    })->create();
