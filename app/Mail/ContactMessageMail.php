<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ContactMessageMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public array $data
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            // Distinct from the customer acknowledgement subject on purpose
            // (customer name, not the message subject) — so if a visitor
            // enters CONTACT_NOTIFICATION_EMAIL as their own address, the
            // two mails in that inbox are still clearly not the same email.
            subject: 'Nieuwe contactaanvraag via ' . config('site.name') . ' — ' . $this->data['name'],
            replyTo: [
                new Address($this->data['email'], $this->data['name']),
            ],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.admin.new-contact-message',
            with: [
                'data' => $this->data,
            ],
        );
    }
}
