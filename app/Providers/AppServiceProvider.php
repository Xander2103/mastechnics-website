<?php

namespace App\Providers;

use App\Services\SeoService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Request-scoped: page templates add schema.org nodes to the same
        // instance the layout later renders into a single JSON-LD graph.
        $this->app->scoped(SeoService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('admin-login', function (Request $request) {
            $email = $request->input('email');
            $email = is_string($email) ? Str::lower($email) : '';
            $key = $email . '|' . $request->ip();

            return Limit::perMinute(5)->by($key);
        });
    }
}
