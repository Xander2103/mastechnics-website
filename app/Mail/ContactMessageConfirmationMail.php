<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ContactMessageConfirmationMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public array $data
    ) {
    }

    public function envelope(): Envelope
    {
        $locale = $this->data['locale'] ?? 'nl';

        $subjects = [
            'nl' => 'We hebben uw bericht goed ontvangen – Mastechnics',
            'fr' => 'Nous avons bien reçu votre message – Mastechnics',
            'en' => 'We have received your message – Mastechnics',
        ];

        return new Envelope(
            subject: $subjects[$locale] ?? $subjects['nl']
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.customer.contact-confirmation',
            with: [
                'data' => $this->data,
            ],
        );
    }
}
