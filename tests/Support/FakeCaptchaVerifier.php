<?php

namespace Tests\Support;

use App\Services\Spam\CaptchaVerdict;
use App\Services\Spam\CaptchaVerifier;

/**
 * Test double: no network. Accepts exactly the configured token(s) and
 * records every verification so tests can assert what was checked. Set
 * $verdict to force a specific outcome (hostname/action mismatch, old
 * challenge, …) regardless of the token.
 */
class FakeCaptchaVerifier implements CaptchaVerifier
{
    public const VALID_TOKEN = 'valid-captcha-token';

    /** @var array<int, array{token: ?string, ip: ?string, expectedAction: ?string}> */
    public array $calls = [];

    /** Forced verdict for the next verification(s); null = derive from the token. */
    public ?CaptchaVerdict $verdict = null;

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
        return $this->verifyDetailed($token, $ip, null)->passed();
    }

    public function verifyDetailed(?string $token, ?string $ip, ?string $expectedAction = null): CaptchaVerdict
    {
        $this->calls[] = ['token' => $token, 'ip' => $ip, 'expectedAction' => $expectedAction];

        if ($this->verdict !== null) {
            return $this->verdict;
        }

        if ($token === null || trim($token) === '') {
            return CaptchaVerdict::missing();
        }

        if (! in_array($token, $this->validTokens, true)) {
            return CaptchaVerdict::failed(['invalid-input-response']);
        }

        return new CaptchaVerdict(CaptchaVerdict::PASSED, true, $expectedAction !== null ? true : null, 5);
    }
}
