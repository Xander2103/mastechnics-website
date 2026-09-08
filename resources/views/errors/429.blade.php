@php
    $copy = [
        'nl' => [
            'title' => 'Te veel pogingen',
            'meta_title' => 'Te veel pogingen | Mastechnics',
            'intro' => 'Er kwamen te veel verzoeken vanaf uw verbinding in korte tijd. Wacht enkele minuten en probeer het dan opnieuw. Voor een dringende vraag kunt u ons telefonisch bereiken op ' . config('site.contact.phone_display') . '.',
            'home' => 'Naar de homepage',
        ],
        'fr' => [
            'title' => 'Trop de tentatives',
            'meta_title' => 'Trop de tentatives | Mastechnics',
            'intro' => 'Trop de requêtes ont été reçues depuis votre connexion en peu de temps. Attendez quelques minutes et réessayez. Pour une question urgente, appelez-nous au ' . config('site.contact.phone_display') . '.',
            'home' => 'Retour à l\'accueil',
        ],
        'en' => [
            'title' => 'Too many attempts',
            'meta_title' => 'Too many attempts | Mastechnics',
            'intro' => 'Too many requests came from your connection in a short time. Wait a few minutes and try again. For urgent questions you can reach us by phone at ' . config('site.contact.phone_display') . '.',
            'home' => 'Back to the homepage',
        ],
    ];
@endphp
@include('errors.partials.simple-error', ['code' => '429', 'copy' => $copy])
