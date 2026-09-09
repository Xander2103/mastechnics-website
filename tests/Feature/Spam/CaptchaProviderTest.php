<?php

namespace Tests\Feature\Spam;

use App\Services\Spam\CaptchaVerdict;
use App\Services\Spam\CaptchaVerifier;
use App\Services\Spam\CaptchaVerifierFactory;
use App\Services\Spam\CloudflareTurnstileVerifier;
use App\Services\Spam\GoogleRecaptchaVerifier;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The real siteverify verifiers, against a faked HTTP layer: no test ever
 * reaches Cloudflare or Google.
 */
class CaptchaProviderTest extends TestCase
{
    /** @param array<int, string> $hostnames */
    private function turnstile(array $hostnames = [], bool $verifyHostname = true, bool $verifyAction = true): CloudflareTurnstileVerifier
    {
        return new CloudflareTurnstileVerifier('site', 'secret', 'https://cf.test/siteverify', 'https://cf.test/api.js', 'cf-turnstile-response', 5, $hostnames, $verifyHostname, $verifyAction);
    }

    /** @param array<string, mixed> $extra */
    private function fakeSuccess(array $extra = []): void
    {
        Http::fake(['cf.test/siteverify' => Http::response(array_merge([
            'success' => true,
            'hostname' => 'mastechnics.be',
            'action' => 'contact',
            'challenge_ts' => now()->subSeconds(5)->toIso8601String(),
        ], $extra))]);
    }

    // ── Hostname / action verification ──────────────────────────────────────

    public function test_token_issued_for_another_hostname_is_refused(): void
    {
        $this->fakeSuccess(['hostname' => 'evil.example']);

        $verifier = $this->turnstile(['mastechnics.be', 'www.mastechnics.be']);
        $verdict = $verifier->verifyDetailed('tok-123', '203.0.113.7', 'contact');

        $this->assertSame(CaptchaVerdict::HOSTNAME_MISMATCH, $verdict->status);
        $this->assertFalse($verdict->hostnameOk);
        $this->assertFalse($verdict->passed());
        $this->assertFalse($verifier->verify('tok-123', '203.0.113.7'), 'verify() must follow the hostname check');
    }

    public function test_missing_hostname_in_the_response_counts_as_mismatch(): void
    {
        $this->fakeSuccess(['hostname' => null]);

        $verdict = $this->turnstile(['mastechnics.be'])->verifyDetailed('tok-123', null, 'contact');

        $this->assertSame(CaptchaVerdict::HOSTNAME_MISMATCH, $verdict->status);
    }

    public function test_hostname_match_is_case_insensitive_and_accepts_www(): void
    {
        $this->fakeSuccess(['hostname' => 'WWW.Mastechnics.be']);

        $verdict = $this->turnstile(['mastechnics.be', 'www.mastechnics.be'])->verifyDetailed('tok-123', null, 'contact');

        $this->assertSame(CaptchaVerdict::PASSED, $verdict->status);
        $this->assertTrue($verdict->hostnameOk);
        $this->assertTrue($verdict->actionOk);
    }

    public function test_turnstile_token_for_another_form_action_is_refused(): void
    {
        $this->fakeSuccess(['action' => 'request']);

        $verdict = $this->turnstile(['mastechnics.be'])->verifyDetailed('tok-123', null, 'contact');

        $this->assertSame(CaptchaVerdict::ACTION_MISMATCH, $verdict->status);
        $this->assertTrue($verdict->hostnameOk);
        $this->assertFalse($verdict->actionOk);
        $this->assertFalse($verdict->passed());
    }

    public function test_turnstile_token_without_action_is_refused_when_an_action_is_expected(): void
    {
        $this->fakeSuccess(['action' => null]);

        $verdict = $this->turnstile(['mastechnics.be'])->verifyDetailed('tok-123', null, 'request');

        $this->assertSame(CaptchaVerdict::ACTION_MISMATCH, $verdict->status);
    }

    public function test_action_is_not_checked_when_no_action_is_expected(): void
    {
        $this->fakeSuccess(['action' => 'whatever']);

        $verdict = $this->turnstile(['mastechnics.be'])->verifyDetailed('tok-123', null, null);

        $this->assertSame(CaptchaVerdict::PASSED, $verdict->status);
        $this->assertNull($verdict->actionOk);
    }

