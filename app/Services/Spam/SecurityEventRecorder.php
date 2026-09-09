<?php

namespace App\Services\Spam;

use App\Models\FormSecurityEvent;
use App\Services\Spam\Trust\TrustContext;
use App\Services\Spam\Trust\TrustDecision;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Writes one FormSecurityEvent per trust decision. Monitoring must never
 * change the outcome of a submission: every public method swallows every
 * Throwable (logged as an error) — a failing security log can therefore
 * never cause a 500, a second mail or a different decision.
 *
 * Failure mode: the submission is processed exactly as without the log;
 * only the event row is lost and `security_log.write_failed` appears in
 * laravel.log.
 *
 * Privacy: hashed IP (16 hex, keyed with APP_KEY), masked + hashed e-mail,
 * parsed user-agent family. No tokens, secrets, headers, names or bodies.
 */
class SecurityEventRecorder
{
    /**
     * @param  array{admin?: string, customer?: string, reason?: ?string, sent?: int, skipped?: int}  $mail  FormMailer::lastOutcome()
     */
    public function record(TrustContext $context, TrustDecision $decision, ?Model $subject = null, array $mail = []): ?FormSecurityEvent
    {
        try {
            return FormSecurityEvent::create($this->attributes($context, $decision, $subject, $mail));
        } catch (\Throwable $e) {
            Log::error('security_log.write_failed', [
                'form' => $context->form,
                'decision' => $decision->verdict,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * An event without a TrustContext: rejections that happen before the
     * evaluator runs (attempt limiter, disabled form, blocklist) and manual
     * admin decisions.
     *
     * @param  array<int, string>  $reasons
     */
    public function recordSimple(
        string $form,
        string $decision,
        array $reasons,
        ?Request $request = null,
        ?string $email = null,
        ?Model $subject = null,
        array $mail = [],
        int $mailsPrevented = 0,
    ): ?FormSecurityEvent {
        try {
            return FormSecurityEvent::create(array_merge([
                'occurred_at' => now(),
                'form' => $form,
                'decision' => $decision,
                'risk_score' => 0,
                'risk_level' => $decision === TrustDecision::BLOCKED ? TrustDecision::RISK_HIGH : TrustDecision::RISK_LOW,
                'reasons' => array_values($reasons),
                'signals' => [],
                'attack_mode' => false,
                'mails_prevented' => $mailsPrevented,
                'ip_hash' => $request !== null ? FormProtectionLog::hashIp($request->ip()) : null,
                'email_masked' => self::maskEmail($email),
                'email_hash' => self::hashEmail($email),
                'locale' => $request?->segment(1),
                'user_agent_family' => $request !== null ? self::userAgentFamily($request->userAgent()) : null,
                'subject_type' => $subject?->getMorphClass(),
                'subject_id' => $subject?->getKey(),
            ], $this->mailAttributes($mail)));
        } catch (\Throwable $e) {
            Log::error('security_log.write_failed', ['form' => $form, 'decision' => $decision, 'error' => $e->getMessage()]);

            return null;
        }
    }

    /** @return array<string, mixed> */
    private function attributes(TrustContext $context, TrustDecision $decision, ?Model $subject, array $mail): array
    {
        $request = $context->request;
        $email = $context->facts->email;

        return array_merge([
            'occurred_at' => now(),
            'form' => $context->form,
            'decision' => $decision->verdict,
            'risk_score' => $decision->risk,
            'risk_level' => $decision->riskLevel(),
            'reasons' => $decision->reasons(),
            'signals' => $decision->signalsByGroup(),
            'captcha_status' => $context->captcha->status,
            'captcha_hostname_ok' => $context->captcha->hostnameOk,
            'captcha_action_ok' => $context->captcha->actionOk,
            'fill_time_bucket' => self::fillTimeBucket($context),
            'fill_time_seconds' => $context->fillSeconds !== null ? max(0, min(4294967295, $context->fillSeconds)) : null,
            'attack_mode' => $decision->attackMode,
            'mails_prevented' => $this->mailsPrevented($decision, $mail),
            'ip_hash' => FormProtectionLog::hashIp($request->ip()),
            'email_masked' => self::maskEmail($email),
            'email_hash' => self::hashEmail($email),
            'locale' => $request->segment(1),
            'user_agent_family' => self::userAgentFamily($request->userAgent()),
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
        ], $this->mailAttributes($mail));
    }

    /** @return array<string, mixed> */
    private function mailAttributes(array $mail): array
    {
        return [
            'mail_admin' => (string) ($mail['admin'] ?? FormMailer::OUTCOME_NOT_APPLICABLE),
            'mail_customer' => (string) ($mail['customer'] ?? FormMailer::OUTCOME_NOT_APPLICABLE),
            'mail_reason' => isset($mail['reason']) ? substr((string) $mail['reason'], 0, 40) : null,
        ];
    }

    /**
     * How many provider calls this decision saved: a non-trusted submission
     * would have cost an admin mail (+ a customer mail when confirmations
     * are on); for a trusted one, every mail the budget skipped counts.
     */
    private function mailsPrevented(TrustDecision $decision, array $mail): int
    {
        if ($decision->trusted()) {
            return (int) ($mail['skipped'] ?? 0);
        }

        return 1 + (FormMailer::customerConfirmationEnabled() ? 1 : 0);
    }

    private static function fillTimeBucket(TrustContext $context): string
    {
        if ($context->timingStatus !== FormTimingToken::OK) {
            return $context->timingStatus;
        }

        $seconds = $context->fillSeconds;

        if ($seconds === null) {
            return 'normal';
        }

        $fast = (int) (config('form-protection.trust.fast_seconds.' . $context->form) ?? 15);

        if ($seconds < $fast) {
            return 'fast';
        }

        if ($seconds > 21600) {
            return 'slow';
        }

        return 'normal';
    }

    /** "xa***@gmail.com" — enough to recognise a pattern, not a person. */
    public static function maskEmail(?string $email): ?string
    {
        $email = trim((string) $email);

        if ($email === '' || ! str_contains($email, '@')) {
            return null;
        }

        [$local, $domain] = explode('@', $email, 2);
        $keep = strlen($local) > 2 ? 2 : 1;

        return substr(substr($local, 0, $keep) . '***@' . strtolower($domain), 0, 120);
    }

    public static function hashEmail(?string $email): ?string
    {
        $email = trim((string) $email);

        return $email === '' ? null : substr(RecipientSafety::hash($email), 0, 16);
    }

    /**
     * "Chrome 128 · Windows", "Safari 17 · iOS", "curl" — never the raw
     * string, which can carry identifying detail.
     */
    public static function userAgentFamily(?string $userAgent): ?string
    {
        $ua = trim((string) $userAgent);

        if ($ua === '') {
            return null;
        }

        $browser = null;

        foreach ([
            'Edg' => 'Edge',
            'OPR' => 'Opera',
            'SamsungBrowser' => 'Samsung Internet',
            'Firefox' => 'Firefox',
            'CriOS' => 'Chrome',
            'Chrome' => 'Chrome',
            'Safari' => 'Safari',
        ] as $token => $name) {
            if (preg_match('~' . preg_quote($token, '~') . '/(\d+)~', $ua, $m) === 1) {
                if ($name === 'Safari' && preg_match('~Version/(\d+)~', $ua, $v) === 1) {
                    $m[1] = $v[1];
                }

                $browser = $name . ' ' . $m[1];
                break;
            }
        }

        $os = null;

        foreach ([
            'Windows' => 'Windows',
            'iPhone' => 'iOS',
            'iPad' => 'iPadOS',
            'Android' => 'Android',
            'Mac OS X' => 'macOS',
            'CrOS' => 'ChromeOS',
            'Linux' => 'Linux',
        ] as $token => $name) {
            if (str_contains($ua, $token)) {
                $os = $name;
                break;
            }
        }

        if ($browser === null) {
            // Non-browser client: only the product name before the version.
            $product = preg_replace('~[/\s(].*$~', '', $ua) ?? $ua;
            $product = preg_replace('~[^A-Za-z0-9._-]~', '', $product) ?? '';

            return substr($product !== '' ? $product : 'onbekend', 0, 60);
        }

        return substr($os !== null ? "{$browser} · {$os}" : $browser, 0, 60);
    }
}
