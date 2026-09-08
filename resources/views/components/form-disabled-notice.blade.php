{{--
    Shown instead of a public form when its kill switch is off
    (CONTACT_FORM_ENABLED / REQUEST_WIZARD_ENABLED). Plain notice, no 500,
    phone number as the fallback channel.
--}}
@php
    $phoneDisplay = config('site.contact.phone_display');
    $phoneLink = config('site.contact.phone_link');
    $copy = [
        'nl' => [
            'title' => 'Formulier tijdelijk niet beschikbaar',
            'text' => 'Dit formulier is even uitgeschakeld voor onderhoud. U kunt ons intussen telefonisch bereiken — we helpen u graag verder.',
            'cta' => 'Bel ' . $phoneDisplay,
        ],
        'fr' => [
            'title' => 'Formulaire temporairement indisponible',
            'text' => 'Ce formulaire est momentanément désactivé pour maintenance. Vous pouvez nous joindre par téléphone en attendant — nous vous aiderons volontiers.',
            'cta' => 'Appeler le ' . $phoneDisplay,
        ],
        'en' => [
            'title' => 'Form temporarily unavailable',
            'text' => 'This form is briefly switched off for maintenance. In the meantime you can reach us by phone — we are happy to help.',
            'cta' => 'Call ' . $phoneDisplay,
        ],
    ];
    $text = $copy[$locale ?? app()->getLocale()] ?? $copy['nl'];
@endphp
<div class="form-disabled-notice" role="status">
    <h3>{{ $text['title'] }}</h3>
    <p>{{ $text['text'] }}</p>
    <a class="button button-primary" href="tel:{{ $phoneLink }}">{{ $text['cta'] }}</a>
</div>
