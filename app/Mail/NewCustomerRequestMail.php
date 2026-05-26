<?php

namespace App\Mail;

use App\Models\CustomerRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NewCustomerRequestMail extends Mailable
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
            subject: 'Nieuwe aanvraag via ' . config('site.name')
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.admin.new-customer-request',
            with: [
                'customerRequest' => $this->customerRequest,
            ],
        );
    }
}