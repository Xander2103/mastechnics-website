<?php

// Attribute names are wrapped in "champ …" so the sentence stays grammatical
// for every gender ("Le adresse e-mail est obligatoire" was wrong).
return [
    'accepted'             => 'Le champ ":attribute" doit être accepté.',
    'array'                => 'Le champ ":attribute" doit être une liste.',
    'boolean'              => 'Le champ ":attribute" doit être vrai ou faux.',
    'email'                => 'Le champ ":attribute" doit être une adresse e-mail valide.',
    'file'                 => 'Le champ ":attribute" doit être un fichier.',
    'in'                   => 'La valeur choisie pour ":attribute" est invalide.',
    'integer'              => 'Le champ ":attribute" doit être un nombre entier.',
    'mimes'                => 'Le champ ":attribute" doit être un fichier de type : :values.',
    'numeric'              => 'Le champ ":attribute" doit être un nombre.',
    'regex'                => 'Le format du champ ":attribute" est invalide.',
    'required'             => 'Le champ ":attribute" est obligatoire.',
    'string'               => 'Le champ ":attribute" doit être une chaîne de caractères.',
    'uploaded'             => 'Le fichier ":attribute" n\'a pas pu être téléchargé. Essayez un fichier plus petit.',

    'max' => [
        'array'   => 'Le champ ":attribute" ne peut pas contenir plus de :max éléments.',
        'file'    => 'Le fichier ":attribute" ne peut pas dépasser :max kilooctets.',
        'numeric' => 'Le champ ":attribute" ne peut pas être supérieur à :max.',
        'string'  => 'Le champ ":attribute" ne peut pas dépasser :max caractères.',
    ],

    'min' => [
        'array'   => 'Le champ ":attribute" doit contenir au moins :min éléments.',
        'file'    => 'Le fichier ":attribute" doit être d\'au moins :min kilooctets.',
        'numeric' => 'Le champ ":attribute" doit être au moins :min.',
        'string'  => 'Le champ ":attribute" doit contenir au moins :min caractères.',
    ],

    'custom' => [
        'privacy_consent' => [
            'required' => 'Vous devez accepter la déclaration de confidentialité avant d\'envoyer la demande.',
            'accepted' => 'Vous devez accepter la déclaration de confidentialité avant d\'envoyer la demande.',
        ],
    ],

    'attributes' => [],
];
