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

        // Only trust the proxies listed in TRUSTED_PROXIES (comma-separated
        // IPs/CIDRs, or "*" when a CDN with unknown IPs terminates TLS).
        // Trusting every proxy would let any client spoof its IP through
        // X-Forwarded-For (rate-limit bypass on the public forms and the
        // login throttle) and its host through X-Forwarded-Host. The
        // production host is Apache without a reverse proxy, so the default
        // is: trust nobody.
        $trustedProxies = array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('TRUSTED_PROXIES', ''))
        )));

        if ($trustedProxies !== []) {
            $middleware->trustProxies(at: in_array('*', $trustedProxies, true) ? '*' : $trustedProxies);
        }
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

            // Same situation for the public request wizard (8 photos × 5 MB
            // can exceed post_max_size): send the visitor back to the form
            // with a localised message instead of a bare 413 page.
            if ($request->is('nl/requests', 'fr/requests', 'en/requests')) {
                $locale = $request->segment(1);
                $slug = config("site.page_slugs.request.{$locale}");

                if (is_string($slug) && $slug !== '') {
                    return redirect()->route('pages.show', [
                        'locale' => $locale,
                        'slug' => $slug,
                        'upload_too_large' => 1,
                    ]);
                }
            }

            return null;
        });
    })->create();
