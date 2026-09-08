<?php

namespace Tests\Unit\Spam;

use App\Services\Spam\CaptchaVerifierFactory;
use App\Services\Spam\CloudflareTurnstileVerifier;
use App\Services\Spam\GoogleRecaptchaVerifier;
use App\Services\Spam\NullCaptchaVerifier;
use App\Services\Spam\RejectingCaptchaVerifier;
use Tests\TestCase;

/** Laravel TestCase: the rejecting verifier logs, which needs the container. */
class CaptchaVerifierFactoryTest extends TestCase
{
    private function config(array $overrides = []): array
    {
        return array_replace_recursive([
            'provider' => 'turnstile',
            'enabled' => null,
            'timeout_seconds' => 5,
            'providers' => [
                'turnstile' => [
                    'site_key' => null,
                    'secret_key' => null,
                    'verify_url' => 'https://cf.test/siteverify',
                    'script_url' => 'https://cf.test/api.js',
                    'response_field' => 'cf-turnstile-response',
                ],
                'recaptcha' => [
                    'site_key' => null,
                    'secret_key' => null,
                    'verify_url' => 'https://google.test/siteverify',
                    'script_url' => 'https://google.test/api.js',
                    'response_field' => 'g-recaptcha-response',
                ],
            ],
        ], $overrides);
    }

    public function test_production_without_keys_fails_closed(): void
    {
        $verifier = CaptchaVerifierFactory::make($this->config(), isProduction: true);

        $this->assertInstanceOf(RejectingCaptchaVerifier::class, $verifier);
        $this->assertTrue($verifier->enabled());
        $this->assertFalse($verifier->verify('any-token', '127.0.0.1'));
    }

    public function test_non_production_without_keys_is_disabled(): void
    {
        $verifier = CaptchaVerifierFactory::make($this->config(), isProduction: false);

        $this->assertInstanceOf(NullCaptchaVerifier::class, $verifier);
        $this->assertFalse($verifier->enabled());
    }

    public function test_keys_enable_the_selected_provider_everywhere(): void
    {
        $turnstile = CaptchaVerifierFactory::make($this->config([
            'providers' => ['turnstile' => ['site_key' => 'site', 'secret_key' => 'secret']],
        ]), isProduction: false);

        $this->assertInstanceOf(CloudflareTurnstileVerifier::class, $turnstile);
        $this->assertSame('site', $turnstile->siteKey());
        $this->assertSame('cf-turnstile-response', $turnstile->responseField());

        $recaptcha = CaptchaVerifierFactory::make($this->config([
            'provider' => 'recaptcha',
            'providers' => ['recaptcha' => ['site_key' => 'gsite', 'secret_key' => 'gsecret']],
        ]), isProduction: true);

        $this->assertInstanceOf(GoogleRecaptchaVerifier::class, $recaptcha);
        $this->assertSame('g-recaptcha-response', $recaptcha->responseField());
        $this->assertSame('https://google.test/api.js', $recaptcha->scriptUrl());
    }

    public function test_switching_provider_without_that_providers_keys_fails_closed(): void
    {
        // Turnstile keys present, but CAPTCHA_PROVIDER=recaptcha: never fall
        // back to the other provider silently.
        $verifier = CaptchaVerifierFactory::make($this->config([
            'provider' => 'recaptcha',
            'providers' => ['turnstile' => ['site_key' => 'site', 'secret_key' => 'secret']],
        ]), isProduction: false);

        $this->assertInstanceOf(NullCaptchaVerifier::class, $verifier, 'outside production: disabled');

        $verifier = CaptchaVerifierFactory::make($this->config([
            'provider' => 'recaptcha',
            'providers' => ['turnstile' => ['site_key' => 'site', 'secret_key' => 'secret']],
        ]), isProduction: true);

        $this->assertInstanceOf(RejectingCaptchaVerifier::class, $verifier, 'production: fail closed');
    }

    public function test_explicit_enabled_flag_overrides_the_automatic_rule(): void
    {
        $forcedOn = CaptchaVerifierFactory::make($this->config(['enabled' => 'true']), isProduction: false);
        $this->assertInstanceOf(RejectingCaptchaVerifier::class, $forcedOn);

        $forcedOff = CaptchaVerifierFactory::make($this->config([
            'enabled' => 'false',
            'providers' => ['turnstile' => ['site_key' => 'site', 'secret_key' => 'secret']],
        ]), isProduction: true);
        $this->assertInstanceOf(NullCaptchaVerifier::class, $forcedOff);
    }

    public function test_unknown_provider_fails_closed(): void
    {
        $verifier = CaptchaVerifierFactory::make($this->config(['provider' => 'hcaptcha']), isProduction: true);

        $this->assertInstanceOf(RejectingCaptchaVerifier::class, $verifier);
    }
}
