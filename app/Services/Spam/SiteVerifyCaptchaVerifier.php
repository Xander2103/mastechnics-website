<?php

namespace App\Services\Spam;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Cloudflare Turnstile and Google reCAPTCHA share the same "siteverify"
 * contract (POST secret + response + remoteip → {"success": bool,
 * "hostname": ..., "challenge_ts": ..., ["action": ...]}), so the network
 * logic exists exactly once. Subclasses only supply URLs and names.
 *
 * A token is only PASSED when the provider says success AND the hostname
 * it was issued for is one of ours AND (Turnstile) the widget action
 * matches the form it is posted to. A solved token from another site or
 * form is therefore refused before any other layer runs.
 */
abstract class SiteVerifyCaptchaVerifier implements CaptchaVerifier
{
    /**
     * @param  array<int, string>  $expectedHostnames  lowercase hostnames the token may be issued for; empty = not checked
     */
    public function __construct(
        private readonly string $siteKey,
        private readonly string $secretKey,
        private readonly string $verifyUrl,
        private readonly string $scriptUrl,
        private readonly string $responseField,
        private readonly int $timeoutSeconds = 5,
        private readonly array $expectedHostnames = [],
        private readonly bool $verifyHostname = true,
        private readonly bool $verifyAction = true,
        private readonly bool $supportsAction = true,
        private readonly int $maxTokenAgeSeconds = 300,
    ) {
    }

    public function enabled(): bool
    {
        return true;
    }

    public function siteKey(): string
    {
        return $this->siteKey;
    }

    public function responseField(): string
    {
        return $this->responseField;
    }

    public function scriptUrl(): string
    {
        return $this->scriptUrl;
    }

    /** Whether the provider echoes a widget action in siteverify (Turnstile yes, reCAPTCHA v2 no). */
    public function supportsAction(): bool
    {
        return $this->supportsAction;
    }

    /** @return array<int, string> */
    public function expectedHostnames(): array
    {
        return $this->expectedHostnames;
    }

    public function maxTokenAgeSeconds(): int
    {
        return $this->maxTokenAgeSeconds;
    }

    public function verify(?string $token, ?string $ip): bool
    {
        return $this->verifyDetailed($token, $ip, null)->passed();
    }

    public function verifyDetailed(?string $token, ?string $ip, ?string $expectedAction = null): CaptchaVerdict
    {
        $token = trim((string) $token);

        // Tokens are opaque strings of bounded length; anything else is
        // refused without a network call.
        if ($token === '') {
            return CaptchaVerdict::missing();
        }

        if (strlen($token) > 2048) {
            return CaptchaVerdict::failed(['token_too_long']);
        }

        try {
            $response = Http::asForm()
                ->timeout($this->timeoutSeconds)
                ->post($this->verifyUrl, array_filter([
                    'secret' => $this->secretKey,
                    'response' => $token,
                    'remoteip' => $ip,
                ]));
        } catch (\Throwable $e) {
            // Network failure = fail closed. A bot must not get through
            // because the provider was briefly unreachable; a real visitor
            // sees the localized "try again" message.
            Log::warning('Captcha siteverify request failed', [
                'provider' => $this->provider(),
                'error' => $e->getMessage(),
            ]);

            return CaptchaVerdict::failed(['siteverify_unreachable']);
        }

        $body = $response->json();

        if (! $response->successful() || ! is_array($body) || ($body['success'] ?? false) !== true) {
            $codes = is_array($body) && is_array($body['error-codes'] ?? null) ? $body['error-codes'] : [];

            Log::info('Captcha token rejected', [
                'provider' => $this->provider(),
                'status' => $response->status(),
                'error_codes' => $codes,
            ]);

            return CaptchaVerdict::failed($codes !== [] ? $codes : ['http_' . $response->status()]);
        }

        $challengeAge = $this->challengeAge($body['challenge_ts'] ?? null);

        // Hostname: the token must have been issued for a widget served on
        // one of our domains. Absent/empty hostname counts as a mismatch.
        $hostnameOk = null;

        if ($this->verifyHostname && $this->expectedHostnames !== []) {
            $hostname = strtolower(trim((string) ($body['hostname'] ?? '')));
            $hostnameOk = $hostname !== '' && in_array($hostname, $this->expectedHostnames, true);

            if (! $hostnameOk) {
                Log::notice('Captcha token rejected: hostname/action mismatch', [
                    'provider' => $this->provider(),
                    'check' => 'hostname',
                    'expected' => $this->expectedHostnames,
                    'got' => $hostname,
                ]);

                return new CaptchaVerdict(CaptchaVerdict::HOSTNAME_MISMATCH, false, null, $challengeAge);
            }
        }

        // Action: Turnstile echoes the widget's data-action; the token must
        // belong to the form it is posted to (contact vs request).
        $actionOk = null;

        if ($this->supportsAction && $this->verifyAction && $expectedAction !== null) {
            $action = trim((string) ($body['action'] ?? ''));
            $actionOk = $action === $expectedAction;

            if (! $actionOk) {
                Log::notice('Captcha token rejected: hostname/action mismatch', [
                    'provider' => $this->provider(),
                    'check' => 'action',
                    'expected' => $expectedAction,
                    'got' => $action,
                ]);

                return new CaptchaVerdict(CaptchaVerdict::ACTION_MISMATCH, $hostnameOk, false, $challengeAge);
            }
        }

        return new CaptchaVerdict(CaptchaVerdict::PASSED, $hostnameOk, $actionOk, $challengeAge);
    }

    /** Seconds since the challenge was solved, from the provider's ISO 8601 timestamp. */
    private function challengeAge(mixed $challengeTs): ?int
    {
        if (! is_string($challengeTs) || trim($challengeTs) === '') {
            return null;
        }

        try {
            $solvedAt = new \DateTimeImmutable($challengeTs);
        } catch (\Throwable) {
            return null;
        }

        return max(0, time() - $solvedAt->getTimestamp());
    }
}
