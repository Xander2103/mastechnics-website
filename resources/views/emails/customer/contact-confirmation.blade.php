@php
    $siteName = config('site.name');
    $locale = $data['locale'] ?? 'nl';

    $labels = [
        'nl' => [
            'title' => 'We hebben uw bericht goed ontvangen – Mastechnics',
            'hello' => 'Beste',
            'intro' => 'Bedankt voor uw bericht. We hebben uw aanvraag goed ontvangen en nemen zo snel mogelijk contact met u op.',
            'subject_label' => 'Onderwerp',
            'message_label' => 'Uw bericht',
            'signoff' => 'Met vriendelijke groeten,',
            'automatic' => 'Dit is een automatisch verzonden bevestiging. Gelieve niet op dit bericht te antwoorden.',
        ],
        'fr' => [
            'title' => 'Nous avons bien reçu votre message – Mastechnics',
            'hello' => 'Bonjour',
            'intro' => 'Merci pour votre message. Nous avons bien reçu votre demande et nous vous contacterons dès que possible.',
            'subject_label' => 'Sujet',
            'message_label' => 'Votre message',
            'signoff' => 'Cordialement,',
            'automatic' => 'Ceci est une confirmation envoyée automatiquement. Merci de ne pas répondre à ce message.',
        ],
        'en' => [
            'title' => 'We have received your message – Mastechnics',
            'hello' => 'Dear',
            'intro' => 'Thank you for your message. We have received your request and will contact you as soon as possible.',
            'subject_label' => 'Subject',
            'message_label' => 'Your message',
            'signoff' => 'Kind regards,',
            'automatic' => 'This is an automatically sent confirmation. Please do not reply to this message.',
        ],
    ];

    $text = $labels[$locale] ?? $labels['nl'];
@endphp

@extends('emails.layout', ['emailLocale' => $locale])

@section('subject', $text['title'])
@section('heading', $siteName)

@section('content')
    <p style="margin: 0 0 22px; font-size: 16px; line-height: 1.6; color: #405163;">
        {{ $text['hello'] }} {{ $data['name'] }},
    </p>

    <p style="margin: 0 0 22px; font-size: 16px; line-height: 1.6; color: #405163;">
        {{ $text['intro'] }}
    </p>

    <p style="margin: 0 0 6px; font-weight: bold; color: #0f3557;">
        {{ $text['subject_label'] }}: {{ $data['subject'] }}
    </p>

    <div style="margin: 10px 0 24px; padding: 16px 18px; border-radius: 14px; background: #f8fbff; border: 1px solid #d9e4ef; color: #405163; line-height: 1.6; white-space: pre-line;">
        {{ $data['message'] }}
    </div>

    <p style="margin: 0 0 22px; font-size: 15px; line-height: 1.6; color: #405163;">
        {{ $text['signoff'] }}<br>
        {{ $siteName }}
    </p>

    <p style="margin: 0; color: #6b7c8f; font-size: 14px; line-height: 1.6;">
        {{ $text['automatic'] }}
    </p>
@endsection
