<?php

namespace App\Services\Spam;

use Illuminate\Support\Facades\Log;

/**
 * Fail closed: a captcha is required (production, or CAPTCHA_ENABLED=true)
 * but the active provider's site/secret key is missing. Every submission
 * is refused and the misconfiguration is logged, instead of silently
 * running without a bot check.
 */
class RejectingCaptchaVerifier implements CaptchaVerifier
{
    public function __construct(private readonly string $provider)
    {
    }

    public function enabled(): bool
    {
        return true;
    }

    public function provider(): string
    {
        return $this->provider;
    }

    public function siteKey(): string
    {
        return '';
    }

    public function responseField(): string
    {
        return 'captcha-response';
    }

    public function scriptUrl(): string
    {
        return '';
    }

    public function verify(?string $token, ?string $ip): bool
    {
        Log::error('Captcha is required but the site/secret key of the active provider is not configured; public form submission refused.', [
            'provider' => $this->provider,
        ]);

        return false;
    }
}
