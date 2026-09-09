<?php

namespace App\Services\Spam;

/**
 * Full outcome of a server-side captcha check. "success = true" from the
 * provider is not enough on its own: the token must also have been issued
 * for one of our hostnames and (Turnstile) for the widget action of the
 * form it is posted to, otherwise a token bought from a solving service
 * or minted on another site would be accepted.
 *
 * Only statuses and booleans, never the token or the secret, so the
 * verdict can be written to the security log as-is.
 */
final class CaptchaVerdict
{
    public const PASSED = 'passed';

    public const FAILED = 'failed';

    public const MISSING = 'missing';

    public const DISABLED = 'disabled';

    public const HOSTNAME_MISMATCH = 'hostname_mismatch';

    public const ACTION_MISMATCH = 'action_mismatch';

    /**
     * @param  array<int, string>  $errorCodes  provider error codes, if any
     */
    public function __construct(
        public readonly string $status,
        public readonly ?bool $hostnameOk = null,
        public readonly ?bool $actionOk = null,
        public readonly ?int $challengeAgeSeconds = null,
        public readonly array $errorCodes = [],
    ) {
    }

    /** True only for a token the provider confirmed for our hostname/action. */
    public function passed(): bool
    {
        return $this->status === self::PASSED;
    }

    public static function disabled(): self
    {
        return new self(self::DISABLED);
    }

    public static function missing(): self
    {
        return new self(self::MISSING);
    }

    /** @param array<int, string> $codes */
    public static function failed(array $codes = []): self
    {
        return new self(self::FAILED, null, null, null, array_values(array_map('strval', $codes)));
    }
}
