<?php

namespace App\Mail;

use App\Models\CustomerRequest;
use App\Services\StandardReplyService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class StandardReplyMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public CustomerRequest $customerRequest
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: StandardReplyService::subject($this->customerRequest)
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.customer.standard-reply',
            with: [
                'customerRequest' => $this->customerRequest,
                'messageText' => StandardReplyService::message($this->customerRequest),
                'emailLocale' => StandardReplyService::locale($this->customerRequest),
            ],
        );
    }
}
