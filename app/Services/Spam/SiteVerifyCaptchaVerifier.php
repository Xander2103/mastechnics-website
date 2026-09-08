<?php

namespace App\Services\Spam;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Cloudflare Turnstile and Google reCAPTCHA share the same "siteverify"
 * contract (POST secret + response + remoteip → {"success": bool}), so the
 * network logic exists exactly once. Subclasses only supply URLs and names.
 */
abstract class SiteVerifyCaptchaVerifier implements CaptchaVerifier
{
    public function __construct(
        private readonly string $siteKey,
        private readonly string $secretKey,
        private readonly string $verifyUrl,
        private readonly string $scriptUrl,
        private readonly string $responseField,
        private readonly int $timeoutSeconds = 5,
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

    public function verify(?string $token, ?string $ip): bool
    {
        $token = trim((string) $token);

        // Tokens are opaque strings of bounded length; anything else is
        // refused without a network call.
        if ($token === '' || strlen($token) > 2048) {
            return false;
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

            return false;
        }

        $body = $response->json();

        if (! $response->successful() || ! is_array($body) || ($body['success'] ?? false) !== true) {
            Log::info('Captcha token rejected', [
                'provider' => $this->provider(),
                'status' => $response->status(),
                'error_codes' => $body['error-codes'] ?? null,
            ]);

            return false;
        }

        return true;
    }
}
