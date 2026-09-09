<?php

namespace App\Services\Spam;

/**
 * Server-side verification of a bot-challenge response token.
 *
 * The public forms only trust this verdict — the widget on the page is
 * nothing more than the way the token gets into the POST. Implementations:
 * CloudflareTurnstileVerifier and GoogleRecaptchaVerifier (real siteverify
 * call), NullCaptchaVerifier (challenge disabled: local/testing without
 * keys) and RejectingCaptchaVerifier (required but not configured: fail
 * closed). Controllers and views never depend on a concrete provider.
 */
interface CaptchaVerifier
{
    /** Whether the challenge is active (widget rendered, token required). */
    public function enabled(): bool;

    /** Provider identifier the widget partial dispatches on: turnstile | recaptcha | none. */
    public function provider(): string;

    /** Public site key rendered into the widget ('' when disabled). */
    public function siteKey(): string;

    /** Name of the POST field the widget injects the token into. */
    public function responseField(): string;

    /** URL of the provider's widget script ('' when disabled). */
    public function scriptUrl(): string;

    /** True only when the provider confirms the token for this visitor (and our hostname). */
    public function verify(?string $token, ?string $ip): bool;

    /**
     * Full verdict: success plus hostname check and, when the provider
     * supports it and $expectedAction is given, the widget action check.
     * The trust evaluator reasons about this; verify() is its boolean view.
     */
    public function verifyDetailed(?string $token, ?string $ip, ?string $expectedAction = null): CaptchaVerdict;
}
