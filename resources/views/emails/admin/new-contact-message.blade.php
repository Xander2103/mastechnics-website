@php
    $siteName = config('site.name');

    $localeLabels = ['nl' => 'Nederlands', 'fr' => 'Français', 'en' => 'English'];
    $localeLabel = $localeLabels[$data['locale']] ?? $data['locale'];

    $rows = [
        'Naam' => $data['name'],
        'E-mail' => $data['email'],
        'Telefoon' => $data['phone'] ?: '-',
        'Onderwerp' => $data['subject'],
        'Taal van de website' => $localeLabel,
        'Datum en tijd' => $data['submitted_at']->format('d/m/Y H:i'),
        'Bronpagina' => $data['source_url'] ?: '-',
    ];
@endphp

@extends('emails.layout')

@section('subject', 'Nieuwe contactaanvraag via ' . $siteName . ' – ' . $data['subject'])
@section('heading', 'Nieuwe contactaanvraag')

@section('content')
    <p style="margin: 0 0 22px; font-size: 16px; line-height: 1.6; color: #405163;">
        Er is een nieuw bericht binnengekomen via het contactformulier op de website.
    </p>

    @include('emails.partials.info-table', ['title' => 'Gegevens', 'rows' => $rows])

    <div style="margin-bottom: 26px;">
        <h2 style="margin: 0 0 10px; font-size: 18px; color: #0f3557;">
            Bericht
        </h2>

        <div style="padding: 16px 18px; border-radius: 14px; background: #f8fbff; border: 1px solid #d9e4ef; color: #405163; line-height: 1.6; white-space: pre-line;">
            {{ $data['message'] }}
        </div>
    </div>

    <p style="margin: 0; color: #6b7c8f; font-size: 14px; line-height: 1.6;">
        U kan rechtstreeks op deze e-mail antwoorden om te reageren naar {{ $data['email'] }}.
    </p>
@endsection
