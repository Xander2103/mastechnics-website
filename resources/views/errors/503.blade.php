@php
    $phone = config('site.contact.phone_display');

    $copy = [
        'nl' => [
            'title' => 'Even in onderhoud',
            'meta_title' => 'Even in onderhoud | Mastechnics',
            'intro' => 'De website wordt op dit moment bijgewerkt en is binnen enkele minuten opnieuw beschikbaar. Voor een dringende vraag kunt u ons telefonisch bereiken op ' . $phone . '.',
            'home' => 'Opnieuw proberen',
        ],
        'fr' => [
            'title' => 'Maintenance en cours',
            'meta_title' => 'Maintenance en cours | Mastechnics',
            'intro' => 'Le site est en cours de mise à jour et sera de nouveau disponible dans quelques minutes. Pour une question urgente, appelez-nous au ' . $phone . '.',
            'home' => 'Réessayer',
        ],
        'en' => [
            'title' => 'Down for maintenance',
            'meta_title' => 'Down for maintenance | Mastechnics',
            'intro' => 'The website is being updated and will be back within a few minutes. For urgent questions you can reach us by phone at ' . $phone . '.',
            'home' => 'Try again',
        ],
    ];
@endphp
@include('errors.partials.simple-error', ['code' => '503', 'copy' => $copy])
