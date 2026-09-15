<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Foundation\Http\Kernel;

return Application::configure(basePath: dirname(__DIR__))
    ->withKernels(
        App\Console\Kernel::class,
        \Illuminate\Foundation\Http\Kernel::class
    )
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Confiar en el proxy para leer X-Forwarded-Proto (HTTPS de Cloudflare Tunnel /
        // cualquier balanceador). Sin esto, tras un proxy HTTPS Laravel genera URLs http://
        // y el navegador bloquea los assets por "Mixed Content" no afecta Prod util en desarollo local.
        $middleware->trustProxies(at: '*');

        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })
    ->create();
