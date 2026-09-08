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

    private function withKeys(string $provider = 'turnstile', array $overrides = []): array
    {
        return $this->config(array_replace_recursive([
            'providers' => [$provider => ['site_key' => 'site', 'secret_key' => 'secret']],
        ], $overrides));
    }

    public function test_production_without_keys_fails_closed(): void
    {
        $verifier = CaptchaVerifierFactory::make($this->config(), 'production');

        $this->assertInstanceOf(RejectingCaptchaVerifier::class, $verifier);
        $this->assertTrue($verifier->enabled());
        $this->assertFalse($verifier->verify('any-token', '127.0.0.1'));
    }

    public function test_a_misspelled_or_unknown_app_env_is_treated_as_production(): void
    {
        // The real server .env once carried "poduction": that must not
        // silently switch the challenge off.
        foreach (['poduction', 'prod', 'staging', 'Production ', ''] as $environment) {
            $verifier = CaptchaVerifierFactory::make($this->config(), $environment);

            $this->assertInstanceOf(RejectingCaptchaVerifier::class, $verifier, "env '{$environment}' must fail closed");
        }
    }

    public function test_development_environments_without_keys_are_disabled(): void
    {
        foreach (['local', 'testing', 'development', 'Local'] as $environment) {
            $verifier = CaptchaVerifierFactory::make($this->config(), $environment);

            $this->assertInstanceOf(NullCaptchaVerifier::class, $verifier, $environment);
            $this->assertFalse($verifier->enabled());
        }
    }

    public function test_keys_enable_the_selected_provider_everywhere(): void
    {
        $turnstile = CaptchaVerifierFactory::make($this->withKeys(), 'local');

        $this->assertInstanceOf(CloudflareTurnstileVerifier::class, $turnstile);
        $this->assertSame('site', $turnstile->siteKey());
        $this->assertSame('cf-turnstile-response', $turnstile->responseField());

        $recaptcha = CaptchaVerifierFactory::make($this->withKeys('recaptcha', ['provider' => 'recaptcha']), 'production');

        $this->assertInstanceOf(GoogleRecaptchaVerifier::class, $recaptcha);
        $this->assertSame('g-recaptcha-response', $recaptcha->responseField());
        $this->assertSame('https://google.test/api.js', $recaptcha->scriptUrl());
    }

    public function test_switching_provider_without_that_providers_keys_fails_closed(): void
    {
        // Turnstile keys present, but CAPTCHA_PROVIDER=recaptcha: never fall
        // back to the other provider silently.
        $config = $this->withKeys('turnstile', ['provider' => 'recaptcha']);

        $this->assertInstanceOf(NullCaptchaVerifier::class, CaptchaVerifierFactory::make($config, 'local'), 'development: disabled');
        $this->assertInstanceOf(RejectingCaptchaVerifier::class, CaptchaVerifierFactory::make($config, 'production'), 'production: fail closed');
    }

    public function test_explicit_enabled_flag_overrides_the_automatic_rule(): void
    {
        $forcedOn = CaptchaVerifierFactory::make($this->config(['enabled' => 'true']), 'local');
        $this->assertInstanceOf(RejectingCaptchaVerifier::class, $forcedOn);

        $forcedOff = CaptchaVerifierFactory::make($this->withKeys('turnstile', ['enabled' => 'false']), 'production');
        $this->assertInstanceOf(NullCaptchaVerifier::class, $forcedOff);
    }

    public function test_an_unrecognisable_enabled_value_never_switches_the_challenge_off(): void
    {
        foreach (['ture', 'flase', 'enabled', 'yes please', 'nope', 'null'] as $value) {
            $withKeys = CaptchaVerifierFactory::make($this->withKeys('turnstile', ['enabled' => $value]), 'production');
            $this->assertInstanceOf(CloudflareTurnstileVerifier::class, $withKeys, "'{$value}' with keys");

            $withoutKeys = CaptchaVerifierFactory::make($this->config(['enabled' => $value]), 'production');
            $this->assertInstanceOf(RejectingCaptchaVerifier::class, $withoutKeys, "'{$value}' without keys");
        }
    }

    public function test_unknown_provider_fails_closed(): void
    {
        $verifier = CaptchaVerifierFactory::make($this->config(['provider' => 'hcaptcha']), 'production');

        $this->assertInstanceOf(RejectingCaptchaVerifier::class, $verifier);
    }
}
