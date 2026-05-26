@php
    $siteName = config('site.name');

    $metadata = $customerRequest->metadata ?? [];
    $answers = $metadata['answers'] ?? [];

    $serviceTitle = $metadata['service']['title'] ?? $customerRequest->service_slug;
    $requestTypeLabel = $metadata['request_type']['label'] ?? $customerRequest->request_type;

    $adminUrl = route('admin.requests.show', $customerRequest);
@endphp

<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <title>Nieuwe aanvraag via {{ $siteName }}</title>
</head>
<body style="margin: 0; padding: 0; background: #f3f7fb; font-family: Arial, sans-serif; color: #102033;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background: #f3f7fb; padding: 28px 12px;">
        <tr>
            <td align="center">
                <table width="100%" cellpadding="0" cellspacing="0" style="max-width: 680px; background: #ffffff; border-radius: 18px; overflow: hidden; border: 1px solid #d9e4ef;">
                    <tr>
                        <td style="padding: 28px 32px; background: #0f3557; color: #ffffff;">
                            <p style="margin: 0 0 8px; font-size: 13px; font-weight: bold; letter-spacing: 0.08em; text-transform: uppercase;">
                                {{ $siteName }}
                            </p>

                            <h1 style="margin: 0; font-size: 26px; line-height: 1.25;">
                                Nieuwe aanvraag ontvangen
                            </h1>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding: 30px 32px;">
                            <p style="margin: 0 0 22px; font-size: 16px; line-height: 1.6; color: #405163;">
                                Er is een nieuwe aanvraag binnengekomen via het slimme aanvraagformulier.
                            </p>

                            <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 24px; border: 1px solid #d9e4ef; border-radius: 14px; overflow: hidden;">
                                <tr>
                                    <td colspan="2" style="padding: 14px 18px; background: #edf5ff; color: #0f3557; font-weight: bold;">
                                        Klantgegevens
                                    </td>
                                </tr>

                                <tr>
                                    <td style="width: 34%; padding: 12px 18px; border-top: 1px solid #d9e4ef; color: #6b7c8f; font-weight: bold;">
                                        Naam
                                    </td>
                                    <td style="padding: 12px 18px; border-top: 1px solid #d9e4ef;">
                                        {{ $customerRequest->customer_name }}
                                    </td>
                                </tr>

                                <tr>
                                    <td style="padding: 12px 18px; border-top: 1px solid #d9e4ef; color: #6b7c8f; font-weight: bold;">
                                        E-mail
                                    </td>
                                    <td style="padding: 12px 18px; border-top: 1px solid #d9e4ef;">
                                        <a href="mailto:{{ $customerRequest->customer_email }}" style="color: #0f66c2; font-weight: bold;">
                                            {{ $customerRequest->customer_email }}
                                        </a>
                                    </td>
                                </tr>

                                <tr>
                                    <td style="padding: 12px 18px; border-top: 1px solid #d9e4ef; color: #6b7c8f; font-weight: bold;">
                                        Telefoon
                                    </td>
                                    <td style="padding: 12px 18px; border-top: 1px solid #d9e4ef;">
                                        {{ $customerRequest->customer_phone ?: '-' }}
                                    </td>
                                </tr>
                            </table>

                            <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 24px; border: 1px solid #d9e4ef; border-radius: 14px; overflow: hidden;">
                                <tr>
                                    <td colspan="2" style="padding: 14px 18px; background: #edf5ff; color: #0f3557; font-weight: bold;">
                                        Aanvraag
                                    </td>
                                </tr>

                                <tr>
                                    <td style="width: 34%; padding: 12px 18px; border-top: 1px solid #d9e4ef; color: #6b7c8f; font-weight: bold;">
                                        Dienst
                                    </td>
                                    <td style="padding: 12px 18px; border-top: 1px solid #d9e4ef;">
                                        {{ $serviceTitle }}
                                    </td>
                                </tr>

                                <tr>
                                    <td style="padding: 12px 18px; border-top: 1px solid #d9e4ef; color: #6b7c8f; font-weight: bold;">
                                        Type
                                    </td>
                                    <td style="padding: 12px 18px; border-top: 1px solid #d9e4ef;">
                                        {{ $requestTypeLabel }}
                                    </td>
                                </tr>

                                <tr>
                                    <td style="padding: 12px 18px; border-top: 1px solid #d9e4ef; color: #6b7c8f; font-weight: bold;">
                                        Status
                                    </td>
                                    <td style="padding: 12px 18px; border-top: 1px solid #d9e4ef;">
                                        {{ $customerRequest->status }}
                                    </td>
                                </tr>

                                <tr>
                                    <td style="padding: 12px 18px; border-top: 1px solid #d9e4ef; color: #6b7c8f; font-weight: bold;">
                                        Bijlagen
                                    </td>
                                    <td style="padding: 12px 18px; border-top: 1px solid #d9e4ef;">
                                        {{ $customerRequest->attachments->count() }} bestand(en)
                                    </td>
                                </tr>
                            </table>

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

                            <table cellpadding="0" cellspacing="0" style="margin: 0 auto 28px;">
                                <tr>
                                    <td align="center" style="border-radius: 999px; background: #0f66c2;">
                                        <a href="{{ $adminUrl }}" target="_blank" style="display: inline-block; padding: 14px 24px; color: #ffffff; text-decoration: none; font-weight: bold;">
                                            Aanvraag bekijken in admin
                                        </a>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin: 0; color: #6b7c8f; font-size: 14px; line-height: 1.6;">
                                Deze melding werd automatisch verzonden door {{ $siteName }}.
                            </p>
                        </td>
                    </tr>
                </table>

                <p style="margin: 18px 0 0; color: #8aa0b5; font-size: 12px;">
                    © {{ date('Y') }} {{ $siteName }}
                </p>
            </td>
        </tr>
    </table>
</body>
</html>