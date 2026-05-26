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
            'code' => 'customer_context',
            'labels' => [
                'nl' => '3. Klant en urgentie',
                'fr' => '3. Client et urgence',
                'en' => '3. Customer and urgency',
            ],
            'type' => 'fields',
            'fields' => [
                [
                    'name' => 'customer_type',
                    'type' => 'select',
                    'required' => true,
                    'labels' => [
                        'nl' => 'Klanttype',
                        'fr' => 'Type de client',
                        'en' => 'Customer type',
                    ],
                    'options' => [
                        [
                            'value' => 'residential',
                            'labels' => [
                                'nl' => 'Particulier',
                                'fr' => 'Particulier',
                                'en' => 'Residential',
                            ],
                        ],
                        [
                            'value' => 'business',
                            'labels' => [
                                'nl' => 'Bedrijf',
                                'fr' => 'Entreprise',
                                'en' => 'Business',
                            ],
                        ],
                    ],
                ],
                [
                    'name' => 'urgency',
                    'type' => 'select',
                    'required' => true,
                    'labels' => [
                        'nl' => 'Urgentie',
                        'fr' => 'Urgence',
                        'en' => 'Urgency',
                    ],
                    'options' => [
                        [
                            'value' => 'urgent',
                            'labels' => [
                                'nl' => 'Dringend',
                                'fr' => 'Urgent',
                                'en' => 'Urgent',
                            ],
                        ],
                        [
                            'value' => 'within_days',
                            'labels' => [
                                'nl' => 'Binnen enkele dagen',
                                'fr' => 'Dans quelques jours',
                                'en' => 'Within a few days',
                            ],
                        ],
                        [
                            'value' => 'not_urgent',
                            'labels' => [
                                'nl' => 'Niet dringend',
                                'fr' => 'Pas urgent',
                                'en' => 'Not urgent',
                            ],
                        ],
                    ],
                ],
            ],
        ],

        [
            'code' => 'description',
            'labels' => [
                'nl' => '4. Probleem of project',
                'fr' => '4. Problème ou projet',
                'en' => '4. Issue or project',
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
                    'nl' => 'Voeg indien mogelijk foto’s toe van het toestel, typeplaatje, foutcode of probleemzone.',
                    'fr' => 'Ajoutez si possible des photos de l’appareil, de la plaque signalétique, du code erreur ou de la zone du problème.',
                    'en' => 'If possible, add photos of the unit, nameplate, error code or problem area.',
                ],
            ],
        ],

        [
            'code' => 'technical_details',
            'labels' => [
                'nl' => '5. Technische gegevens',
                'fr' => '5. Informations techniques',
                'en' => '5. Technical details',
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
                        'nl' => 'Ik weet merk/model niet',
                        'fr' => 'Je ne connais pas la marque/le modèle',
                        'en' => 'I don’t know the brand/model',
                    ],
                ],
            ],
        ],

        [
            'code' => 'location_availability',
            'labels' => [
                'nl' => '6. Locatie en beschikbaarheid',
                'fr' => '6. Lieu et disponibilité',
                'en' => '6. Location and availability',
            ],
            'type' => 'fields',
            'fields' => [
                [
                    'name' => 'street',
                    'type' => 'text',
                    'required' => true,
                    'labels' => [
                        'nl' => 'Straat en nummer',
                        'fr' => 'Rue et numéro',
                        'en' => 'Street and number',
                    ],
                    'placeholder' => [
                        'nl' => 'Voorbeeldstraat 12',
                        'fr' => 'Rue exemple 12',
                        'en' => 'Example street 12',
                    ],
                ],
                [
                    'name' => 'postal_code',
                    'type' => 'text',
                    'required' => true,
                    'labels' => [
                        'nl' => 'Postcode',
                        'fr' => 'Code postal',
                        'en' => 'Postal code',
                    ],
                    'placeholder' => [
                        'nl' => '1000',
                        'fr' => '1000',
                        'en' => '1000',
                    ],
                ],
                [
                    'name' => 'city',
                    'type' => 'text',
                    'required' => true,
                    'labels' => [
                        'nl' => 'Gemeente',
                        'fr' => 'Commune',
                        'en' => 'City',
                    ],
                    'placeholder' => [
                        'nl' => 'Brussel',
                        'fr' => 'Bruxelles',
                        'en' => 'Brussels',
                    ],
                ],
                [
                    'name' => 'availability',
                    'type' => 'textarea',
                    'required' => false,
                    'labels' => [
                        'nl' => 'Beschikbaarheid of voorkeurmoment',
                        'fr' => 'Disponibilité ou moment préféré',
                        'en' => 'Availability or preferred moment',
                    ],
                    'placeholder' => [
                        'nl' => 'Bijvoorbeeld: liefst in de voormiddag, niet op woensdag...',
                        'fr' => 'Par exemple : de préférence le matin, pas le mercredi...',
                        'en' => 'For example: preferably in the morning, not on Wednesday...',
                    ],
                ],
            ],
        ],

        [
            'code' => 'contact_details',
            'labels' => [
                'nl' => '7. Contactgegevens',
                'fr' => '7. Coordonnées',
                'en' => '7. Contact details',
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
                'nl' => '8. Samenvatting',
                'fr' => '8. Résumé',
                'en' => '8. Summary',
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