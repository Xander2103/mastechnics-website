<?php

namespace App\Services\Spam;

use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Application-wide budget for form-triggered transactional mail — the
 * circuit breaker that keeps a bot wave from consuming the 300/day Brevo
 * quota even when every other layer let it through.
 *
 * Two layers of counters, checked in this order under one cache lock:
 *
 *   1. the joint external-mail circuit breaker: ALL mails the public forms
 *      cause (admin notification + customer confirmation, both forms) share
 *      one 10-minute counter (FORM_EXTERNAL_MAIL_LIMIT_PER_10_MINUTES) and
 *      one daily counter (FORM_EXTERNAL_MAIL_DAILY_LIMIT). Once either is
 *      full: zero further provider calls from the public forms until the
 *      window rolls over. Submissions are stored regardless.
 *   2. the per-kind budgets of sprint 20 (customer/day, admin/day, all/hour)
 *      as a second net.
 *
 * A send first reserves a slot; reservation = check + increment under a
 * cache lock so two concurrent requests cannot both take the last slot.
 * The budget only ever suppresses a mail — a submission is stored regardless.
 */
class MailBudget
{
    public const KIND_CUSTOMER = 'customer';

    public const KIND_ADMIN = 'admin';

    private const LOCK_SECONDS = 5;

    private const LOCK_WAIT_SECONDS = 3;

    private const CIRCUIT_BURST_WINDOW = 600;

    private const CIRCUIT_DAY_WINDOW = 86400;

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
     * go out, or the FormProtectionLog reason when a counter is exhausted.
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

            return $this->budgetReason($kind);
        } catch (\Throwable $e) {
            // Store without lock support: the RateLimiter's own increment is
            // still atomic on the database/file stores, so degrade to that.
            Log::debug('Mail budget lock unavailable, reserving without lock', ['error' => $e->getMessage()]);

            try {
                return $this->reserveUnlocked($kind);
            } catch (\Throwable $inner) {
                // Cache store down altogether: the submission is already
                // stored, so fail closed on the mail (skip + log) rather than
                // throw a 500 or send unbudgeted.
                Log::error('Mail budget unavailable; send refused', ['kind' => $kind, 'error' => $inner->getMessage()]);

                return $this->budgetReason($kind);
            }
        }
    }

    /** Remaining slots per counter, for the admin dashboard. @return array<string, int> */
    public function remaining(): array
    {
        return [
            self::KIND_CUSTOMER => max(0, $this->dailyLimit(self::KIND_CUSTOMER) - $this->attempts($this->dailyKey(self::KIND_CUSTOMER))),
            self::KIND_ADMIN => max(0, $this->dailyLimit(self::KIND_ADMIN) - $this->attempts($this->dailyKey(self::KIND_ADMIN))),
            'burst' => max(0, $this->burstLimit() - $this->attempts(self::burstKey())),
            'circuit_daily' => max(0, $this->circuitDailyLimit() - $this->attempts(self::circuitDailyKey())),
            'circuit_burst' => max(0, $this->circuitBurstLimit() - $this->attempts(self::circuitBurstKey())),
        ];
    }

    /**
     * State of the joint external-mail circuit breaker for the dashboard.
     *
     * @return array{enabled: bool, open: bool, reason: ?string, daily_used: int, daily_limit: int, burst_used: int, burst_limit: int, resets_at: ?Carbon}
     */
    public function circuitState(): array
    {
        $dailyUsed = $this->attempts(self::circuitDailyKey());
        $burstUsed = $this->attempts(self::circuitBurstKey());
        $dailyLimit = $this->circuitDailyLimit();
        $burstLimit = $this->circuitBurstLimit();

        $reason = null;
        $resetsAt = null;

        if ($this->enabled()) {
            if ($dailyUsed >= $dailyLimit) {
                $reason = FormProtectionLog::MAIL_CIRCUIT_DAILY;
                $resetsAt = $this->availableAt(self::circuitDailyKey());
            } elseif ($burstUsed >= $burstLimit) {
                $reason = FormProtectionLog::MAIL_CIRCUIT_BURST;
                $resetsAt = $this->availableAt(self::circuitBurstKey());
            }
        }

        return [
            'enabled' => $this->enabled(),
            'open' => $reason !== null,
            'reason' => $reason,
            'daily_used' => $dailyUsed,
            'daily_limit' => $dailyLimit,
            'burst_used' => $burstUsed,
            'burst_limit' => $burstLimit,
            'resets_at' => $resetsAt,
        ];
    }

    private function reserveUnlocked(string $kind): ?string
    {
        // 1. Joint circuit breaker — every form-related external mail, any kind.
        if (RateLimiter::tooManyAttempts(self::circuitBurstKey(), $this->circuitBurstLimit())) {
            return FormProtectionLog::MAIL_CIRCUIT_BURST;
        }

        if (RateLimiter::tooManyAttempts(self::circuitDailyKey(), $this->circuitDailyLimit())) {
            return FormProtectionLog::MAIL_CIRCUIT_DAILY;
        }

        // 2. Per-kind budgets.
        if (RateLimiter::tooManyAttempts(self::burstKey(), $this->burstLimit())) {
            return FormProtectionLog::MAIL_BUDGET_BURST;
        }

        if (RateLimiter::tooManyAttempts($this->dailyKey($kind), $this->dailyLimit($kind))) {
            return $this->budgetReason($kind);
        }

        RateLimiter::hit(self::circuitBurstKey(), self::CIRCUIT_BURST_WINDOW);
        RateLimiter::hit(self::circuitDailyKey(), self::CIRCUIT_DAY_WINDOW);
        RateLimiter::hit(self::burstKey(), 3600);
        RateLimiter::hit($this->dailyKey($kind), 86400);

        return null;
    }

    private function budgetReason(string $kind): string
    {
        return $kind === self::KIND_CUSTOMER
            ? FormProtectionLog::MAIL_BUDGET_CUSTOMER
            : FormProtectionLog::MAIL_BUDGET_ADMIN;
    }

    private function attempts(string $key): int
    {
        try {
            return (int) RateLimiter::attempts($key);
        } catch (\Throwable $e) {
            return 0;
        }
    }

    private function availableAt(string $key): ?Carbon
    {
        try {
            $seconds = RateLimiter::availableIn($key);

            return $seconds > 0 ? now()->addSeconds($seconds) : null;
        } catch (\Throwable $e) {
            return null;
        }
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

    private function circuitDailyLimit(): int
    {
        return max(0, (int) ($this->config['external_daily_limit'] ?? 20));
    }

    private function circuitBurstLimit(): int
    {
        return max(0, (int) ($this->config['external_limit_per_10_minutes'] ?? 4));
    }

    private function dailyKey(string $kind): string
    {
        return "form-protection:mail-budget:{$kind}:day";
    }

    private static function burstKey(): string
    {
        return 'form-protection:mail-budget:burst';
    }

    private static function circuitDailyKey(): string
    {
        return 'form-protection:mail-circuit:day';
    }

    private static function circuitBurstKey(): string
    {
        return 'form-protection:mail-circuit:burst';
    }
}
