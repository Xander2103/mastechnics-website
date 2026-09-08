<?php

namespace App\Services\Spam;

use Illuminate\Support\Str;

/**
 * Last check before an address becomes a mail recipient or a limiter key.
 * Validation already ran, but the address also flows into headers and
 * counters, so it is re-checked in one place, close to the send.
 */
class RecipientSafety
{
    public const MAX_LENGTH = 254;

    public static function isSafe(?string $email): bool
    {
        $email = trim((string) $email);

        if ($email === '' || strlen($email) > self::MAX_LENGTH) {
            return false;
        }

        // Header injection / malformed: no control characters, no whitespace,
        // no angle brackets or commas that could smuggle a second recipient.
        if (preg_match('/[\x00-\x20\x7f<>,;"()\[\]\\\\]/', $email) === 1) {
            return false;
        }

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return false;
        }

        $domain = substr($email, strrpos($email, '@') + 1);

        // Bare hostnames ("user@localhost") are valid RFC but never a
        // customer: require a dotted domain.
        return str_contains($domain, '.') && ! str_starts_with($domain, '.') && ! str_ends_with($domain, '.');
    }

    /**
     * "J.A.N+tag@Gmail.com" and "jan@gmail.com" are the same inbox; a bot
     * that rotates dots/plus-tags must hit the same counter.
     */
    public static function normalize(string $email): string
    {
        $email = Str::lower(trim($email));

        if (! str_contains($email, '@')) {
            return $email;
        }

        [$local, $domain] = explode('@', $email, 2);
        $local = preg_replace('/\+.*$/', '', $local) ?? $local;

        if (in_array($domain, ['gmail.com', 'googlemail.com'], true)) {
            $local = str_replace('.', '', $local);
            $domain = 'gmail.com';
        }

        return $local . '@' . $domain;
    }

    /** Stable, non-reversible key for counters and logs. */
    public static function hash(string $email): string
    {
        return hash('sha256', self::normalize($email));
    }
}
