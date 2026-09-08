<?php

namespace Tests\Support;

use App\Services\Spam\CaptchaVerifier;

/**
 * Test double: no network. Accepts exactly the configured token(s) and
 * records every verification so tests can assert what was checked.
 */
class FakeCaptchaVerifier implements CaptchaVerifier
{
    public const VALID_TOKEN = 'valid-captcha-token';

    /** @var array<int, array{token: ?string, ip: ?string}> */
    public array $calls = [];

    /** @param array<int, string> $validTokens */
    public function __construct(
        private readonly array $validTokens = [self::VALID_TOKEN],
        private readonly bool $enabled = true,
        private readonly string $provider = 'turnstile',
        private readonly string $responseField = 'cf-turnstile-response',
    ) {
    }

    public function enabled(): bool
    {
        return $this->enabled;
    }

    public function provider(): string
    {
        return $this->provider;
    }

    public function siteKey(): string
    {
        return 'fake-site-key';
    }

    public function responseField(): string
    {
        return $this->responseField;
    }

    public function scriptUrl(): string
    {
        return 'https://example.test/captcha.js';
    }

    public function verify(?string $token, ?string $ip): bool
    {
        $this->calls[] = ['token' => $token, 'ip' => $ip];

        return $token !== null && in_array($token, $this->validTokens, true);
    }
}
