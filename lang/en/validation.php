<?php

// English counterpart of lang/nl and lang/fr: without it the framework's
// generic messages (and an untranslated privacy-consent error) were shown.
return [
    'accepted'             => 'The ":attribute" field must be accepted.',
    'array'                => 'The ":attribute" field must be a list.',
    'boolean'              => 'The ":attribute" field must be true or false.',
    'email'                => 'The ":attribute" field must be a valid e-mail address.',
    'file'                 => 'The ":attribute" field must be a file.',
    'in'                   => 'The selected value for ":attribute" is invalid.',
    'integer'              => 'The ":attribute" field must be a whole number.',
    'mimes'                => 'The ":attribute" field must be a file of type: :values.',
    'numeric'              => 'The ":attribute" field must be a number.',
    'regex'                => 'The format of ":attribute" is invalid.',
    'required'             => 'The ":attribute" field is required.',
    'string'               => 'The ":attribute" field must be text.',
    'uploaded'             => 'The ":attribute" file could not be uploaded. Try a smaller file.',

    'max' => [
        'array'   => 'The ":attribute" field may not contain more than :max items.',
        'file'    => 'The ":attribute" file may not be larger than :max kilobytes.',
        'numeric' => 'The ":attribute" field may not be greater than :max.',
        'string'  => 'The ":attribute" field may not contain more than :max characters.',
    ],

    'min' => [
        'array'   => 'The ":attribute" field must contain at least :min items.',
        'file'    => 'The ":attribute" file must be at least :min kilobytes.',
        'numeric' => 'The ":attribute" field must be at least :min.',
        'string'  => 'The ":attribute" field must contain at least :min characters.',
    ],

    'custom' => [
        'privacy_consent' => [
            'required' => 'You must agree to the privacy policy before sending the request.',
            'accepted' => 'You must agree to the privacy policy before sending the request.',
        ],
    ],

    'attributes' => [],
];
