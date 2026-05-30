@php
    $siteName = config('site.name');

    $locale = $customerRequest->locale ?? 'nl';

    $metadata = $customerRequest->metadata ?? [];
    $answers = $metadata['answers'] ?? [];

    $serviceTitle = $metadata['service']['title'] ?? $customerRequest->service_slug;
    $requestTypeLabel = $metadata['request_type']['label'] ?? $customerRequest->request_type;

    $labels = [
        'nl' => [
            'title' => 'We hebben je aanvraag goed ontvangen',
            'hello' => 'Beste',
            'intro' => 'Bedankt voor je aanvraag. We hebben je gegevens goed ontvangen en bekijken je aanvraag zo snel mogelijk.',
            'summary' => 'Samenvatting van je aanvraag',
            'service' => 'Dienst',
            'request_type' => 'Type aanvraag',
            'customer_type' => 'Klanttype',
            'urgency' => 'Urgentie',
            'address' => 'Adres',
            'availability' => 'Beschikbaarheid',
            'brand' => 'Merk',
            'model' => 'Model',
            'serial' => 'Serienummer',
            'unknown_device' => 'Merk/model/serienummer onbekend',
            'attachments' => 'Aantal bijlagen',
            'files' => 'bestand(en)',
            'description' => 'Beschrijving',
            'next' => 'Je hoeft voorlopig niets extra te doen. Als er nog informatie ontbreekt, nemen we contact met je op.',
            'automatic' => 'Deze bevestiging werd automatisch verzonden door',
            'yes' => 'Ja',
            'no' => 'Nee',
            'empty' => '-',
            'customer_types' => [
                'residential' => 'Particulier',
                'business' => 'Bedrijf',
            ],
            'urgencies' => [
                'urgent' => 'Dringend',
                'within_days' => 'Binnen enkele dagen',
                'not_urgent' => 'Niet dringend',
            ],
        ],

        'fr' => [
            'title' => 'Nous avons bien reçu votre demande',
            'hello' => 'Bonjour',
            'intro' => 'Merci pour votre demande. Nous avons bien reçu vos informations et nous examinerons votre demande dès que possible.',
            'summary' => 'Résumé de votre demande',
            'service' => 'Service',
            'request_type' => 'Type de demande',
            'customer_type' => 'Type de client',
            'urgency' => 'Urgence',
            'address' => 'Adresse',
            'availability' => 'Disponibilité',
            'brand' => 'Marque',
            'model' => 'Modèle',
            'serial' => 'Numéro de série',
            'unknown_device' => 'Marque/modèle/numéro de série inconnu',
            'attachments' => 'Nombre de pièces jointes',
            'files' => 'fichier(s)',
            'description' => 'Description',
            'next' => 'Vous n'avez rien d'autre à faire pour le moment. S'il manque des informations, nous vous contacterons.',
            'automatic' => 'Cette confirmation a été envoyée automatiquement par',
            'yes' => 'Oui',
            'no' => 'Non',
            'empty' => '-',
            'customer_types' => [
                'residential' => 'Particulier',
                'business' => 'Entreprise',
            ],
            'urgencies' => [
                'urgent' => 'Urgent',
                'within_days' => 'Dans quelques jours',
                'not_urgent' => 'Pas urgent',
            ],
        ],

        'en' => [
            'title' => 'We have received your request',
            'hello' => 'Hello',
            'intro' => 'Thank you for your request. We have received your information and will review your request as soon as possible.',
            'summary' => 'Summary of your request',
            'service' => 'Service',
            'request_type' => 'Request type',
            'customer_type' => 'Customer type',
            'urgency' => 'Urgency',
            'address' => 'Address',
            'availability' => 'Availability',
            'brand' => 'Brand',
            'model' => 'Model',
            'serial' => 'Serial number',
            'unknown_device' => 'Brand/model/serial number unknown',
            'attachments' => 'Number of attachments',
            'files' => 'file(s)',
            'description' => 'Description',
            'next' => 'You do not need to do anything else for now. If more information is needed, we will contact you.',
            'automatic' => 'This confirmation was sent automatically by',
            'yes' => 'Yes',
            'no' => 'No',
            'empty' => '-',
            'customer_types' => [
                'residential' => 'Residential',
                'business' => 'Business',
            ],
            'urgencies' => [
                'urgent' => 'Urgent',
                'within_days' => 'Within a few days',
                'not_urgent' => 'Not urgent',
            ],
        ],
    ];

    $text = $labels[$locale] ?? $labels['nl'];

    $customerType = $answers['customer_type'] ?? null;
    $urgency = $answers['urgency'] ?? null;

    $street = $answers['street'] ?? null;
    $postalCode = $answers['postal_code'] ?? null;
    $city = $answers['city'] ?? null;

    $addressParts = collect([
        $street,
        trim(($postalCode ?? '') . ' ' . ($city ?? '')),
    ])->filter();

    $address = trim($addressParts->implode(', '));

    $rows = [
        $text['service'] => $serviceTitle,
        $text['request_type'] => $requestTypeLabel,
        $text['customer_type'] => $text['customer_types'][$customerType] ?? $text['empty'],
        $text['urgency'] => $text['urgencies'][$urgency] ?? $text['empty'],
        $text['address'] => $address ?: $text['empty'],
        $text['availability'] => $answers['availability'] ?? $text['empty'],
        $text['brand'] => $customerRequest->brand ?: $text['empty'],
        $text['model'] => $customerRequest->device_model ?: $text['empty'],
        $text['serial'] => $customerRequest->serial_number ?: $text['empty'],
        $text['unknown_device'] => $customerRequest->unknown_device_details ? $text['yes'] : $text['no'],
        $text['attachments'] => $customerRequest->attachments->count() . ' ' . $text['files'],
    ];
