<?php

namespace App\Services;

use App\Mail\ContactMessageConfirmationMail;
use App\Mail\ContactMessageMail;
use App\Mail\CustomerRequestConfirmationMail;
use App\Mail\NewCustomerRequestMail;
use App\Models\ContactSubmission;
use App\Models\CustomerRequest;
use App\Services\Spam\FormMailer;
use App\Services\Spam\PublicFormGuard;
use App\Services\Spam\SecurityEventRecorder;
use App\Services\Spam\Trust\TrustDecision;
use Illuminate\Http\Request;

/**
 * The two manual decisions an admin can take on a submission the trust
 * gate stored as needs_review:
 *
 *   release  → trusted; the admin notification and (when enabled) the
 *              customer confirmation are sent now, through FormMailer, so
 *              the circuit breaker still applies. Never automatic.
 *   spam     → verdict "spam"; nothing is mailed, the row stays for the
 *              record (the address can then be blocklisted).
 *
 * Both are logged as a security event with the admin's decision as reason.
 */
class TrustReviewService
{
    public const VERDICT_SPAM = 'spam';

    public function __construct(
        private readonly FormMailer $mailer,
        private readonly SecurityEventRecorder $recorder,
    ) {
    }

    /** @return array{admin: string, customer: string, reason: ?string, sent: int, skipped: int} */
    public function release(CustomerRequest|ContactSubmission $subject, string $adminEmail, ?Request $request = null): array
    {
        $subject->forceFill([
            'trust_verdict' => TrustDecision::TRUSTED,
            'trust_reviewed_at' => now(),
            'trust_reviewed_by' => $adminEmail,
        ])->save();

        $decision = TrustDecision::manualRelease($adminEmail);
        $this->mailer->beginSubmission();

        if ($subject instanceof CustomerRequest) {
            $form = PublicFormGuard::FORM_REQUEST;
            $subject->load(['attachments', 'notes']);

            foreach ($this->requestRecipients() as $recipient) {
                $this->mailer->sendAdmin($form, $recipient, fn () => new NewCustomerRequestMail($subject), $decision, $subject);
            }

            $this->mailer->sendCustomer($form, $subject->customer_email, fn () => new CustomerRequestConfirmationMail($subject), $decision, $subject);
            $email = $subject->customer_email;
        } else {
            $form = PublicFormGuard::FORM_CONTACT;
            $data = $this->contactData($subject);

            $this->mailer->sendAdmin($form, config('site.contact_notification_email'), fn () => new ContactMessageMail($data), $decision);
            $this->mailer->sendCustomer($form, $subject->email, fn () => new ContactMessageConfirmationMail($data), $decision);
            $email = $subject->email;
        }

        $outcome = $this->mailer->lastOutcome();

        if (($outcome['sent'] ?? 0) > 0 && $subject instanceof ContactSubmission) {
            $subject->forceFill(['mail_sent_at' => now()])->save();
        }

        $this->recorder->recordSimple($form, TrustDecision::TRUSTED, ['manual_release'], $request, $email, $subject, $outcome);

        return $outcome;
    }

    public function markSpam(CustomerRequest|ContactSubmission $subject, string $adminEmail, ?Request $request = null): void
    {
        $subject->forceFill([
            'trust_verdict' => self::VERDICT_SPAM,
            'trust_reviewed_at' => now(),
            'trust_reviewed_by' => $adminEmail,
        ])->save();

        $form = $subject instanceof CustomerRequest ? PublicFormGuard::FORM_REQUEST : PublicFormGuard::FORM_CONTACT;
        $email = $subject instanceof CustomerRequest ? $subject->customer_email : $subject->email;

        $this->recorder->recordSimple($form, TrustDecision::BLOCKED, ['manual_spam'], $request, $email, $subject, [], 0);
    }

    /** @return array<int, string> */
    private function requestRecipients(): array
    {
        return collect(config('admin.notification_emails', []))
            ->push(config('site.request_notification_email'))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /** @return array<string, mixed> */
    private function contactData(ContactSubmission $submission): array
    {
        return [
            'name' => $submission->name,
            'email' => $submission->email,
            'phone' => $submission->phone,
            'subject' => $submission->subject,
            'message' => $submission->message,
            'locale' => $submission->locale,
            'submitted_at' => $submission->created_at ?? now(),
            'source_url' => url('/' . ($submission->locale ?: 'nl')),
        ];
    }
}
