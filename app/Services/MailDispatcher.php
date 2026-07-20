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
 */
class MailDispatcher
{
    public static function send(string $recipient, Mailable $mailable, ?CustomerRequest $customerRequest = null): bool
    {
        $subject = $mailable->envelope()->subject ?? class_basename($mailable);

        try {
            Mail::to($recipient)->send($mailable);

            self::log($customerRequest, $mailable, $recipient, $subject, 'sent');

            return true;
        } catch (\Throwable $e) {
            Log::error('Mail send failed', [
                'mailable'             => class_basename($mailable),
                'recipient'            => $recipient,
                'customer_request_id'  => $customerRequest?->id,
                'error'                => $e->getMessage(),
            ]);

            self::log($customerRequest, $mailable, $recipient, $subject, 'failed', $e->getMessage());

            return false;
        }
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
                'sent_at'             => $status === 'sent' ? now() : null,
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
