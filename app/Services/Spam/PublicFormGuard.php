<?php

namespace App\Services\Spam;

use App\Services\Spam\Trust\TrustContext;
use App\Services\Spam\Trust\TrustDecision;
use App\Services\Spam\Trust\TrustEvaluator;
use App\Services\Spam\Trust\TrustSignal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

/**
 * One anti-abuse pipeline for both public forms (contact + request wizard).
 *
 * Every accepted submission may cost transactional e-mail on a 300/day
 * Brevo quota, so the defence is layered and every layer runs BEFORE
 * anything is stored or mailed. Order as enforced by the controllers:
 *
 *   0. kill switch            formEnabled()
 *   1. idempotency            submission_token already processed → fake success (controller)
 *   2. attempt limiters       enforceAttemptLimits(): per IP + site-wide, → 429
 *   3. validation             controller
 *   4. captcha                screen(): provider verdict incl. hostname + action, server-side
 *   5. honeypot               screen(): any filled hidden field → fake success
 *   6. fill time              screen(): signed timestamp, too fast / missing / forged
 *   7. accepted limits        screen(): per IP, per e-mail, per form, global burst
 *   8. fingerprint            screen(): exact repeat / same content repeat
 *   9. trust evaluation       screen(): TrustEvaluator → trusted | needs_review | blocked
 *  10. blocklist              controller (contact only)
 *  11. store                  controller (needs_review rows get trust_verdict), then recordAccepted()
 *  12. mail                   FormMailer (only for a trusted decision) → MailDispatcher → provider
 *  13. security event         recordEvent(): one FormSecurityEvent per decision, fail-safe
 *
 * Steps 4–8 are hard blocks (nothing stored). Step 9 combines independent
 * signals: captcha alone is never enough to be trusted. Counters 7–8 are
 * only incremented after a submission was really stored, so a visitor who
 * gets a validation error does not burn quota.
 *
 * IP keys use Request::ip(), which is REMOTE_ADDR unless the proxy is listed
 * in TRUSTED_PROXIES (bootstrap/app.php) — X-Forwarded-For, Forwarded and
 * X-Real-IP from an untrusted client are ignored.
 */
class PublicFormGuard
{
    /** First honeypot field; kept as a constant for the existing controllers/tests. */
    public const HONEYPOT_FIELD = 'website_url';

    public const FORM_CONTACT = 'contact';

    public const FORM_REQUEST = 'request';

    private ?TrustContext $lastContext = null;

    public function __construct(
        private readonly CaptchaVerifier $captcha,
        private readonly FormTimingToken $timing,
        private readonly FormProtectionLog $log,
        private readonly TrustEvaluator $evaluator,
        private readonly SecurityEventRecorder $recorder,
    ) {
    }

    // ── View helpers ────────────────────────────────────────────────────────

    public function captcha(): CaptchaVerifier
    {
        return $this->captcha;
    }

    public function captchaEnabled(): bool
    {
        return $this->captcha->enabled();
    }

    public function formEnabled(string $form): bool
    {
        return (bool) config("form-protection.forms.{$form}.enabled", true);
    }

    /** @return array<int, string> */
    public function honeypotFields(): array
    {
        $fields = config('form-protection.honeypot_fields', [self::HONEYPOT_FIELD]);

        return is_array($fields) && $fields !== [] ? array_values($fields) : [self::HONEYPOT_FIELD];
    }

    public function timingField(): string
    {
        return (string) config('form-protection.timing.field', 'form_opened_at');
    }

    /**
     * Value for the hidden timing field. After a rejected POST the page is
     * re-rendered with old input: a token that already passed the minimum
     * fill time is re-used so a human correcting a field is not asked to
     * wait again; a forged one fails again on the next POST regardless.
     */
    public function timingToken(string $form): string
    {
        $previous = old($this->timingField());

        if (is_string($previous) && $this->timing->check($form, $previous) === FormTimingToken::OK) {
            return $previous;
        }

        return $this->timing->issue($form);
    }

    // ── Pipeline ────────────────────────────────────────────────────────────

    /**
     * Step 2: bound the number of POSTs a single client, and the site as a
     * whole, can push through validation and the captcha provider. Aborts
     * with a (branded, localized) 429 — nothing has been validated yet.
     */
    public function enforceAttemptLimits(string $form, Request $request): void
    {
        $ipKey = "form-protection:attempts:{$form}:" . $request->ip();
        $globalKey = 'form-protection:attempts:global';

        $ipLimit = (int) config("form-protection.forms.{$form}.attempt_limit_per_10_minutes", 30);
        $globalLimit = (int) config('form-protection.global_attempt_limit_per_10_minutes', 120);

        if (RateLimiter::tooManyAttempts($ipKey, $ipLimit) || RateLimiter::tooManyAttempts($globalKey, $globalLimit)) {
            $this->log->rejected($form, FormProtectionLog::REASON_ATTEMPTS, $request);
            $this->recorder->recordSimple($form, TrustDecision::BLOCKED, [FormProtectionLog::REASON_ATTEMPTS], $request);

            abort(429);
        }

        RateLimiter::hit($ipKey, 600);
        RateLimiter::hit($globalKey, 600);
    }