    public function test_recaptcha_ignores_the_action_but_still_checks_the_hostname(): void
    {
        Http::fake(['google.test/siteverify' => Http::response(['success' => true, 'hostname' => 'mastechnics.be'])]);

        $verifier = new GoogleRecaptchaVerifier('gsite', 'gsecret', 'https://google.test/siteverify', 'https://google.test/api.js', 'g-recaptcha-response', 5, ['mastechnics.be']);

        $verdict = $verifier->verifyDetailed('g-tok', null, 'contact');
        $this->assertSame(CaptchaVerdict::PASSED, $verdict->status);
        $this->assertTrue($verdict->hostnameOk);
        $this->assertNull($verdict->actionOk, 'reCAPTCHA v2 has no action');
    }

    public function test_recaptcha_token_for_another_hostname_is_refused(): void
    {
        Http::fake(['google.test/siteverify' => Http::response(['success' => true, 'hostname' => 'evil.example'])]);

        $verifier = new GoogleRecaptchaVerifier('gsite', 'gsecret', 'https://google.test/siteverify', 'https://google.test/api.js', 'g-recaptcha-response', 5, ['mastechnics.be']);

        $this->assertSame(CaptchaVerdict::HOSTNAME_MISMATCH, $verifier->verifyDetailed('g-tok', null, 'contact')->status);
        $this->assertFalse($verifier->verify('g-tok', null));
    }

    public function test_old_challenge_is_reported_as_age_but_still_passes(): void
    {
        $this->fakeSuccess(['challenge_ts' => now()->subMinutes(10)->toIso8601String()]);

        $verdict = $this->turnstile(['mastechnics.be'])->verifyDetailed('tok-123', null, 'contact');

        $this->assertSame(CaptchaVerdict::PASSED, $verdict->status);
        $this->assertGreaterThanOrEqual(600, $verdict->challengeAgeSeconds);
    }

    public function test_hostname_check_can_be_switched_off_in_an_emergency(): void
    {
        $this->fakeSuccess(['hostname' => 'evil.example']);

        $verdict = $this->turnstile(['mastechnics.be'], verifyHostname: false)->verifyDetailed('tok-123', null, 'contact');

        $this->assertSame(CaptchaVerdict::PASSED, $verdict->status);
        $this->assertNull($verdict->hostnameOk);
    }

    public function test_action_check_can_be_switched_off_in_an_emergency(): void
    {
        $this->fakeSuccess(['action' => 'request']);

        $verdict = $this->turnstile(['mastechnics.be'], verifyAction: false)->verifyDetailed('tok-123', null, 'contact');

        $this->assertSame(CaptchaVerdict::PASSED, $verdict->status);
        $this->assertNull($verdict->actionOk);
    }

    public function test_no_expected_hostnames_means_hostname_is_not_checked(): void
    {
        $this->fakeSuccess(['hostname' => 'evil.example']);

        $this->assertSame(CaptchaVerdict::PASSED, $this->turnstile([])->verifyDetailed('tok-123', null, 'contact')->status);
    }

    public function test_missing_token_gives_missing_verdict_without_network(): void
    {
        Http::fake();

        $this->assertSame(CaptchaVerdict::MISSING, $this->turnstile()->verifyDetailed(null, null, 'contact')->status);
        $this->assertSame(CaptchaVerdict::MISSING, $this->turnstile()->verifyDetailed('  ', null, 'contact')->status);

        Http::assertNothingSent();
    }

    public function test_failed_verdict_carries_provider_error_codes(): void
    {
        Http::fake(['cf.test/siteverify' => Http::response(['success' => false, 'error-codes' => ['timeout-or-duplicate']])]);

        $verdict = $this->turnstile()->verifyDetailed('tok-123', null, 'contact');

        $this->assertSame(CaptchaVerdict::FAILED, $verdict->status);
        $this->assertSame(['timeout-or-duplicate'], $verdict->errorCodes);
    }

    public function test_factory_derives_expected_hostnames_from_app_url_and_env_list(): void
    {
        $hostnames = CaptchaVerifierFactory::expectedHostnames(
            ['expected_hostnames' => ' Mastechnics.com, staging.mastechnics.be ,, '],
            'https://mastechnics.be/nl'
        );

        $this->assertSame(['mastechnics.be', 'www.mastechnics.be', 'mastechnics.com', 'staging.mastechnics.be'], $hostnames);

        $this->assertSame(['www.example.org', 'example.org'], CaptchaVerifierFactory::expectedHostnames([], 'https://www.example.org'));
        $this->assertSame([], CaptchaVerifierFactory::expectedHostnames([], ''));
    }

