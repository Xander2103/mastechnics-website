<?php

namespace Tests\Feature\Spam;

use App\Services\Spam\CaptchaVerifier;
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
    private function turnstile(): CloudflareTurnstileVerifier
    {
        return new CloudflareTurnstileVerifier('site', 'secret', 'https://cf.test/siteverify', 'https://cf.test/api.js', 'cf-turnstile-response', 5);
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
