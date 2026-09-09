<?php

namespace App\Services\Spam\Trust;

use Illuminate\Support\Facades\Cache;

/**
 * Does the sender's domain accept mail at all? A domain without MX/A/AAAA
 * records is a strong bot signal (random "user@xkq81.com" addresses). The
 * lookup is cached for a day per domain; any failure is "unknown" (null),
 * never a negative — DNS trouble must not push real customers to review.
 *
 * The test suite never touches DNS: under PHPUnit the answer is null unless
 * dns_check_in_tests is on, and fake() can pin answers per domain.
 */
final class EmailDomainCheck
{
    /** @var array<string, bool>|null */
    private static ?array $fake = null;

    /** @param array<string, mixed> $config config('form-protection.trust') */
    public function __construct(private readonly array $config)
    {
    }

    /** @param array<string, bool> $results domain => has mail server */
    public static function fake(?array $results): void
    {
        self::$fake = $results === null ? null : array_change_key_case($results, CASE_LOWER);
    }

    public function hasMailServer(string $domain): ?bool
    {
        $domain = strtolower(trim($domain));

        if ($domain === '' || ! str_contains($domain, '.')) {
            return null;
        }

        if (self::$fake !== null) {
            return self::$fake[$domain] ?? null;
        }

        if (! (bool) ($this->config['email']['dns_check'] ?? true)) {
            return null;
        }

        if (app()->runningUnitTests() && ! (bool) ($this->config['email']['dns_check_in_tests'] ?? false)) {
            return null;
        }

        try {
            return (bool) Cache::remember(
                'form-protection:mx:' . $domain,
                86400,
                fn (): bool => @checkdnsrr($domain, 'MX') || @checkdnsrr($domain, 'A') || @checkdnsrr($domain, 'AAAA')
            );
        } catch (\Throwable $e) {
            return null;
        }
    }
}
