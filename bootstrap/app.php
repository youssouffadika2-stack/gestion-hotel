<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*'); // derrière le proxy HTTPS de Coolify
        // Accès des hôtels recalculé automatiquement (paiement => actif, échéance => suspendu)
        $middleware->web(append: [\App\Http\Middleware\SyncHotelAccess::class]);
        $middleware->alias([
            'hotel.selected' => \App\Http\Middleware\EnsureHotelSelected::class,
            'platform.admin' => \App\Http\Middleware\EnsurePlatformAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
