<?php

namespace App\Services\Spam;

use App\Models\CustomerRequest;
use App\Services\MailDispatcher;
use Illuminate\Mail\Mailable;

/**
 * The only path from a public form to the mail provider. Called after the
 * submission is safely stored; applies, in order, the kill switches, the
 * recipient safety check and the mail budget, and only then hands the
 * Mailable to MailDispatcher (which is where Mail::send — and thus the
 * SMTP/Brevo call — happens). A skipped mail is recorded in mail_logs with
 * status "skipped" and is never retried automatically.
 */
class FormMailer
{
    public function __construct(
        private readonly MailBudget $budget,
        private readonly FormProtectionLog $log,
    ) {
    }

    public function sendAdmin(string $form, ?string $recipient, Mailable $mailable, ?CustomerRequest $customerRequest = null): bool
    {
        return $this->send($form, MailBudget::KIND_ADMIN, $recipient, $mailable, $customerRequest);
    }

    public function sendCustomer(string $form, ?string $recipient, Mailable $mailable, ?CustomerRequest $customerRequest = null): bool
    {
        if (! config('form-protection.mail.customer_confirmation_enabled', true)) {
            return $this->skip($form, MailBudget::KIND_CUSTOMER, FormProtectionLog::MAIL_CUSTOMER_DISABLED, $recipient, $mailable, $customerRequest);
        }

        return $this->send($form, MailBudget::KIND_CUSTOMER, $recipient, $mailable, $customerRequest);
    }

    private function send(string $form, string $kind, ?string $recipient, Mailable $mailable, ?CustomerRequest $customerRequest): bool
    {
        if (! RecipientSafety::isSafe($recipient)) {
            return $this->skip($form, $kind, FormProtectionLog::MAIL_UNSAFE_RECIPIENT, $recipient, $mailable, $customerRequest);
        }

        $exhausted = $this->budget->reserve($kind);

        if ($exhausted !== null) {
            return $this->skip($form, $kind, $exhausted, $recipient, $mailable, $customerRequest);
        }

        return MailDispatcher::send($recipient, $mailable, $customerRequest);
    }

    private function skip(string $form, string $kind, string $reason, ?string $recipient, Mailable $mailable, ?CustomerRequest $customerRequest): bool
    {
        $this->log->mailSkipped($form, $kind, $reason, $customerRequest?->id);
        MailDispatcher::skipped($recipient, $mailable, $reason, $customerRequest);

        return false;
    }
}