    public function test_bound_verifier_checks_the_app_url_hostname(): void
    {
        config([
            'app.url' => 'https://mastechnics.be',
            'captcha.provider' => 'turnstile',
            'captcha.enabled' => true,
            'captcha.providers.turnstile.site_key' => 'site',
            'captcha.providers.turnstile.secret_key' => 'secret',
            'captcha.providers.turnstile.verify_url' => 'https://cf.test/siteverify',
        ]);
        $this->app->forgetInstance(CaptchaVerifier::class);

        /** @var CloudflareTurnstileVerifier $verifier */
        $verifier = $this->app->make(CaptchaVerifier::class);
        $this->assertSame(['mastechnics.be', 'www.mastechnics.be'], $verifier->expectedHostnames());

        $this->fakeSuccess(['hostname' => 'other.example']);
        $this->assertSame(CaptchaVerdict::HOSTNAME_MISMATCH, $verifier->verifyDetailed('tok', null, 'contact')->status);
    }

    public function test_turnstile_posts_secret_token_and_ip_and_accepts_success(): void
    {
        Http::fake(['cf.test/siteverify' => Http::response(['success' => true])]);

        $this->assertTrue($this->turnstile()->verify('tok-123', '203.0.113.7'));

        Http::assertSent(function (Request $request) {
            return $request->url() === 'https://cf.test/siteverify'
                && $request['secret'] === 'secret'
                && $request['response'] === 'tok-123'
                && $request['remoteip'] === '203.0.113.7';
        });
    }

    public function test_failure_response_is_rejected(): void
    {
        Http::fake(['cf.test/siteverify' => Http::response(['success' => false, 'error-codes' => ['invalid-input-response']])]);

        $this->assertFalse($this->turnstile()->verify('tok-123', null));
    }

    public function test_network_error_or_bad_http_status_fails_closed(): void
    {
        Http::fake(['cf.test/siteverify' => fn () => throw new ConnectionException('timeout')]);
        $this->assertFalse($this->turnstile()->verify('tok-123', null));

        Http::fake(['cf.test/siteverify' => Http::response('Bad gateway', 502)]);
        $this->assertFalse($this->turnstile()->verify('tok-123', null));

        Http::fake(['cf.test/siteverify' => Http::response('not json', 200)]);
        $this->assertFalse($this->turnstile()->verify('tok-123', null));
    }

    public function test_empty_or_oversized_token_is_refused_without_a_network_call(): void
    {
        Http::fake();

        $this->assertFalse($this->turnstile()->verify(null, null));
        $this->assertFalse($this->turnstile()->verify('   ', null));
        $this->assertFalse($this->turnstile()->verify(str_repeat('x', 2049), null));

        Http::assertNothingSent();
    }

    public function test_recaptcha_uses_the_same_contract_against_its_own_endpoint(): void
    {
        Http::fake(['google.test/siteverify' => Http::response(['success' => true])]);

        $verifier = new GoogleRecaptchaVerifier('gsite', 'gsecret', 'https://google.test/siteverify', 'https://google.test/api.js', 'g-recaptcha-response', 5);

        $this->assertTrue($verifier->verify('g-tok', '203.0.113.7'));
        $this->assertSame('recaptcha', $verifier->provider());

        Http::assertSent(fn (Request $request) => $request->url() === 'https://google.test/siteverify' && $request['secret'] === 'gsecret');
    }

    public function test_container_binding_follows_the_captcha_config(): void
    {
        config([
            'captcha.provider' => 'recaptcha',
            'captcha.enabled' => true,
            'captcha.providers.recaptcha.site_key' => 'gsite',
            'captcha.providers.recaptcha.secret_key' => 'gsecret',
        ]);
        $this->app->forgetInstance(CaptchaVerifier::class);

        $verifier = $this->app->make(CaptchaVerifier::class);

        $this->assertInstanceOf(GoogleRecaptchaVerifier::class, $verifier);
        $this->assertSame('g-recaptcha-response', $verifier->responseField());
    }
}
