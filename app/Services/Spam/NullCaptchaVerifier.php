<?php

namespace App\Services\Spam;

/** Challenge disabled (no keys outside production): every submission passes. */
class NullCaptchaVerifier implements CaptchaVerifier
{
    public function enabled(): bool
    {
        return false;
    }

    public function provider(): string
    {
        return 'none';
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
        return true;
    }

    public function verifyDetailed(?string $token, ?string $ip, ?string $expectedAction = null): CaptchaVerdict
    {
        return CaptchaVerdict::disabled();
    }
}