@endphp

<!DOCTYPE html>
<html lang="{{ $locale }}">
<head>
    <meta charset="UTF-8">
    <title>{{ $text['title'] }}</title>
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
                                {{ $text['title'] }}
                            </h1>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding: 30px 32px;">
                            <p style="margin: 0 0 22px; font-size: 16px; line-height: 1.6; color: #405163;">
                                {{ $text['hello'] }} {{ $customerRequest->customer_name }},
                            </p>

                            <p style="margin: 0 0 22px; font-size: 16px; line-height: 1.6; color: #405163;">
                                {{ $text['intro'] }}
                            </p>

                            <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 24px; border: 1px solid #d9e4ef; border-radius: 14px; overflow: hidden;">
                                <tr>
                                    <td colspan="2" style="padding: 14px 18px; background: #edf5ff; color: #0f3557; font-weight: bold;">
                                        {{ $text['summary'] }}
                                    </td>
                                </tr>

                                @foreach ($rows as $label => $value)
                                    <tr>
                                        <td style="width: 38%; padding: 12px 18px; border-top: 1px solid #d9e4ef; color: #6b7c8f; font-weight: bold; vertical-align: top;">
                                            {{ $label }}
                                        </td>

                                        <td style="padding: 12px 18px; border-top: 1px solid #d9e4ef; vertical-align: top; color: #102033;">
                                            {{ $value }}
                                        </td>
                                    </tr>
                                @endforeach
                            </table>

                            <div style="margin-bottom: 24px;">
                                <h2 style="margin: 0 0 10px; font-size: 18px; color: #0f3557;">
                                    {{ $text['description'] }}
                                </h2>

                                <div style="padding: 16px 18px; border-radius: 14px; background: #f8fbff; border: 1px solid #d9e4ef; color: #405163; line-height: 1.6;">
                                    {{ $customerRequest->description }}
                                </div>
                            </div>

                            <p style="margin: 0 0 14px; font-size: 15px; line-height: 1.6; color: #405163;">
                                {{ $text['next'] }}
                            </p>

                            <p style="margin: 0; color: #6b7c8f; font-size: 14px; line-height: 1.6;">
                                {{ $text['automatic'] }} {{ $siteName }}.
                            </p>
                        </td>
                    </tr>
                </table>

                <p style="margin: 18px 0 0; color: #8aa0b5; font-size: 12px;">
                    &copy; {{ date('Y') }} {{ $siteName }}
                </p>
            </td>
        </tr>
    </table>
</body>
</html>