<?php

namespace App\Services\Spam;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

/**
 * One anti-abuse pipeline for both public forms (contact + request wizard).
 *
 * Every accepted submission costs two transactional e-mails on a 300/day
 * Brevo quota, so the defence is layered and every layer runs BEFORE
 * anything is stored or mailed. Order as enforced by the controllers:
 *
 *   0. kill switch            formEnabled()
 *   1. idempotency            submission_token already processed → fake success (controller)
 *   2. attempt limiters       enforceAttemptLimits(): per IP + site-wide, → 429
 *   3. validation             controller
 *   4. captcha                screen(): provider verdict, verified server-side
 *   5. honeypot               screen(): any filled hidden field → fake success
 *   6. fill time              screen(): signed timestamp, too fast / missing / forged
 *   7. accepted limits        screen(): per IP, per e-mail, per form, global burst
 *   8. fingerprint            screen(): exact repeat / same content repeat
 *   9. blocklist              controller (contact only)
 *  10. store                  controller, then recordAccepted()
 *  11. mail                   FormMailer → MailDispatcher → provider
 *
 * Counters 7–8 are only incremented after a submission was really stored,
 * so a visitor who gets a validation error does not burn quota.
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

    public function __construct(
        private readonly CaptchaVerifier $captcha,
        private readonly FormTimingToken $timing,
        private readonly FormProtectionLog $log,
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

            abort(429);
        }

        RateLimiter::hit($ipKey, 600);
        RateLimiter::hit($globalKey, 600);
    }

    /**
     * Steps 4–8, after validation. Returns null when the submission may be
     * stored, otherwise the Rejection to answer with. Nothing is counted
     * here: counters move in recordAccepted() once the row exists.
     */
    public function screen(string $form, Request $request, SubmissionFacts $facts, string $emailErrorKey): ?Rejection
    {
        if (! $this->captchaPassed($request)) {
            return $this->reject($form, $request, $facts, FormProtectionLog::REASON_CAPTCHA, 'captcha', 'captcha');
        }

        if ($this->honeypotTripped($request)) {
            return $this->reject($form, $request, $facts, FormProtectionLog::REASON_HONEYPOT, 'captcha', 'captcha', silent: true);
        }

        if ($this->timing->check($form, $request->input($this->timingField())) !== FormTimingToken::OK) {
            return $this->reject($form, $request, $facts, FormProtectionLog::REASON_TIMING, 'captcha', 'captcha');
        }

        if ($this->ipLimitExceeded($form, $request)) {
            return $this->reject($form, $request, $facts, FormProtectionLog::REASON_IP_LIMIT, 'rate_limit', 'rate_limit');
        }

        if ($this->emailLimitExceeded($form, $facts->email)) {
            return $this->reject($form, $request, $facts, FormProtectionLog::REASON_EMAIL_LIMIT, 'email_limit', $emailErrorKey);
        }

        if ($this->formLimitExceeded($form)) {
            return $this->reject($form, $request, $facts, FormProtectionLog::REASON_FORM_LIMIT, 'rate_limit', 'rate_limit');
        }

        if ($this->globalBurstExceeded()) {
            return $this->reject($form, $request, $facts, FormProtectionLog::REASON_GLOBAL_BURST, 'rate_limit', 'rate_limit');
        }

        if ($this->duplicateDetected($form, $request, $facts)) {
            return $this->reject($form, $request, $facts, FormProtectionLog::REASON_DUPLICATE, 'duplicate', 'captcha');
        }

        return null;
    }

    /** Blocklist hit (contact form): counted like every other rejection. */
    public function noteBlocked(string $form, Request $request, string $email): void
    {
        $this->log->rejected($form, FormProtectionLog::REASON_BLOCKLIST, $request, $email);
    }

    public function noteDisabled(string $form, Request $request): void
    {
        $this->log->rejected($form, FormProtectionLog::REASON_DISABLED, $request);
    }

    /**
     * Step 10: called once per stored submission, before mailing. The row
     * already exists, so a cache outage here must never turn into a 500 for
     * a submission that was accepted — it is logged and the send proceeds
     * (the mail budget has its own fail-closed handling).
     */
    public function recordAccepted(string $form, Request $request, SubmissionFacts $facts): void
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
    }

    // ── Individual checks (public for tests) ────────────────────────────────

    public function captchaPassed(Request $request): bool
    {
        if (! $this->captcha->enabled()) {
            return true;
        }

        $token = $request->input($this->captcha->responseField());

        return $this->captcha->verify(is_string($token) ? $token : null, $request->ip());
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

    private function reject(string $form, Request $request, SubmissionFacts $facts, string $reason, string $messageKind, string $errorKey, bool $silent = false): Rejection
    {
        $this->log->rejected($form, $reason, $request, $facts->email);

        return new Rejection($reason, $messageKind, $errorKey, $silent);
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
