<?php

namespace App\Providers;

use App\Services\Hvac\Explanation\HvacExplanationGeneratorInterface;
use App\Services\Hvac\Explanation\NullHvacExplanationGenerator;
use App\Services\SeoService;
use App\Services\Spam\CaptchaVerifier;
use App\Services\Spam\CaptchaVerifierFactory;
use App\Services\Spam\FormTimingToken;
use App\Services\Spam\MailBudget;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
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

        // Bot challenge on the public forms (Cloudflare Turnstile by default,
        // Google reCAPTCHA as switchable fallback — see config/captcha.php).
        // Production always requires it: without keys the verifier refuses
        // every submission (fail closed) instead of running unprotected.
        // Elsewhere the challenge is only active when the keys are set, so
        // local development and the test suite need no external account.
        $this->app->singleton(CaptchaVerifier::class, function () {
            return CaptchaVerifierFactory::make(
                (array) config('captcha', []),
                $this->app->isProduction()
            );
        });

        $this->app->singleton(FormTimingToken::class, function () {
            return new FormTimingToken(
                (string) config('app.key'),
                (int) config('form-protection.timing.min_seconds', 3),
                (int) config('form-protection.timing.max_hours', 12) * 3600
            );
        });

        $this->app->singleton(MailBudget::class, function () {
            return new MailBudget((array) config('form-protection.mail', []));
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // APP_URL is the single source of truth for every absolute URL the
        // app generates (canonical, hreflang, sitemap, JSON-LD , links in
        // notification mails). Without this they follow the request's Host /
        // X-Forwarded-Host header, which any client can set.
        $appUrl = rtrim((string) config('app.url'), '/');

        if ($appUrl !== '') {
            URL::forceRootUrl($appUrl);

            if (str_starts_with($appUrl, 'https://')) {
                URL::forceScheme('https');
            }
        }

        RateLimiter::for('admin-login', function (Request $request) {
            $email = $request->input('email');
            $email = is_string($email) ? Str::lower($email) : '';

            // Two independent limits: per account (so a rotating source IP
            // cannot brute-force one admin) and per IP (so one client cannot
            // spray many addresses).
            return [
                Limit::perMinute(5)->by('email|' . $email),
                Limit::perMinute(20)->by('ip|' . $request->ip()),
            ];
        });
    }
}
