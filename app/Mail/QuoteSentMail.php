<?php

namespace App\Mail;

use App\Models\CustomerRequest;
use App\Models\Quote;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class QuoteSentMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public CustomerRequest $customerRequest,
        public Quote $quote,
        public string $emailSubject,
        public string $emailBody,
        public string $pdfBinary,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->emailSubject);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.customer.quote-sent',
            with: [
                'customerRequest' => $this->customerRequest,
                'quote'           => $this->quote,
                'emailBody'       => $this->emailBody,
            ],
        );
    }

    public function attachments(): array
    {
        $filename = strtolower($this->quote->quote_number ?: 'offerte') . '-mastechnics-offerte.pdf';

        return [
            Attachment::fromData(fn () => $this->pdfBinary, $filename)
                ->withMime('application/pdf'),
        ];
    }
}