    /**
     * Steps 4–9, after validation. Returns the trust decision: blocked
     * (nothing may be stored; `rejection` says how to answer), needs_review
     * (store, never mail) or trusted (store, mail allowed). A blocked
     * decision is already logged here; for the other two the controller
     * calls recordAccepted() after the row exists and recordEvent() at the
     * end. Nothing is counted here.
     */
    public function screen(string $form, Request $request, SubmissionFacts $facts, string $emailErrorKey): TrustDecision
    {
        $captcha = $this->captchaVerdict($request, $form);
        $honeypot = $this->honeypotTripped($request);
        $timingStatus = $this->timing->check($form, $request->input($this->timingField()));
        $fillSeconds = $timingStatus === FormTimingToken::OK ? $this->fillSeconds($request) : null;

        $hard = null;

        if (! $captcha->passed() && $captcha->status !== CaptchaVerdict::DISABLED) {
            $reason = match ($captcha->status) {
                CaptchaVerdict::HOSTNAME_MISMATCH => 'captcha_hostname',
                CaptchaVerdict::ACTION_MISMATCH => 'captcha_action',
                default => FormProtectionLog::REASON_CAPTCHA,
            };
            $hard = new Rejection($reason, 'captcha', 'captcha');
        } elseif ($honeypot) {
            $hard = new Rejection(FormProtectionLog::REASON_HONEYPOT, 'captcha', 'captcha', true);
        } elseif ($timingStatus !== FormTimingToken::OK) {
            $hard = new Rejection(FormProtectionLog::REASON_TIMING, 'captcha', 'captcha');
        } elseif ($this->ipLimitExceeded($form, $request)) {
            $hard = new Rejection(FormProtectionLog::REASON_IP_LIMIT, 'rate_limit', 'rate_limit');
        } elseif ($this->emailLimitExceeded($form, $facts->email)) {
            $hard = new Rejection(FormProtectionLog::REASON_EMAIL_LIMIT, 'email_limit', $emailErrorKey);
        } elseif ($this->formLimitExceeded($form)) {
            $hard = new Rejection(FormProtectionLog::REASON_FORM_LIMIT, 'rate_limit', 'rate_limit');
        } elseif ($this->globalBurstExceeded()) {
            $hard = new Rejection(FormProtectionLog::REASON_GLOBAL_BURST, 'rate_limit', 'rate_limit');
        } elseif ($this->duplicateDetected($form, $request, $facts)) {
            $hard = new Rejection(FormProtectionLog::REASON_DUPLICATE, 'duplicate', 'captcha');
        }

        $context = new TrustContext($form, $request, $facts, $captcha, $timingStatus, $fillSeconds, $honeypot, $hard);
        $this->lastContext = $context;

        $decision = $this->evaluator->evaluate($context);

        if ($decision->blocked()) {
            $reason = $decision->rejection?->reason ?? 'trust_score';
            $this->log->rejected($form, $this->counterReason($reason), $request, $facts->email);
            $this->evaluator->recordBlocked($context);
            $this->recorder->record($context, $decision);
        }

        return $decision;
    }

    /** Blocklist hit (contact form): counted like every other rejection. */
    public function noteBlocked(string $form, Request $request, string $email): void
    {
        $this->log->rejected($form, FormProtectionLog::REASON_BLOCKLIST, $request, $email);
        $this->recorder->recordSimple($form, TrustDecision::BLOCKED, [FormProtectionLog::REASON_BLOCKLIST], $request, $email);
    }

    public function noteDisabled(string $form, Request $request): void
    {
        $this->log->rejected($form, FormProtectionLog::REASON_DISABLED, $request);
        $this->recorder->recordSimple($form, TrustDecision::BLOCKED, [FormProtectionLog::REASON_DISABLED], $request);
    }

