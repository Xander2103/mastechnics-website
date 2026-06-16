<?php

return [
    'accepted'             => 'Le :attribute doit être accepté.',
    'array'                => 'Le :attribute doit être une liste.',
    'boolean'              => 'Le :attribute doit être vrai ou faux.',
    'email'                => 'Le :attribute doit être une adresse e-mail valide.',
    'file'                 => 'Le :attribute doit être un fichier.',
    'in'                   => 'Le :attribute sélectionné est invalide.',
    'integer'              => 'Le :attribute doit être un nombre entier.',
    'mimes'                => 'Le :attribute doit être un fichier de type : :values.',
    'numeric'              => 'Le :attribute doit être un nombre.',
    'regex'                => 'Le format du :attribute est invalide.',
    'required'             => 'Le :attribute est obligatoire.',
    'string'               => 'Le :attribute doit être une chaîne de caractères.',

    'max' => [
        'array'   => 'Le :attribute ne peut pas contenir plus de :max éléments.',
        'file'    => 'Le :attribute ne peut pas dépasser :max kilooctets.',
        'numeric' => 'Le :attribute ne peut pas être supérieur à :max.',
        'string'  => 'Le :attribute ne peut pas dépasser :max caractères.',
    ],

    'min' => [
        'array'   => 'Le :attribute doit contenir au moins :min éléments.',
        'file'    => 'Le :attribute doit être d\'au moins :min kilooctets.',
        'numeric' => 'Le :attribute doit être au moins :min.',
        'string'  => 'Le :attribute doit contenir au moins :min caractères.',
    ],

    'custom' => [
        'privacy_consent' => [
            'required' => 'Vous devez accepter la déclaration de confidentialité avant d\'envoyer la demande.',
            'accepted' => 'Vous devez accepter la déclaration de confidentialité avant d\'envoyer la demande.',
        ],
    ],

    'attributes' => [],
];
