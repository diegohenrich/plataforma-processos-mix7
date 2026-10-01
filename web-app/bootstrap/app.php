<?php

use App\Http\Middleware\EnsureApiTokenAbility;
use App\Http\Middleware\EnsureUserIsActive;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // O app não publica portas no host; o Traefik é o único ingresso público.
        $middleware->trustProxies(at: '*');

        $middleware->alias([
            'active' => EnsureUserIsActive::class,
            'api.token' => EnsureApiTokenAbility::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
