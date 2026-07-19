@php
    $siteName = config('site.name');
    $locale = $customerRequest->locale ?? 'nl';

    $labels = [
        'nl' => [
            'title' => 'Uw offerte is klaar',
            'hello' => 'Beste',
            'reference' => 'Referentie',
            'quote_number' => 'Offertenummer',
            'valid_until' => 'Geldig tot',
            'amount' => 'Totaalbedrag (incl. btw)',
            'attachment_note' => 'U vindt de volledige offerte als PDF-bijlage bij deze e-mail.',
            'automatic' => 'Deze e-mail werd verzonden door',
            'empty' => '-',
        ],
        'fr' => [
            'title' => 'Votre devis est prêt',
            'hello' => 'Bonjour',
            'reference' => 'Référence',
            'quote_number' => 'Numéro de devis',
            'valid_until' => 'Valable jusqu\'au',
            'amount' => 'Montant total (TVAC)',
            'attachment_note' => 'Vous trouverez le devis complet en pièce jointe PDF de cet e-mail.',
            'automatic' => 'Cet e-mail a été envoyé par',
            'empty' => '-',
        ],
        'en' => [
            'title' => 'Your quote is ready',
            'hello' => 'Hello',
            'reference' => 'Reference',
            'quote_number' => 'Quote number',
            'valid_until' => 'Valid until',
            'amount' => 'Total amount (incl. VAT)',
            'attachment_note' => 'You will find the complete quote attached as a PDF to this email.',
            'automatic' => 'This email was sent by',
            'empty' => '-',
        ],
    ];

    $text = $labels[$locale] ?? $labels['nl'];

    $rows = [
        $text['reference']     => $customerRequest->reference,
        $text['quote_number']  => $quote->quote_number ?: $text['empty'],
        $text['valid_until']   => $quote->valid_until?->format('d/m/Y') ?? $text['empty'],
        $text['amount']        => $quote->amount_incl_vat !== null
            ? '€ ' . number_format((float) $quote->amount_incl_vat, 2, ',', '.')
            : $text['empty'],
    ];
@endphp

@extends('emails.layout', ['emailLocale' => $locale])

@section('subject', $text['title'])
@section('heading', $text['title'])

@section('content')
    <p style="margin: 0 0 22px; font-size: 16px; line-height: 1.6; color: #405163;">
        {{ $text['hello'] }} {{ $customerRequest->customer_name }},
    </p>

    <div style="margin-bottom: 24px; font-size: 16px; line-height: 1.6; color: #405163; white-space: pre-line;">{{ $emailBody }}</div>

    @include('emails.partials.info-table', ['title' => $text['quote_number'], 'rows' => $rows])

    <p style="margin: 0 0 14px; font-size: 15px; line-height: 1.6; color: #405163;">
        {{ $text['attachment_note'] }}
    </p>

    <p style="margin: 0; color: #6b7c8f; font-size: 14px; line-height: 1.6;">
        {{ $text['automatic'] }} {{ $siteName }}.
    </p>
@endsection
