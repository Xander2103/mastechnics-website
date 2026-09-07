<?php

// Attribute names are wrapped in "veld …" so the sentence stays grammatical
// for every gender ("Het naam is verplicht" was wrong for de-woorden).
return [
    'accepted'             => 'Het veld ":attribute" moet geaccepteerd worden.',
    'array'                => 'Het veld ":attribute" moet een lijst zijn.',
    'boolean'              => 'Het veld ":attribute" moet waar of onwaar zijn.',
    'email'                => 'Het veld ":attribute" moet een geldig e-mailadres zijn.',
    'file'                 => 'Het veld ":attribute" moet een bestand zijn.',
    'in'                   => 'De gekozen waarde voor ":attribute" is ongeldig.',
    'integer'              => 'Het veld ":attribute" moet een geheel getal zijn.',
    'mimes'                => 'Het veld ":attribute" moet een bestand zijn van het type: :values.',
    'numeric'              => 'Het veld ":attribute" moet een getal zijn.',
    'regex'                => 'Het formaat van ":attribute" is ongeldig.',
    'required'             => 'Het veld ":attribute" is verplicht.',
    'string'               => 'Het veld ":attribute" moet een tekst zijn.',
    'uploaded'             => 'Het bestand ":attribute" kon niet geüpload worden. Probeer een kleiner bestand.',

    'max' => [
        'array'   => 'Het veld ":attribute" mag niet meer dan :max items bevatten.',
        'file'    => 'Het bestand ":attribute" mag niet groter zijn dan :max kilobytes.',
        'numeric' => 'Het veld ":attribute" mag niet groter zijn dan :max.',
        'string'  => 'Het veld ":attribute" mag niet meer dan :max tekens bevatten.',
    ],

    'min' => [
        'array'   => 'Het veld ":attribute" moet minstens :min items bevatten.',
        'file'    => 'Het bestand ":attribute" moet minstens :min kilobytes zijn.',
        'numeric' => 'Het veld ":attribute" moet minstens :min zijn.',
        'string'  => 'Het veld ":attribute" moet minstens :min tekens bevatten.',
    ],

    'custom' => [
        'privacy_consent' => [
            'required' => 'U moet akkoord gaan met de privacyverklaring voor u de aanvraag kunt verzenden.',
            'accepted' => 'U moet akkoord gaan met de privacyverklaring voor u de aanvraag kunt verzenden.',
        ],
    ],

    'attributes' => [],
];
