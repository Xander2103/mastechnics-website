<?php

namespace App\Services\Spam;

use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Application-wide budget for form-triggered transactional mail — the
 * circuit breaker that keeps a bot wave from consuming the 300/day Brevo
 * quota even when every other layer let it through.
 *
 * Three rolling counters: customer confirmations per day, admin
 * notifications per day, and all form mails together per hour. A send
 * first reserves a slot; reservation = check + increment under a cache
 * lock so two concurrent requests cannot both take the last slot. The
 * budget only ever suppresses a mail — a submission is stored regardless.
 */
class MailBudget
{
    public const KIND_CUSTOMER = 'customer';

    public const KIND_ADMIN = 'admin';

    private const LOCK_SECONDS = 5;

    private const LOCK_WAIT_SECONDS = 3;

    /** @param array<string, mixed> $config config('form-protection.mail') */
    public function __construct(private readonly array $config)
    {
    }

    public function enabled(): bool
    {
        return (bool) ($this->config['guard_enabled'] ?? true);
    }

    /**
     * Reserve one send of the given kind. Returns null when the mail may
     * go out, or the FormProtectionLog reason when the budget is exhausted.
     */
    public function reserve(string $kind): ?string
    {
        if (! $this->enabled()) {
            return null;
        }

        try {
            return Cache::lock('form-protection:mail-budget', self::LOCK_SECONDS)
                ->block(self::LOCK_WAIT_SECONDS, fn () => $this->reserveUnlocked($kind));
        } catch (LockTimeoutException $e) {
            // Could not get the lock in time: refuse rather than risk
            // overshooting the quota (the submission itself is safe).
            Log::warning('Mail budget lock timeout; send refused', ['kind' => $kind]);

            return $kind === self::KIND_CUSTOMER
                ? FormProtectionLog::MAIL_BUDGET_CUSTOMER
                : FormProtectionLog::MAIL_BUDGET_ADMIN;
        } catch (\Throwable $e) {
            // Store without lock support: the RateLimiter's own increment is
            // still atomic on the database/file stores, so degrade to that.
            Log::debug('Mail budget lock unavailable, reserving without lock', ['error' => $e->getMessage()]);

            return $this->reserveUnlocked($kind);
        }
    }

    /** Remaining slots per counter, for the admin dashboard. @return array<string, int> */
    public function remaining(): array
    {
        return [
            self::KIND_CUSTOMER => max(0, $this->dailyLimit(self::KIND_CUSTOMER) - RateLimiter::attempts($this->dailyKey(self::KIND_CUSTOMER))),
            self::KIND_ADMIN => max(0, $this->dailyLimit(self::KIND_ADMIN) - RateLimiter::attempts($this->dailyKey(self::KIND_ADMIN))),
            'burst' => max(0, $this->burstLimit() - RateLimiter::attempts(self::burstKey())),
        ];
    }

    private function reserveUnlocked(string $kind): ?string
    {
        if (RateLimiter::tooManyAttempts(self::burstKey(), $this->burstLimit())) {
            return FormProtectionLog::MAIL_BUDGET_BURST;
        }

        if (RateLimiter::tooManyAttempts($this->dailyKey($kind), $this->dailyLimit($kind))) {
            return $kind === self::KIND_CUSTOMER
                ? FormProtectionLog::MAIL_BUDGET_CUSTOMER
                : FormProtectionLog::MAIL_BUDGET_ADMIN;
        }

        RateLimiter::hit(self::burstKey(), 3600);
        RateLimiter::hit($this->dailyKey($kind), 86400);

        return null;
    }

    private function dailyLimit(string $kind): int
    {
        return $kind === self::KIND_CUSTOMER
            ? (int) ($this->config['customer_confirmation_daily_limit'] ?? 100)
            : (int) ($this->config['admin_notification_daily_limit'] ?? 150);
    }

    private function burstLimit(): int
    {
        return (int) ($this->config['burst_limit_per_hour'] ?? 20);
    }

    private function dailyKey(string $kind): string
    {
        return "form-protection:mail-budget:{$kind}:day";
    }

    private static function burstKey(): string
    {
        return 'form-protection:mail-budget:burst';
    }
}
