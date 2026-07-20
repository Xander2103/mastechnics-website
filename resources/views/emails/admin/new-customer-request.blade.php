@php
    $siteName = config('site.name');

    $metadata = $customerRequest->metadata ?? [];
    $answers = $metadata['answers'] ?? [];

    $serviceTitle = $metadata['service']['title'] ?? $customerRequest->service_slug;
    $requestTypeLabel = $metadata['request_type']['label'] ?? $customerRequest->request_type;

    $adminUrl = route('admin.requests.show', $customerRequest);

    $customerRows = [
        'Referentie' => $customerRequest->reference,
        'Naam' => $customerRequest->customer_name,
        'E-mail' => $customerRequest->customer_email,
        'Telefoon' => $customerRequest->customer_phone ?: '-',
    ];

    $requestRows = [
        'Dienst' => $serviceTitle,
        'Type' => $requestTypeLabel,
        'Status' => $customerRequest->status,
        'Bijlagen' => $customerRequest->attachments->count() . ' bestand(en)',
    ];
@endphp

@extends('emails.layout')

@section('subject', 'Nieuwe aanvraag via ' . $siteName)
@section('heading', 'Nieuwe aanvraag ontvangen')

@section('content')
    <p style="margin: 0 0 22px; font-size: 16px; line-height: 1.6; color: #405163;">
        Er is een nieuwe aanvraag binnengekomen via het slimme aanvraagformulier.
    </p>

    @include('emails.partials.info-table', ['title' => 'Klantgegevens', 'rows' => $customerRows])

    @include('emails.partials.info-table', ['title' => 'Aanvraag', 'rows' => $requestRows])

    <div style="margin-bottom: 26px;">
        <h2 style="margin: 0 0 10px; font-size: 18px; color: #0f3557;">
            Beschrijving
        </h2>

        <div style="padding: 16px 18px; border-radius: 14px; background: #f8fbff; border: 1px solid #d9e4ef; color: #405163; line-height: 1.6;">
            {{ $customerRequest->description }}
        </div>
    </div>

    @if (!empty($answers))
        <div style="margin-bottom: 28px;">
            <h2 style="margin: 0 0 10px; font-size: 18px; color: #0f3557;">
                Extra antwoorden
            </h2>

            <table width="100%" cellpadding="0" cellspacing="0" style="border: 1px solid #d9e4ef; border-radius: 14px; overflow: hidden;">
                @foreach ($answers as $key => $value)
                    <tr>
                        <td style="width: 38%; padding: 10px 14px; border-top: {{ $loop->first ? '0' : '1px solid #d9e4ef' }}; color: #6b7c8f; font-weight: bold;">
                            {{ str_replace('_', ' ', ucfirst($key)) }}
                        </td>

                        <td style="padding: 10px 14px; border-top: {{ $loop->first ? '0' : '1px solid #d9e4ef' }};">
                            @if (is_bool($value))
                                {{ $value ? 'Ja' : 'Nee' }}
                            @elseif (is_array($value))
                                {{ json_encode($value, JSON_UNESCAPED_UNICODE) }}
                            @else
                                {{ $value ?: '-' }}
                            @endif
                        </td>
                    </tr>
                @endforeach
            </table>
        </div>
    @endif

    @include('emails.partials.cta-button', ['url' => $adminUrl, 'label' => 'Aanvraag bekijken in admin'])

    <p style="margin: 0; color: #6b7c8f; font-size: 14px; line-height: 1.6;">
        Deze melding werd automatisch verzonden door {{ $siteName }}.
    </p>
@endsection
