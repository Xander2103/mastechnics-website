<?php

namespace App\Services\Spam;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Safe monitoring of the anti-abuse layers: one structured log line per
 * rejection / skipped mail (hashed IP and e-mail, never message bodies or
 * secrets) plus per-day counters in the cache so the admin dashboard and
 * `php artisan forms:protection-stats` can show how much was stopped.
 */
class FormProtectionLog
{
    public const REASON_DISABLED = 'form_disabled';

    public const REASON_ATTEMPTS = 'rate_limit_attempts';

    public const REASON_CAPTCHA = 'captcha_failed';

    public const REASON_HONEYPOT = 'honeypot';

    public const REASON_TIMING = 'fill_time';

    public const REASON_IP_LIMIT = 'rate_limit_ip';

    public const REASON_EMAIL_LIMIT = 'rate_limit_email';

    public const REASON_FORM_LIMIT = 'rate_limit_form';

    public const REASON_GLOBAL_BURST = 'rate_limit_global';

    public const REASON_DUPLICATE = 'duplicate';

    public const REASON_BLOCKLIST = 'blocklist';

    public const MAIL_CUSTOMER_DISABLED = 'mail_customer_disabled';

    public const MAIL_UNSAFE_RECIPIENT = 'mail_unsafe_recipient';

    public const MAIL_BUDGET_CUSTOMER = 'mail_budget_customer';

    public const MAIL_BUDGET_ADMIN = 'mail_budget_admin';

    public const MAIL_BUDGET_BURST = 'mail_budget_burst';

    public const MAIL_CIRCUIT_DAILY = 'mail_circuit_daily';

    public const MAIL_CIRCUIT_BURST = 'mail_circuit_burst';

    public const MAIL_NOT_TRUSTED = 'mail_not_trusted';

    /** Every counter key, in the order the dashboard lists them. */
    public const REASONS = [
        self::REASON_CAPTCHA,
        self::REASON_HONEYPOT,
        self::REASON_TIMING,
        self::REASON_ATTEMPTS,
        self::REASON_IP_LIMIT,
        self::REASON_EMAIL_LIMIT,
        self::REASON_FORM_LIMIT,
        self::REASON_GLOBAL_BURST,
        self::REASON_DUPLICATE,
        self::REASON_BLOCKLIST,
        self::REASON_DISABLED,
        self::MAIL_CUSTOMER_DISABLED,
        self::MAIL_UNSAFE_RECIPIENT,
        self::MAIL_BUDGET_CUSTOMER,
        self::MAIL_BUDGET_ADMIN,
        self::MAIL_BUDGET_BURST,
        self::MAIL_CIRCUIT_DAILY,
        self::MAIL_CIRCUIT_BURST,
        self::MAIL_NOT_TRUSTED,
    ];

    public const LABELS = [
        self::REASON_CAPTCHA => 'Captcha mislukt/ontbreekt',
        self::REASON_HONEYPOT => 'Honeypot',
        self::REASON_TIMING => 'Te snel ingevuld',
        self::REASON_ATTEMPTS => 'Te veel pogingen (429)',
        self::REASON_IP_LIMIT => 'IP-limiet',
        self::REASON_EMAIL_LIMIT => 'E-maillimiet',
        self::REASON_FORM_LIMIT => 'Daglimiet formulier',
        self::REASON_GLOBAL_BURST => 'Globale burst-limiet',
        self::REASON_DUPLICATE => 'Herhaalde inzending',
        self::REASON_BLOCKLIST => 'Geblokkeerd adres',
        self::REASON_DISABLED => 'Formulier uitgeschakeld',
        self::MAIL_CUSTOMER_DISABLED => 'Klantmail uitgeschakeld',
        self::MAIL_UNSAFE_RECIPIENT => 'Onveilig ontvangeradres',
        self::MAIL_BUDGET_CUSTOMER => 'Klantmailbudget bereikt',
        self::MAIL_BUDGET_ADMIN => 'Adminmailbudget bereikt',
        self::MAIL_BUDGET_BURST => 'Mail-burstlimiet bereikt',
        self::MAIL_CIRCUIT_DAILY => 'Noodrem: daglimiet externe mails',
        self::MAIL_CIRCUIT_BURST => 'Noodrem: burstlimiet externe mails',
        self::MAIL_NOT_TRUSTED => 'Mail overgeslagen: niet vertrouwd',
    ];

    private const COUNTER_TTL_DAYS = 8;

    public function rejected(string $form, string $reason, Request $request, ?string $email = null): void
    {
        Log::notice('form_protection.rejected', array_filter([
            'form' => $form,
            'reason' => $reason,
            'ip_hash' => self::hashIp($request->ip()),
            'email_hash' => $email !== null && $email !== '' ? substr(RecipientSafety::hash($email), 0, 16) : null,
            'locale' => $request->segment(1),
        ]));

        $this->count($form, $reason);
    }

    public function mailSkipped(string $form, string $kind, string $reason, ?int $customerRequestId = null): void
    {
        Log::warning('form_protection.mail_skipped', array_filter([
            'form' => $form,
            'mail' => $kind,
            'reason' => $reason,
            'customer_request_id' => $customerRequestId,
        ]));

        $this->count($form, $reason);
    }

    /**
     * Counters for today and for the last N days (today included).
     *
     * @return array{today: array<string, int>, today_total: int, period: array<string, int>, period_total: int, days: int}
     */
    public function summary(int $days = 7): array
    {
        $today = [];
        $period = [];

        foreach (self::REASONS as $reason) {
            $today[$reason] = 0;
            $period[$reason] = 0;
        }

        // One round trip for every counter (days × reasons × forms) instead
        // of hundreds of single gets on the database cache store.
        $keys = [];

        for ($offset = 0; $offset < $days; $offset++) {
            $date = now()->subDays($offset)->format('Ymd');

            foreach (self::REASONS as $reason) {
                foreach (['contact', 'request'] as $form) {
                    $keys[self::counterKey($date, $form, $reason)] = [$offset, $reason];
                }
            }
        }

        try {
            $values = Cache::many(array_keys($keys));
        } catch (\Throwable $e) {
            Log::debug('form_protection counters unavailable', ['error' => $e->getMessage()]);
            $values = [];
        }

        foreach ($keys as $key => [$offset, $reason]) {
            $value = (int) ($values[$key] ?? 0);
            $period[$reason] += $value;

            if ($offset === 0) {
                $today[$reason] += $value;
            }
        }

        return [
            'today' => $today,
            'today_total' => array_sum($today),
            'period' => $period,
            'period_total' => array_sum($period),
            'days' => $days,
        ];
    }

    public static function hashIp(?string $ip): string
    {
        return substr(hash('sha256', (string) $ip . '|' . (string) config('app.key')), 0, 16);
    }

    private function count(string $form, string $reason): void
    {
        $key = self::counterKey(now()->format('Ymd'), $form, $reason);

        try {
            Cache::add($key, 0, now()->addDays(self::COUNTER_TTL_DAYS));
            Cache::increment($key);
        } catch (\Throwable $e) {
            // Monitoring must never take a form down.
            Log::debug('form_protection counter unavailable', ['error' => $e->getMessage()]);
        }
    }

    private static function counterKey(string $date, string $form, string $reason): string
    {
        return "form-protection:stats:{$date}:{$form}:{$reason}";
    }
}
