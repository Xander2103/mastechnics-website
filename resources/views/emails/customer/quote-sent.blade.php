@php
    $siteName = config('site.name');

    // Locale + labels come from the shared QuoteEmailTextService so the
    // admin prefill, mailable and this template can never drift apart.
    $locale = \App\Services\QuoteEmailTextService::locale($customerRequest);
    $text = \App\Services\QuoteEmailTextService::labels($customerRequest);

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
    {{-- The greeting is part of $emailBody (see QuoteEmailTextService::body),
         so it is not repeated here. --}}
    <div style="margin-bottom: 24px; font-size: 16px; line-height: 1.6; color: #405163; white-space: pre-line;">{{ $emailBody }}</div>

    @include('emails.partials.info-table', ['title' => $text['quote_number'], 'rows' => $rows])

    <p style="margin: 0 0 14px; font-size: 15px; line-height: 1.6; color: #405163;">
        {{ $text['attachment_note'] }}
    </p>

    <p style="margin: 0; color: #6b7c8f; font-size: 14px; line-height: 1.6;">
        {{ $text['automatic'] }} {{ $siteName }}.
    </p>
@endsection
