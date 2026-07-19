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

            MailLog::create([
                'customer_request_id' => $customerRequest?->id,
                'mailable'            => class_basename($mailable),
                'recipient'           => $recipient,
                'subject'             => $subject,
                'status'              => 'sent',
                'sent_at'             => now(),
            ]);

            return true;
        } catch (\Throwable $e) {
            Log::error('Mail send failed', [
                'mailable'             => class_basename($mailable),
                'recipient'            => $recipient,
                'customer_request_id'  => $customerRequest?->id,
                'error'                => $e->getMessage(),
            ]);

            MailLog::create([
                'customer_request_id' => $customerRequest?->id,
                'mailable'            => class_basename($mailable),
                'recipient'           => $recipient,
                'subject'             => $subject,
                'status'              => 'failed',
                'error'               => $e->getMessage(),
            ]);

            return false;
        }
    }
}
