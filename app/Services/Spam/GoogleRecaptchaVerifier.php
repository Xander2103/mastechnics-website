<?php

namespace App\Services\Spam;

/**
 * Google reCAPTCHA v2 ("I'm not a robot" checkbox). Fallback provider.
 * siteverify returns hostname but no action, so only the hostname is
 * checked.
 */
class GoogleRecaptchaVerifier extends SiteVerifyCaptchaVerifier
{
    /** @param array<int, string> $expectedHostnames */
    public function __construct(
        string $siteKey,
        string $secretKey,
        string $verifyUrl,
        string $scriptUrl,
        string $responseField,
        int $timeoutSeconds = 5,
        array $expectedHostnames = [],
        bool $verifyHostname = true,
        bool $verifyAction = true,
        int $maxTokenAgeSeconds = 300,
    ) {
        parent::__construct(
            $siteKey,
            $secretKey,
            $verifyUrl,
            $scriptUrl,
            $responseField,
            $timeoutSeconds,
            $expectedHostnames,
            $verifyHostname,
            $verifyAction,
            supportsAction: false,
            maxTokenAgeSeconds: $maxTokenAgeSeconds,
        );
    }

    public function provider(): string
    {
        return 'recaptcha';
    }
}
