<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');
        $middleware->alias([
            'admin' => \App\Http\Middleware\EnsureAdmin::class,
            'manager' => \App\Http\Middleware\EnsureManager::class,
            'manager.password.changed' => \App\Http\Middleware\EnsureManagerPasswordChanged::class,
            'driver' => \App\Http\Middleware\EnsureDriver::class,
            'driver.password.changed' => \App\Http\Middleware\EnsureDriverPasswordChanged::class,
            'super-admin' => \App\Http\Middleware\EnsureSuperAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (TokenMismatchException $exception, Request $request) {
            if ($request->isMethod('POST') && $request->is('login')) {
                return redirect()
                    ->route('login')
                    ->with('status', 'Your login session expired. Please try again.');
            }
        });
    })->create();
