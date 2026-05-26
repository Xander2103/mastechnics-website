<?php
return [
    'steps' => [
        [
            'code' => 'service',
            'labels' => [
                'nl' => '1. Kies je dienst',
                'fr' => '1. Choisissez votre service',
                'en' => '1. Choose your service',
            ],
            'type' => 'service_selection',
        ],

        [
            'code' => 'request_type',
            'labels' => [
                'nl' => '2. Type aanvraag',
                'fr' => '2. Type de demande',
                'en' => '2. Request type',
            ],
            'type' => 'request_type_selection',
        ],

        [
            'code' => 'description',
            'labels' => [
                'nl' => '3. Probleem of project',
                'fr' => '3. Problème ou projet',
                'en' => '3. Issue or project',
            ],
            'type' => 'fields',
            'fields' => [
                [
                    'name' => 'description',
                    'type' => 'textarea',
                    'required' => true,
                    'labels' => [
                        'nl' => 'Beschrijf kort je probleem of project',
                        'fr' => 'Décrivez brièvement votre problème ou projet',
                        'en' => 'Briefly describe your issue or project',
                    ],
                    'placeholder' => [
                        'nl' => 'Beschrijf wat er aan de hand is...',
                        'fr' => 'Décrivez la situation...',
                        'en' => 'Describe what is going on...',
                    ],
                ],
            ],
            'helper_box' => [
                'title' => [
                    'nl' => 'Foto’s toevoegen',
                    'fr' => 'Ajouter des photos',
                    'en' => 'Add photos',
                ],
                'text' => [
                    'nl' => 'Foto-upload komt later. Voor nu kan de klant het probleem beschrijven.',
                    'fr' => 'L’upload de photos sera ajouté plus tard. Pour l’instant, le client peut décrire le problème.',
                    'en' => 'Photo upload will be added later. For now, the customer can describe the issue.',
                ],
            ],
        ],

        [
            'code' => 'technical_details',
            'labels' => [
                'nl' => '4. Technische gegevens',
                'fr' => '4. Informations techniques',
                'en' => '4. Technical details',
            ],
            'type' => 'fields',
            'fields' => [
                [
                    'name' => 'brand',
                    'type' => 'text',
                    'required' => false,
                    'labels' => [
                        'nl' => 'Merk',
                        'fr' => 'Marque',
                        'en' => 'Brand',
                    ],
                    'placeholder' => [
                        'nl' => 'Vaillant, Daikin, Bosch...',
                        'fr' => 'Vaillant, Daikin, Bosch...',
                        'en' => 'Vaillant, Daikin, Bosch...',
                    ],
                ],
                [
                    'name' => 'device_model',
                    'type' => 'text',
                    'required' => false,
                    'labels' => [
                        'nl' => 'Model',
                        'fr' => 'Modèle',
                        'en' => 'Model',
                    ],
                    'placeholder' => [
                        'nl' => 'ecoTEC plus, Altherma...',
                        'fr' => 'ecoTEC plus, Altherma...',
                        'en' => 'ecoTEC plus, Altherma...',
                    ],
                ],
                [
                    'name' => 'serial_number',
                    'type' => 'text',
                    'required' => false,
                    'labels' => [
                        'nl' => 'Serienummer',
                        'fr' => 'Numéro de série',
                        'en' => 'Serial number',
                    ],
                    'placeholder' => [
                        'nl' => 'SN / serial...',
                        'fr' => 'SN / serial...',
                        'en' => 'SN / serial...',
                    ],
                ],
                [
                    'name' => 'unknown_device_details',
                    'type' => 'checkbox',
                    'required' => false,
                    'labels' => [
                        'nl' => 'Ik weet dit niet',
                        'fr' => 'Je ne sais pas',
                        'en' => 'I don’t know',
                    ],
                ],
            ],
        ],

        [
            'code' => 'contact_details',
            'labels' => [
                'nl' => '5. Contactgegevens',
                'fr' => '5. Coordonnées',
                'en' => '5. Contact details',
            ],
            'type' => 'fields',
            'fields' => [
                [
                    'name' => 'customer_name',
                    'type' => 'text',
                    'required' => true,
                    'labels' => [
                        'nl' => 'Naam',
                        'fr' => 'Nom',
                        'en' => 'Name',
                    ],
                ],
                [
                    'name' => 'customer_email',
                    'type' => 'email',
                    'required' => true,
                    'labels' => [
                        'nl' => 'E-mailadres',
                        'fr' => 'Adresse e-mail',
                        'en' => 'Email address',
                    ],
                ],
                [
                    'name' => 'customer_phone',
                    'type' => 'tel',
                    'required' => false,
                    'labels' => [
                        'nl' => 'Telefoonnummer',
                        'fr' => 'Numéro de téléphone',
                        'en' => 'Phone number',
                    ],
                ],
            ],
        ],

        [
            'code' => 'summary',
            'labels' => [
                'nl' => '6. Samenvatting',
                'fr' => '6. Résumé',
                'en' => '6. Summary',
            ],
            'type' => 'summary',
        ],
    ],

    'request_types' => [
        [
            'value' => 'repair',
            'labels' => [
                'nl' => 'Herstelling',
                'fr' => 'Réparation',
                'en' => 'Repair',
            ],
        ],
        [
            'value' => 'maintenance',
            'labels' => [
                'nl' => 'Onderhoud',
                'fr' => 'Entretien',
                'en' => 'Maintenance',
            ],
        ],
        [
            'value' => 'installation',
            'labels' => [
                'nl' => 'Installatie',
                'fr' => 'Installation',
                'en' => 'Installation',
            ],
        ],
        [
            'value' => 'new_project',
            'labels' => [
                'nl' => 'Nieuw project',
                'fr' => 'Nouveau projet',
                'en' => 'New project',
            ],
        ],
    ],
];