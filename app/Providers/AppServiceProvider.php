<?php

namespace App\Providers;

use App\Services\Hvac\Explanation\HvacExplanationGeneratorInterface;
use App\Services\Hvac\Explanation\NullHvacExplanationGenerator;
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

        // HVAC AI explanation boundary. Default is the null provider: the
        // whole HVAC system works without AI. A real provider can be bound
        // here later via config('hvac.explanation_provider') — API keys only
        // via environment variables, never in code.
        $this->app->bind(HvacExplanationGeneratorInterface::class, function () {
            return match (config('hvac.explanation_provider', 'null')) {
                default => new NullHvacExplanationGenerator(),
            };
        });
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
