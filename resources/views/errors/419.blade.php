@php
    $segment = request()->segment(1);
    $errLocale = in_array($segment, config('site.locales', ['nl', 'fr', 'en']), true) ? $segment : 'nl';
    $formSlug = config("site.page_slugs.request.{$errLocale}");
    $formUrl = is_string($formSlug) && $formSlug !== '' ? url("/{$errLocale}/{$formSlug}") : url("/{$errLocale}");

    $copy = [
        'nl' => [
            'title' => 'Uw sessie is verlopen',
            'meta_title' => 'Sessie verlopen | Mastechnics',
            'intro' => 'De pagina stond te lang open, waardoor het formulier niet meer verzonden kon worden. Er is niets verzonden. Open het formulier opnieuw en probeer het nog eens.',
            'home' => 'Naar de homepage',
            'cta' => 'Formulier opnieuw openen',
            'cta_url' => $formUrl,
        ],
        'fr' => [
            'title' => 'Votre session a expiré',
            'meta_title' => 'Session expirée | Mastechnics',
            'intro' => 'La page est restée ouverte trop longtemps et le formulaire n\'a pas pu être envoyé. Rien n\'a été transmis. Rouvrez le formulaire et réessayez.',
            'home' => 'Retour à l\'accueil',
            'cta' => 'Rouvrir le formulaire',
            'cta_url' => $formUrl,
        ],
        'en' => [
            'title' => 'Your session has expired',
            'meta_title' => 'Session expired | Mastechnics',
            'intro' => 'The page was open for too long, so the form could not be submitted. Nothing was sent. Open the form again and retry.',
            'home' => 'Back to the homepage',
            'cta' => 'Open the form again',
            'cta_url' => $formUrl,
        ],
    ];
@endphp
@include('errors.partials.simple-error', ['code' => '419', 'copy' => $copy])
