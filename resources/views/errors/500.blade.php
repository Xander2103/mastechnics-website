@php
    $phone = config('site.contact.phone_display');

    $copy = [
        'nl' => [
            'title' => 'Er ging iets mis',
            'meta_title' => 'Er ging iets mis | Mastechnics',
            'intro' => 'Door een technische fout kon deze pagina niet geladen worden. Probeer het over enkele minuten opnieuw. Voor een dringende vraag kunt u ons telefonisch bereiken op ' . $phone . '.',
            'home' => 'Naar de homepage',
        ],
        'fr' => [
            'title' => 'Une erreur s\'est produite',
            'meta_title' => 'Une erreur s\'est produite | Mastechnics',
            'intro' => 'Suite à une erreur technique, cette page n\'a pas pu être chargée. Réessayez dans quelques minutes. Pour une question urgente, appelez-nous au ' . $phone . '.',
            'home' => 'Retour à l\'accueil',
        ],
        'en' => [
            'title' => 'Something went wrong',
            'meta_title' => 'Something went wrong | Mastechnics',
            'intro' => 'A technical error prevented this page from loading. Please try again in a few minutes. For urgent questions you can reach us by phone at ' . $phone . '.',
            'home' => 'Back to the homepage',
        ],
    ];
@endphp
@include('errors.partials.simple-error', ['code' => '500', 'copy' => $copy])
