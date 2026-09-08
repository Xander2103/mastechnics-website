<?php

namespace App\Services\Spam;

use Illuminate\Support\Facades\Log;

/**
 * Builds the CaptchaVerifier for config('captcha'). Kept out of the service
 * provider so the resolution rules (which provider, enabled or not, fail
 * closed when misconfigured) can be unit-tested without booting bindings.
 *
 * Fail-closed rule: the challenge is only allowed to be OFF in the
 * environments listed in DEVELOPMENT_ENVIRONMENTS. Any other APP_ENV —
 * "production", but also a typo like "poduction" or an unknown value — is
 * treated as production and refuses submissions until keys are set.
 */
class CaptchaVerifierFactory
{
    public const DEVELOPMENT_ENVIRONMENTS = ['local', 'testing', 'development', 'dev'];

    /** @param array<string, mixed> $config */
    public static function make(array $config, string $environment): CaptchaVerifier
    {
        $provider = strtolower(trim((string) ($config['provider'] ?? 'turnstile')));
        $providers = $config['providers'] ?? [];
        $settings = is_array($providers[$provider] ?? null) ? $providers[$provider] : null;

        $siteKey = trim((string) ($settings['site_key'] ?? ''));
        $secret = trim((string) ($settings['secret_key'] ?? ''));
        $configured = $settings !== null && $siteKey !== '' && $secret !== '';

        $isDevelopment = in_array(strtolower(trim($environment)), self::DEVELOPMENT_ENVIRONMENTS, true);

        // Explicit switch. Anything that is not a recognisable boolean
        // ("ture", "yes please") counts as "not set", never as "off".
        $switch = $config['enabled'] ?? null;
        $switch = ($switch === null || $switch === '')
            ? null
            : filter_var($switch, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);

        if ($switch === null && isset($config['enabled']) && $config['enabled'] !== '') {
            Log::error('CAPTCHA_ENABLED has an unrecognised value; treating it as not set (fail closed outside development).', [
                'value' => (string) $config['enabled'],
            ]);
        }

        $enabled = $switch ?? (! $isDevelopment || $configured);

        if (! $enabled) {
            if (! $isDevelopment) {
                Log::warning('Captcha explicitly disabled outside a development environment; public forms run without a bot challenge.', [
                    'environment' => $environment,
                ]);
            }

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