    /**
     * Step 11: called once per stored submission, before mailing. The row
     * already exists, so a cache outage here must never turn into a 500 for
     * a submission that was accepted — it is logged and the flow proceeds
     * (the mail budget has its own fail-closed handling).
     */
    public function recordAccepted(string $form, Request $request, SubmissionFacts $facts, ?TrustDecision $decision = null): void
    {
        try {
            $ip = $request->ip();
            $fingerprint = config('form-protection.fingerprint', []);

            RateLimiter::hit("form-protection:accepted:{$form}:ip-day:{$ip}", 86400);
            RateLimiter::hit("form-protection:accepted:{$form}:ip-hour:{$ip}", 3600);
            RateLimiter::hit($this->emailKey($form, $facts->email), 86400);
            RateLimiter::hit("form-protection:accepted:{$form}:day", 86400);
            RateLimiter::hit('form-protection:accepted:global:burst', 600);
            RateLimiter::hit($this->exactFingerprintKey($form, $request, $facts), (int) ($fingerprint['exact_window_seconds'] ?? 600));

            $contentKey = $this->contentFingerprintKey($form, $facts);

            if ($contentKey !== null) {
                RateLimiter::hit($contentKey, (int) ($fingerprint['content_window_seconds'] ?? 3600));
            }
        } catch (\Throwable $e) {
            Log::error('form_protection counters could not be updated after an accepted submission', [
                'form' => $form,
                'error' => $e->getMessage(),
            ]);
        }

        if ($decision !== null && $this->lastContext !== null && $this->lastContext->form === $form) {
            $this->evaluator->recordAccepted($this->lastContext, $decision);
        }
    }

    /**
     * Step 13: the security event for a stored submission, with the mail
     * outcome of FormMailer::lastOutcome(). Never throws.
     *
     * @param  array{admin?: string, customer?: string, reason?: ?string, sent?: int, skipped?: int}  $mail
     */
    public function recordEvent(TrustDecision $decision, ?Model $subject, array $mail = []): void
    {
        if ($this->lastContext === null) {
            return;
        }

        $this->recorder->record($this->lastContext, $decision, $subject, $mail);
    }

    // ── Individual checks (public for tests) ────────────────────────────────

    public function captchaVerdict(Request $request, ?string $form = null): CaptchaVerdict
    {
        if (! $this->captcha->enabled()) {
            return CaptchaVerdict::disabled();
        }

        $token = $request->input($this->captcha->responseField());

        return $this->captcha->verifyDetailed(is_string($token) ? $token : null, $request->ip(), $form);
    }

    public function captchaPassed(Request $request): bool
    {
        return $this->captchaVerdict($request)->passed();
    }

    public function honeypotTripped(Request $request): bool
    {
        foreach ($this->honeypotFields() as $field) {
            $value = $request->input($field);

            if (is_array($value) || trim((string) $value) !== '') {
                return true;
            }
        }

        return false;
    }

    public function ipLimitExceeded(string $form, Request $request): bool
    {
        $ip = $request->ip();

        return RateLimiter::tooManyAttempts("form-protection:accepted:{$form}:ip-day:{$ip}", $this->limit($form, 'daily_limit', 5))
            || RateLimiter::tooManyAttempts("form-protection:accepted:{$form}:ip-hour:{$ip}", $this->limit($form, 'burst_limit_per_hour', 10));
    }

    /**
     * The same (normalized) e-mail address may only be accepted N times per
     * day per form, whoever submits it — this is what stops a bot from using
     * one real address as a spam victim, and it also stops quota burn
     * through a single address from many IPs.
     */
    public function emailLimitExceeded(string $form, string $email): bool
    {
        return RateLimiter::tooManyAttempts($this->emailKey($form, $email), $this->limit($form, 'email_daily_limit', 3));
    }

    /** Site-wide daily cap per form: a distributed bot cannot consume the quota. */
    public function formLimitExceeded(string $form): bool
    {
        return RateLimiter::tooManyAttempts("form-protection:accepted:{$form}:day", $this->limit($form, 'global_daily_limit', 60));
    }

    /** Site-wide burst over both forms. */
    public function globalBurstExceeded(): bool
    {
        return RateLimiter::tooManyAttempts(
            'form-protection:accepted:global:burst',
            (int) config('form-protection.accepted_burst_limit_per_10_minutes', 20)
        );
    }

    /**
     * Privacy-aware repeat detection: an identical submission (same client,
     * address, phone, message and user agent) within the window, or the
     * same message body from anyone more than N times per hour.
     */
    public function duplicateDetected(string $form, Request $request, SubmissionFacts $facts): bool
    {
        $fingerprint = config('form-protection.fingerprint', []);

        if (RateLimiter::tooManyAttempts($this->exactFingerprintKey($form, $request, $facts), (int) ($fingerprint['exact_limit'] ?? 1))) {
            return true;
        }

        $contentKey = $this->contentFingerprintKey($form, $facts);

        return $contentKey !== null
            && RateLimiter::tooManyAttempts($contentKey, (int) ($fingerprint['content_limit'] ?? 3));
    }

    // ── Messages ────────────────────────────────────────────────────────────

