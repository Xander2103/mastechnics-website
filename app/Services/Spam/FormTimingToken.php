<?php

namespace App\Services\Spam;

/**
 * Signed "form opened at" timestamp rendered into a hidden field on every
 * GET of a public form. A submit that arrives faster than a human could
 * possibly fill the form is refused, as is a token that is missing,
 * tampered with or older than the maximum age. The HMAC uses APP_KEY, so
 * a bot cannot mint its own timestamp; a page refresh simply issues a new
 * one, and the browser's back button re-uses a still-valid one.
 */
class FormTimingToken
{
    public const OK = 'ok';

    public const MISSING = 'missing';

    public const INVALID = 'invalid';

    public const TOO_FAST = 'too_fast';

    public const EXPIRED = 'expired';

    public function __construct(
        private readonly string $appKey,
        private readonly int $minSeconds,
        private readonly int $maxSeconds,
    ) {
    }

    public function issue(string $form, ?int $now = null): string
    {
        $timestamp = $now ?? time();

        return $timestamp . '.' . $this->signature($form, $timestamp);
    }

    /** @return self::OK|self::MISSING|self::INVALID|self::TOO_FAST|self::EXPIRED */
    public function check(string $form, mixed $value, ?int $now = null): string
    {
        if (! is_string($value) || trim($value) === '') {
            return self::MISSING;
        }

        $parts = explode('.', trim($value), 2);

        if (count($parts) !== 2 || ! ctype_digit($parts[0]) || strlen($parts[0]) > 12) {
            return self::INVALID;
        }

        [$timestamp, $signature] = $parts;
        $timestamp = (int) $timestamp;

        if (! hash_equals($this->signature($form, $timestamp), $signature)) {
            return self::INVALID;
        }

        $elapsed = ($now ?? time()) - $timestamp;

        if ($elapsed < $this->minSeconds) {
            return self::TOO_FAST;
        }

        if ($elapsed > $this->maxSeconds) {
            return self::EXPIRED;
        }

        return self::OK;
    }

    /**
     * Timestamp a token was issued at, or null when it is missing or
     * malformed. Only meaningful after check() returned OK (the signature
     * is verified there); used to compute the fill time of a submission.
     */
    public function issuedAt(mixed $value): ?int
    {
        if (! is_string($value)) {
            return null;
        }

        $parts = explode('.', trim($value), 2);

        if (count($parts) !== 2 || ! ctype_digit($parts[0]) || strlen($parts[0]) > 12) {
            return null;
        }

        return (int) $parts[0];
    }

    private function signature(string $form, int $timestamp): string
    {
        return hash_hmac('sha256', $form . '|' . $timestamp, $this->appKey);
    }
}
