<?php

namespace App\Services\Spam;

/**
 * Builds the CaptchaVerifier for config('captcha'). Kept out of the service
 * provider so the resolution rules (which provider, enabled or not, fail
 * closed when misconfigured) can be unit-tested without booting bindings.
 */
class CaptchaVerifierFactory
{
    /** @param array<string, mixed> $config */
    public static function make(array $config, bool $isProduction): CaptchaVerifier
    {
        $provider = strtolower(trim((string) ($config['provider'] ?? 'turnstile')));
        $providers = $config['providers'] ?? [];
        $settings = is_array($providers[$provider] ?? null) ? $providers[$provider] : null;

        $siteKey = trim((string) ($settings['site_key'] ?? ''));
        $secret = trim((string) ($settings['secret_key'] ?? ''));
        $configured = $settings !== null && $siteKey !== '' && $secret !== '';

        $enabled = $config['enabled'] ?? null;
        $enabled = ($enabled === null || $enabled === '')
            ? ($isProduction || $configured)
            : filter_var($enabled, FILTER_VALIDATE_BOOL);

        if (! $enabled) {
            return new NullCaptchaVerifier();
        }

        if (! $configured) {
            return new RejectingCaptchaVerifier($provider);
        }

        $args = [
            $siteKey,
            $secret,
            (string) $settings['verify_url'],
            (string) $settings['script_url'],
            (string) $settings['response_field'],
            (int) ($config['timeout_seconds'] ?? 5),
        ];

        return match ($provider) {
            'turnstile' => new CloudflareTurnstileVerifier(...$args),
            'recaptcha' => new GoogleRecaptchaVerifier(...$args),
            default => new RejectingCaptchaVerifier($provider),
        };
    }
}
