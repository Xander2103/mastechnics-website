<?php

return [
    'enabled' => true,

    'platforms' => [
        'google' => [
            'label' => 'Google',
            'url' => env('GOOGLE_REVIEWS_URL', 'https://www.google.com/search?q=Mas+Technics+Reviews'),
        ],
        'trustpilot' => [
            'label' => 'Trustpilot',
            'url' => env('TRUSTPILOT_REVIEWS_URL', 'https://nl.trustpilot.com/review/mastechnics.be'),
        ],
        'facebook' => [
            'label' => 'Facebook',
            'url' => env('FACEBOOK_URL', 'https://www.facebook.com/mastechnics'),
        ],
    ],

    // No reviews are pre-populated here. Google, Trustpilot and Facebook all
    // block automated/anonymous access to individual review text, so none
    // could be verified for inclusion — do not invent authors, ratings,
    // dates or text. Add only genuine, manually-verified reviews using the
    // structure below (copy the text yourself from the public listing):
    //
    // [
    //     'author'          => 'Public display name',
    //     'rating'          => 5,
    //     'date'            => 'YYYY-MM-DD',
    //     'source'          => 'trustpilot', // google | trustpilot | facebook
    //     'source_url'      => 'https://...',
    //     'original_locale' => 'fr',
    //     'original_text'   => 'Exact public review text, in its original language.',
    //     'translations'    => [
    //         'nl' => 'Faithful Dutch translation (optional if original_locale is nl).',
    //         'fr' => 'Faithful French translation (optional if original_locale is fr).',
    //         'en' => 'Faithful English translation (optional if original_locale is en).',
    //     ],
    // ],
    'reviews' => [],
];