    /**
     * @param  'captcha'|'rate_limit'|'email_limit'|'duplicate'|'disabled'  $kind
     */
    public function message(string $form, string $kind, string $locale): string
    {
        $phone = (string) config('site.contact.phone_display');

        $messages = [
            'captcha' => [
                'nl' => 'De anti-spamcontrole is niet gelukt. Vernieuw de pagina en probeer opnieuw, of neem telefonisch contact met ons op.',
                'fr' => 'La vérification anti-spam a échoué. Rechargez la page et réessayez, ou contactez-nous par téléphone.',
                'en' => 'The anti-spam check failed. Refresh the page and try again, or contact us by phone.',
            ],
            'email_limit' => [
                'nl' => 'Voor dit e-mailadres werden vandaag al meerdere berichten verstuurd. Probeer morgen opnieuw of neem telefonisch contact op.',
                'fr' => "Plusieurs messages ont déjà été envoyés aujourd'hui pour cette adresse e-mail. Réessayez demain ou contactez-nous par téléphone.",
                'en' => 'Several messages have already been sent for this e-mail address today. Please try again tomorrow or contact us by phone.',
            ],
            'duplicate' => [
                'nl' => 'Dit bericht werd zonet al verstuurd. Wij hebben het goed ontvangen; u hoeft het niet opnieuw te versturen.',
                'fr' => "Ce message vient d'être envoyé. Nous l'avons bien reçu ; inutile de le renvoyer.",
                'en' => 'This message was just sent. We have received it; there is no need to send it again.',
            ],
            'disabled' => [
                'nl' => "Dit formulier is tijdelijk niet beschikbaar. Bel ons gerust op {$phone}.",
                'fr' => "Ce formulaire est temporairement indisponible. Appelez-nous au {$phone}.",
                'en' => "This form is temporarily unavailable. Feel free to call us at {$phone}.",
            ],
            'rate_limit' => $form === self::FORM_CONTACT
                ? [
                    'nl' => 'U heeft al meerdere berichten verstuurd. Probeer later opnieuw of neem rechtstreeks contact op.',
                    'fr' => 'Vous avez déjà envoyé plusieurs messages. Veuillez réessayer plus tard ou nous contacter directement.',
                    'en' => 'You have already sent several messages. Please try again later or contact us directly.',
                ]
                : [
                    'nl' => 'U heeft vandaag al meerdere aanvragen verstuurd. Probeer later opnieuw of neem rechtstreeks contact op.',
                    'fr' => "Vous avez déjà envoyé plusieurs demandes aujourd'hui. Veuillez réessayer plus tard ou nous contacter directement.",
                    'en' => 'You have already sent several requests today. Please try again later or contact us directly.',
                ],
        ];

        $set = $messages[$kind] ?? $messages['rate_limit'];

        return $set[$locale] ?? $set['nl'];
    }

    // ── Internals ───────────────────────────────────────────────────────────

    /** Counter key for FormProtectionLog: hostname/action mismatches count as captcha failures. */
    private function counterReason(string $reason): string
    {
        return match ($reason) {
            'captcha_hostname', 'captcha_action' => FormProtectionLog::REASON_CAPTCHA,
            'trust_score' => FormProtectionLog::REASON_TRUST_SCORE,
            default => $reason,
        };
    }

    private function fillSeconds(Request $request): ?int
    {
        $issuedAt = $this->timing->issuedAt($request->input($this->timingField()));

        return $issuedAt !== null ? max(0, time() - $issuedAt) : null;
    }

    private function limit(string $form, string $key, int $default): int
    {
        return (int) config("form-protection.forms.{$form}.{$key}", $default);
    }

    private function emailKey(string $form, string $email): string
    {
        return "form-protection:accepted:{$form}:email:" . RecipientSafety::hash($email);
    }

    private function exactFingerprintKey(string $form, Request $request, SubmissionFacts $facts): string
    {
        $parts = [
            $form,
            (string) $request->ip(),
            RecipientSafety::normalize($facts->email),
            preg_replace('/\D+/', '', (string) $facts->phone) ?? '',
            hash('sha256', trim((string) $facts->message)),
            hash('sha256', (string) $request->userAgent()),
        ];

        return 'form-protection:fp:exact:' . hash('sha256', implode('|', $parts));
    }

    private function contentFingerprintKey(string $form, SubmissionFacts $facts): ?string
    {
        $message = strtolower(trim((string) $facts->message));
        $message = preg_replace('/\s+/', ' ', $message) ?? $message;

        if (strlen($message) < 20) {
            // Too short to be meaningful ("ok", "test"): skip the content
            // fingerprint rather than block unrelated short messages.
            return null;
        }

        return 'form-protection:fp:content:' . hash('sha256', $form . '|' . $message);
    }
}
