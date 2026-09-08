<?php

namespace App\Services;

use App\Models\CustomerRequest;
use App\Models\MailLog;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Central place to send a Mailable, log the attempt, and never let a mail
 * failure bubble up and break the calling request/redirect. Used by every
 * outgoing email (customer confirmation, Martin notification, quote email)
 * so the try/catch + logging logic exists exactly once.
 *
 * This is the ONLY place that calls Mail::send() — i.e. the only place the
 * SMTP/Brevo transport is reached. Public-form mails must go through
 * App\Services\Spam\FormMailer first (kill switches, recipient safety,
 * mail budget); admin-initiated mails (quotes, standard replies) call this
 * directly from behind the admin middleware.
 */
class MailDispatcher
{
    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    public const STATUS_SKIPPED = 'skipped';

    public static function send(?string $recipient, Mailable $mailable, ?CustomerRequest $customerRequest = null): bool
    {
        $subject = $mailable->envelope()->subject ?? class_basename($mailable);
        $recipient = trim((string) $recipient);

        // A missing recipient (empty env value, "null" string) must never be
        // a TypeError that takes the whole form down; it is a failed send.
        if ($recipient === '' || $recipient === 'null') {
            Log::error('Mail send skipped: no recipient', [
                'mailable'            => class_basename($mailable),
                'customer_request_id' => $customerRequest?->id,
            ]);

            self::log($customerRequest, $mailable, $recipient, $subject, self::STATUS_FAILED, 'No recipient configured');

            return false;
        }

        try {
            Mail::to($recipient)->send($mailable);

            self::log($customerRequest, $mailable, $recipient, $subject, self::STATUS_SENT);

            return true;
        } catch (\Throwable $e) {
            Log::error('Mail send failed', [
                'mailable'             => class_basename($mailable),
                'recipient'            => $recipient,
                'customer_request_id'  => $customerRequest?->id,
                'error'                => $e->getMessage(),
            ]);

            self::log($customerRequest, $mailable, $recipient, $subject, self::STATUS_FAILED, $e->getMessage());

            return false;
        }
    }

    /**
     * Record a mail that was deliberately NOT sent (kill switch, mail
     * budget, unsafe recipient). Nothing touches the mail transport; the
     * audit trail shows the admin why a customer got no confirmation.
     */
    public static function skipped(?string $recipient, Mailable $mailable, string $reason, ?CustomerRequest $customerRequest = null): void
    {
        $subject = $mailable->envelope()->subject ?? class_basename($mailable);

        self::log($customerRequest, $mailable, trim((string) $recipient), $subject, self::STATUS_SKIPPED, $reason);
    }

    /**
     * Writing the audit trail must never be able to break the calling
     * request. If mail_logs is unavailable (e.g. the migration hasn't been
     * run against this database yet) the send/failure outcome above already
     * stands — only the log entry itself is lost, and that failure is
     * reported here rather than rethrown.
     */
    private static function log(
        ?CustomerRequest $customerRequest,
        Mailable $mailable,
        string $recipient,
        string $subject,
        string $status,
        ?string $error = null
    ): void {
        try {
            MailLog::create([
                'customer_request_id' => $customerRequest?->id,
                'mailable'            => class_basename($mailable),
                'recipient'           => $recipient,
                'subject'             => $subject,
                'status'              => $status,
                'error'               => $error,
                'sent_at'             => $status === self::STATUS_SENT ? now() : null,
            ]);
        } catch (\Throwable $e) {
            Log::error('Mail log write failed', [
                'mailable'  => class_basename($mailable),
                'recipient' => $recipient,
                'status'    => $status,
                'error'     => $e->getMessage(),
            ]);
        }
    }
}
