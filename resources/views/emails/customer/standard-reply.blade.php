@php
    $headings = [
        'nl' => 'We nemen binnenkort contact op',
        'fr' => 'Nous vous contactons bientôt',
        'en' => 'We will contact you soon',
    ];
    $heading = $headings[$emailLocale] ?? $headings['nl'];
@endphp

@extends('emails.layout', ['emailLocale' => $emailLocale])

@section('subject', $heading)
@section('heading', $heading)

@section('content')
    <div style="font-size: 16px; line-height: 1.6; color: #405163; white-space: pre-line;">{{ $messageText }}</div>
@endsection
