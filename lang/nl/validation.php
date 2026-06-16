<?php

return [
    'accepted'             => 'Het :attribute moet geaccepteerd worden.',
    'array'                => 'Het :attribute moet een lijst zijn.',
    'boolean'              => 'Het :attribute moet waar of onwaar zijn.',
    'email'                => 'Het :attribute moet een geldig e-mailadres zijn.',
    'file'                 => 'Het :attribute moet een bestand zijn.',
    'in'                   => 'De geselecteerde :attribute is ongeldig.',
    'integer'              => 'Het :attribute moet een geheel getal zijn.',
    'mimes'                => 'Het :attribute moet een bestand zijn van het type: :values.',
    'numeric'              => 'Het :attribute moet een getal zijn.',
    'regex'                => 'Het formaat van :attribute is ongeldig.',
    'required'             => 'Het :attribute is verplicht.',
    'string'               => 'Het :attribute moet een tekst zijn.',

    'max' => [
        'array'   => 'Het :attribute mag niet meer dan :max items bevatten.',
        'file'    => 'Het :attribute mag niet groter zijn dan :max kilobytes.',
        'numeric' => 'Het :attribute mag niet groter zijn dan :max.',
        'string'  => 'Het :attribute mag niet meer dan :max tekens bevatten.',
    ],

    'min' => [
        'array'   => 'Het :attribute moet minstens :min items bevatten.',
        'file'    => 'Het :attribute moet minstens :min kilobytes zijn.',
        'numeric' => 'Het :attribute moet minstens :min zijn.',
        'string'  => 'Het :attribute moet minstens :min tekens bevatten.',
    ],

    'custom' => [
        'privacy_consent' => [
            'required' => 'U moet akkoord gaan met de privacyverklaring voor u de aanvraag kunt verzenden.',
            'accepted' => 'U moet akkoord gaan met de privacyverklaring voor u de aanvraag kunt verzenden.',
        ],
    ],

    'attributes' => [],
];
