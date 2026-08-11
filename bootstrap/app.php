<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin' => \App\Http\Middleware\EnsureAdminIsAuthenticated::class,
        ]);

        $middleware->append(\App\Http\Middleware\SecurityHeaders::class);

        // Trust the reverse proxy in front of this app (nginx/Forge) so RateLimiter sees the real client IP, not the proxy's.
        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // PHP/webserver rejects an upload before Laravel validation runs
        // (post_max_size / client_max_body_size). At that point the session
        // has not started yet, so flash errors are unavailable — redirect the
        // HVAC import pages back with a query flag the page renders as a
        // friendly message (no server details exposed).
        $exceptions->render(function (\Illuminate\Http\Exceptions\PostTooLargeException $e, \Illuminate\Http\Request $request) {
            if ($request->is('admin/hvac/import*')) {
                return redirect()->route('admin.hvac.import.index', ['upload_too_large' => 1]);
            }

            return null;
        });
    })->create();
