<?php

namespace App\Services\Spam\Trust\Signals;

use App\Services\Spam\RecipientSafety;
use App\Services\Spam\Trust\EmailDomainCheck;
use App\Services\Spam\Trust\SignalProvider;
use App\Services\Spam\Trust\TrustContext;
use App\Services\Spam\Trust\TrustSignal;
use Illuminate\Support\Str;

/**
 * Does the address look like a customer address? Disposable domains,
 * domains without a mail server and random-looking local parts
 * ("k8q2zx91@") are bot traits; an address that contains the name the
 * visitor typed is a human one.
 */
final class EmailSignals implements SignalProvider
{
    /** @param array<string, mixed> $config */
    public function __construct(
        private readonly array $config,
        private readonly EmailDomainCheck $domainCheck,
    ) {
    }

    public function collect(TrustContext $ctx): array
    {
        $group = TrustSignal::GROUP_EMAIL;
        $email = RecipientSafety::normalize($ctx->facts->email);

        if (! str_contains($email, '@')) {
            return [];
        }

        [$local, $domain] = explode('@', $email, 2);
        $signals = [];
        $suspicious = false;

        $disposable = array_map('strtolower', (array) ($this->config['email']['disposable_domains'] ?? []));

        if (in_array($domain, $disposable, true)) {
            $signals[] = TrustSignal::risk('email_disposable', $group, 3);
            $suspicious = true;
        }

        if (self::looksRandom($local)) {
            $signals[] = TrustSignal::risk('email_random_local_part', $group, 2);
            $suspicious = true;
        }

        if ($this->domainCheck->hasMailServer($domain) === false) {
            $signals[] = TrustSignal::risk('email_domain_no_mx', $group, 3);
            $suspicious = true;
        }

        if (! $suspicious) {
            $signals[] = TrustSignal::positive('email_plausible', $group);
        }

        if ($this->nameMatches($ctx->facts->name, $local)) {
            $signals[] = TrustSignal::positive('email_matches_name', $group);
        }

        return $signals;
    }

    public static function looksRandom(string $local): bool
    {
        $local = strtolower($local);
        $length = strlen($local);

        if ($length >= 6) {
            $digits = preg_match_all('/\d/', $local);

            // Mostly digits ("83920174@"), or letters and digits interleaved
            // ("k8q2zx91@"): four or more letter↔digit switches. A year or
            // birthday suffix ("marie2024", "jan1985") switches only once.
            if ($digits / $length >= 0.6 || preg_match_all('/(?=[a-z]\d|\d[a-z])/', $local) >= 4) {
                return true;
            }
        }

        $letters = preg_replace('/[^a-z]/', '', $local) ?? '';

        if (strlen($letters) >= 12 && preg_match('/[aeiouy]/', $letters) !== 1) {
            return true;
        }

        return preg_match('/[bcdfghjklmnpqrstvwxz]{6,}/', $letters) === 1;
    }

    private function nameMatches(?string $name, string $local): bool
    {
        if ($name === null || trim($name) === '') {
            return false;
        }

        $ascii = strtolower(Str::ascii($name));
        $localAscii = strtolower(Str::ascii($local));

        foreach (preg_split('/[^a-z]+/', $ascii, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $word) {
            if (strlen($word) >= 3 && str_contains($localAscii, $word)) {
                return true;
            }
        }

        return false;
    }
}
