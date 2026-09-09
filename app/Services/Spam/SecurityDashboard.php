<?php

namespace App\Services\Spam;

use App\Models\ContactSubmission;
use App\Models\CustomerRequest;
use App\Models\FormSecurityEvent;
use App\Services\Spam\Trust\TrustDecision;
use Illuminate\Support\Facades\Log;

/**
 * Numbers behind the "Formulierbeveiliging" tile and the security-log
 * page. Every query is wrapped: the admin dashboard must render even when
 * the security log table is missing or the cache is down — monitoring
 * never takes the admin down.
 */
class SecurityDashboard
{
    public function __construct(private readonly MailBudget $budget)
    {
    }

    /**
     * @return array{
     *     blocked_today: int, needs_review_today: int, trusted_today: int,
     *     mails_prevented_today: int, external_mails_today: int, external_mail_limit: int,
     *     needs_review_open: int,
     *     circuit: array<string, mixed>,
     *     attack: array{active: bool, last_10_minutes: int, last_hour: int, threshold_10_minutes: int, threshold_hour: int}
     * }
     */
    public function summary(): array
    {
        $decisions = $this->guard(fn () => FormSecurityEvent::query()->today()
            ->selectRaw('decision, count(*) as total')
            ->groupBy('decision')
            ->pluck('total', 'decision')
            ->all(), []);

        $prevented = $this->guard(fn () => (int) FormSecurityEvent::query()->today()->sum('mails_prevented'), 0);

        $last10 = $this->guard(fn () => FormSecurityEvent::query()
            ->where('occurred_at', '>=', now()->subMinutes(10))
            ->where('decision', '!=', TrustDecision::TRUSTED)
            ->count(), 0);

        $lastHour = $this->guard(fn () => FormSecurityEvent::query()
            ->where('occurred_at', '>=', now()->subHour())
            ->where('decision', '!=', TrustDecision::TRUSTED)
            ->count(), 0);

        $open = $this->guard(fn () => CustomerRequest::where('trust_verdict', TrustDecision::NEEDS_REVIEW)->count()
            + ContactSubmission::where('trust_verdict', TrustDecision::NEEDS_REVIEW)->count(), 0);

        $circuit = $this->guard(fn () => $this->budget->circuitState(), [
            'enabled' => false, 'open' => false, 'reason' => null,
            'daily_used' => 0, 'daily_limit' => 0, 'burst_used' => 0, 'burst_limit' => 0, 'resets_at' => null,
        ]);

        $threshold10 = max(1, (int) config('form-protection.trust.velocity.attack_per_10_minutes', 10));
        $thresholdHour = max(1, (int) config('form-protection.trust.velocity.attack_per_hour', 25));

        return [
            'blocked_today' => (int) ($decisions[TrustDecision::BLOCKED] ?? 0),
            'needs_review_today' => (int) ($decisions[TrustDecision::NEEDS_REVIEW] ?? 0),
            'trusted_today' => (int) ($decisions[TrustDecision::TRUSTED] ?? 0),
            'mails_prevented_today' => $prevented,
            'external_mails_today' => (int) ($circuit['daily_used'] ?? 0),
            'external_mail_limit' => (int) ($circuit['daily_limit'] ?? 0),
            'needs_review_open' => $open,
            'circuit' => $circuit,
            'attack' => [
                'active' => $last10 >= $threshold10 || $lastHour >= $thresholdHour,
                'last_10_minutes' => $last10,
                'last_hour' => $lastHour,
                'threshold_10_minutes' => $threshold10,
                'threshold_hour' => $thresholdHour,
            ],
        ];
    }

    private function guard(callable $query, mixed $fallback): mixed
    {
        try {
            return $query();
        } catch (\Throwable $e) {
            Log::debug('security dashboard query unavailable', ['error' => $e->getMessage()]);

            return $fallback;
        }
    }
}
