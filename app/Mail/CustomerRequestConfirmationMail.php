<?php

namespace App\Mail;

use App\Models\CustomerRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CustomerRequestConfirmationMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public CustomerRequest $customerRequest
    ) {
    }

    public function envelope(): Envelope
    {
        $locale = $this->customerRequest->locale ?? 'nl';

        $subjects = [
            'nl' => 'We hebben je aanvraag goed ontvangen',
            'fr' => 'Nous avons bien reçu votre demande',
            'en' => 'We have received your request',
        ];

        return new Envelope(
            subject: $subjects[$locale] ?? $subjects['nl']
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.customer.request-confirmation',
            with: [
                'customerRequest' => $this->customerRequest,
            ],
        );
    }
}