<?php

namespace App\Services;

use App\Models\CustomerRequest;

/**
 * Single source of truth for every customer-facing text in the quote email.
 *
 * The admin send-form prefill, the mailable subject and the email template
 * chrome all read from here. The language is always the locale the customer
 * used when submitting the request (never the admin UI language), with a
 * safe fallback to Dutch for unknown values. The quote PDF itself remains
 * Dutch-only by design; see docs/superpowers plan 2026-07-30.
 */
class QuoteEmailTextService
{
    public const SUPPORTED_LOCALES = ['nl', 'fr', 'en'];

    private const TEXTS = [
        'nl' => [
            'subject' => 'Uw offerte van :site',
            'greeting' => 'Beste :name,',
            'body' => 'Zoals besproken vindt u in bijlage onze offerte.',
            'outro' => 'Heeft u nog vragen? Neem gerust contact met ons op.',
            'signoff' => 'Met vriendelijke groeten,',
            'title' => 'Uw offerte is klaar',
            'reference' => 'Referentie',
            'quote_number' => 'Offertenummer',
            'valid_until' => 'Geldig tot',
            'amount' => 'Totaalbedrag (incl. btw)',
            'attachment_note' => 'U vindt de volledige offerte als PDF-bijlage bij deze e-mail.',
            'automatic' => 'Deze e-mail werd verzonden door',
            'empty' => '-',
        ],
        'fr' => [
            'subject' => 'Votre devis de :site',
            'greeting' => 'Bonjour :name,',
            'body' => 'Comme convenu, vous trouverez notre devis en pièce jointe.',
            'outro' => "N'hésitez pas à nous contacter pour toute question.",
            'signoff' => 'Cordialement,',
            'title' => 'Votre devis est prêt',
            'reference' => 'Référence',
            'quote_number' => 'Numéro de devis',
            'valid_until' => "Valable jusqu'au",
            'amount' => 'Montant total (TVAC)',
            'attachment_note' => 'Vous trouverez le devis complet en pièce jointe PDF de cet e-mail.',
            'automatic' => 'Cet e-mail a été envoyé par',
            'empty' => '-',
        ],
        'en' => [
            'subject' => 'Your quotation from :site',
            'greeting' => 'Dear :name,',
            'body' => 'As discussed, please find our quotation attached.',
            'outro' => 'Feel free to contact us with any questions.',
            'signoff' => 'Kind regards,',
            'title' => 'Your quote is ready',
            'reference' => 'Reference',
            'quote_number' => 'Quote number',
            'valid_until' => 'Valid until',
            'amount' => 'Total amount (incl. VAT)',
            'attachment_note' => 'You will find the complete quote attached as a PDF to this email.',
            'automatic' => 'This email was sent by',
            'empty' => '-',
        ],
    ];

    public static function locale(CustomerRequest $customerRequest): string
    {
        $locale = $customerRequest->locale;

        return in_array($locale, self::SUPPORTED_LOCALES, true) ? $locale : 'nl';
    }

    public static function subject(CustomerRequest $customerRequest): string
    {
        return str_replace(':site', (string) config('site.name'), self::labels($customerRequest)['subject']);
    }

    /**
     * The editable plain-text default body for the send form: greeting,
     * core sentence, outro and sign-off in the customer's language.
     */
    public static function body(CustomerRequest $customerRequest): string
    {
        $text = self::labels($customerRequest);

        return str_replace(':name', (string) $customerRequest->customer_name, $text['greeting'])
            . "\n\n" . $text['body']
            . "\n\n" . $text['outro']
            . "\n\n" . $text['signoff'] . "\n" . config('site.name');
    }

    /**
     * All localized strings for the email template chrome.
     *
     * @return array<string, string>
     */
    public static function labels(CustomerRequest $customerRequest): array
    {
        return self::TEXTS[self::locale($customerRequest)];
    }
}
