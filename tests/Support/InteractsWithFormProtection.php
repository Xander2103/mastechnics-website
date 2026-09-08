<?php

namespace Tests\Support;

use App\Services\Spam\CaptchaVerifier;
use App\Services\Spam\FormTimingToken;
use Illuminate\Support\Facades\Mail;

/**
 * Helpers for tests of the public forms: a real (spy) mail transport, a
 * fake captcha provider and a valid fill-time token, so a test can build
 * a submission that passes every layer and then remove exactly one.
 */
trait InteractsWithFormProtection
{
    protected ?SpyMailTransport $mailTransport = null;

    protected ?FakeCaptchaVerifier $captcha = null;

    /**
     * Route every mail through the spy transport. Whatever Mail::send()
     * hands to the transport is counted as one provider call.
     */
    protected function useSpyMailTransport(): SpyMailTransport
    {
        $this->mailTransport = new SpyMailTransport();
        $transport = $this->mailTransport;

        config(['mail.default' => 'spy', 'mail.mailers.spy' => ['transport' => 'spy']]);
        Mail::extend('spy', fn () => $transport);
        Mail::forgetMailers();

        return $transport;
    }

    protected function assertProviderCalls(int $expected, string $message = ''): void
    {
        $this->assertNotNull($this->mailTransport, 'useSpyMailTransport() was not called');
        $this->assertSame(
            $expected,
            $this->mailTransport->calls(),
            $message !== '' ? $message : "Expected {$expected} mail provider call(s), got {$this->mailTransport->calls()}"
        );
    }

    protected function useFakeCaptcha(bool $enabled = true): FakeCaptchaVerifier
    {
        $this->captcha = new FakeCaptchaVerifier(enabled: $enabled);
        $this->app->instance(CaptchaVerifier::class, $this->captcha);

        return $this->captcha;
    }

    /** A fill-time token that already satisfies the minimum fill time. */
    protected function timingToken(string $form, int $secondsAgo = 30): string
    {
        return $this->app->make(FormTimingToken::class)->issue($form, time() - $secondsAgo);
    }

    /** Timing field + (when the fake captcha is active) a valid captcha token. */
    protected function protectionFields(string $form, array $overrides = []): array
    {
        $fields = [config('form-protection.timing.field') => $this->timingToken($form)];

        if ($this->captcha !== null && $this->captcha->enabled()) {
            $fields[$this->captcha->responseField()] = FakeCaptchaVerifier::VALID_TOKEN;
        }

        return array_merge($fields, $overrides);
    }
}
