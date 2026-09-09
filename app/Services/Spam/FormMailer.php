<?php

namespace App\Services\Spam;

use App\Models\CustomerRequest;
use App\Services\MailDispatcher;
use App\Services\Spam\Trust\TrustDecision;
use Closure;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Log;

/**
 * The only path from a public form to the mail provider. Called after the
 * submission is safely stored; applies, in order: the trust decision (only
 * a TRUSTED submission may cause mail at all), the kill switches, the
 * recipient safety check and the mail budget / circuit breaker, and only
 * then hands the Mailable to MailDispatcher (which is where Mail::send —
 * and thus the SMTP/Brevo call — happens). A skipped mail is recorded in
 * mail_logs with status "skipped" and is never retried automatically.
 *
 * The Mailable may be passed as a Closure so that a mail which is skipped
 * before the send (customer confirmations disabled, not trusted) is never
 * even built.
 */
class FormMailer
{
    public const OUTCOME_SENT = 'sent';

    public const OUTCOME_SKIPPED = 'skipped';

    public const OUTCOME_FAILED = 'failed';

    public const OUTCOME_NOT_APPLICABLE = 'not_applicable';

    /** @var array{admin: string, customer: string, reason: ?string, sent: int, skipped: int} */
    private array $outcome;

    public function __construct(
        private readonly MailBudget $budget,
        private readonly FormProtectionLog $log,
    ) {
        $this->beginSubmission();
    }

    /** Reset the per-submission outcome (the security-event recorder reads it afterwards). */
    public function beginSubmission(): void
    {
        $this->outcome = [
            'admin' => self::OUTCOME_NOT_APPLICABLE,
            'customer' => self::OUTCOME_NOT_APPLICABLE,
            'reason' => null,
            'sent' => 0,
            'skipped' => 0,
        ];
    }

    /** @return array{admin: string, customer: string, reason: ?string, sent: int, skipped: int} */
    public function lastOutcome(): array
    {
        return $this->outcome;
    }

    public function sendAdmin(string $form, ?string $recipient, Closure|Mailable $mailable, TrustDecision $decision, ?CustomerRequest $customerRequest = null): bool
    {
        return $this->send($form, MailBudget::KIND_ADMIN, $recipient, $mailable, $decision, $customerRequest);
    }

    public function sendCustomer(string $form, ?string $recipient, Closure|Mailable $mailable, TrustDecision $decision, ?CustomerRequest $customerRequest = null): bool
    {
        if (! self::customerConfirmationEnabled()) {
            // Switch is off (the default): no transport call, only the audit
            // row in mail_logs so the admin sees why the customer got nothing.
            return $this->skip($form, MailBudget::KIND_CUSTOMER, FormProtectionLog::MAIL_CUSTOMER_DISABLED, $recipient, $mailable, $customerRequest);
        }

        return $this->send($form, MailBudget::KIND_CUSTOMER, $recipient, $mailable, $decision, $customerRequest);
    }

    public static function customerConfirmationEnabled(): bool
    {
        return (bool) config('form-protection.mail.customer_confirmation_enabled', false);
    }

    private function send(string $form, string $kind, ?string $recipient, Closure|Mailable $mailable, TrustDecision $decision, ?CustomerRequest $customerRequest): bool
    {
        if (! $decision->trusted()) {
            return $this->skip($form, $kind, FormProtectionLog::MAIL_NOT_TRUSTED, $recipient, $mailable, $customerRequest);
        }

        if (! RecipientSafety::isSafe($recipient)) {
            return $this->skip($form, $kind, FormProtectionLog::MAIL_UNSAFE_RECIPIENT, $recipient, $mailable, $customerRequest);
        }

        $exhausted = $this->budget->reserve($kind);

        if ($exhausted !== null) {
            return $this->skip($form, $kind, $exhausted, $recipient, $mailable, $customerRequest);
        }

        $sent = MailDispatcher::send($recipient, $this->build($mailable), $customerRequest);

        $this->outcome[$kind] = $sent ? self::OUTCOME_SENT : self::OUTCOME_FAILED;

        if ($sent) {
            $this->outcome['sent']++;
        } elseif ($this->outcome['reason'] === null) {
            $this->outcome['reason'] = 'mail_transport_failed';
        }

        return $sent;
    }

    private function skip(string $form, string $kind, string $reason, ?string $recipient, Closure|Mailable $mailable, ?CustomerRequest $customerRequest): bool
    {
        $this->noteSkipped($kind, $reason);
        $this->log->mailSkipped($form, $kind, $reason, $customerRequest?->id);

        try {
            MailDispatcher::skipped($recipient, $this->build($mailable), $reason, $customerRequest);
        } catch (\Throwable $e) {
            // The audit row is best effort; building a mailable for it must
            // never take the submission down.
            Log::error('Skipped-mail audit row failed', ['reason' => $reason, 'error' => $e->getMessage()]);
        }

        return false;
    }

    private function noteSkipped(string $kind, string $reason): void
    {
        $this->outcome[$kind] = self::OUTCOME_SKIPPED;
        $this->outcome['skipped']++;

        if ($this->outcome['reason'] === null) {
            $this->outcome['reason'] = $reason;
        }
    }

    private function build(Closure|Mailable $mailable): Mailable
    {
        return $mailable instanceof Closure ? $mailable() : $mailable;
    }
}
