<?php

namespace App\Services\Spam;

/** Cloudflare Turnstile: siteverify echoes hostname, action and challenge_ts. */
class CloudflareTurnstileVerifier extends SiteVerifyCaptchaVerifier
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
            supportsAction: true,
            maxTokenAgeSeconds: $maxTokenAgeSeconds,
        );
    }

    public function provider(): string
    {
        return 'turnstile';
    }
}
