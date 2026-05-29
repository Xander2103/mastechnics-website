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
            'code' => 'airco_project_details',
            'labels' => [
                'nl' => '5. Extra airco-informatie',
                'fr' => '5. Informations climatisation',
                'en' => '5. Extra air conditioning details',
            ],
            'type' => 'fields',
            'condition' => [
                'service_slug' => ['airco', 'climatisation', 'air-conditioning'],
                'request_types' => ['installation', 'new_project'],
            ],
            'fields' => [
                [
                    'name' => 'airco_rooms_count',
                    'type' => 'number',
                    'required' => true,
                    'conditional_required' => true,
                    'labels' => [
                        'nl' => 'Aantal kamers / ruimtes',
                        'fr' => 'Nombre de pièces / espaces',
                        'en' => 'Number of rooms / areas',
                    ],
                    'placeholder' => [
                        'nl' => 'Bijvoorbeeld: 2',
                        'fr' => 'Par exemple : 2',
                        'en' => 'For example: 2',
                    ],
                ],
                [
                    'name' => 'airco_total_surface',
                    'type' => 'text',
                    'required' => true,
                    'conditional_required' => true,
                    'labels' => [
                        'nl' => 'Totale oppervlakte ongeveer',
                        'fr' => 'Surface totale approximative',
                        'en' => 'Approximate total surface',
                    ],
                    'placeholder' => [
                        'nl' => 'Bijvoorbeeld: 45 m²',
                        'fr' => 'Par exemple : 45 m²',
                        'en' => 'For example: 45 m²',
                    ],
                ],
                [
                    'name' => 'airco_room_types',
                    'type' => 'text',
                    'required' => false,
                    'labels' => [
                        'nl' => 'Type ruimtes',
                        'fr' => 'Type de pièces',
                        'en' => 'Room types',
                    ],
                    'placeholder' => [
                        'nl' => 'Slaapkamer, living, bureau, winkelruimte...',
                        'fr' => 'Chambre, salon, bureau, commerce...',
                        'en' => 'Bedroom, living room, office, shop...',
                    ],
                ],
                [
                    'name' => 'airco_building_type',
                    'type' => 'select',
                    'required' => false,
                    'labels' => [
                        'nl' => 'Type gebouw',
                        'fr' => 'Type de bâtiment',
                        'en' => 'Building type',
                    ],
                    'options' => [
                        [
                            'value' => 'house',
                            'labels' => [
                                'nl' => 'Woning',
                                'fr' => 'Maison',
                                'en' => 'House',
                            ],
                        ],
                        [
                            'value' => 'apartment',
                            'labels' => [
                                'nl' => 'Appartement',
                                'fr' => 'Appartement',
                                'en' => 'Apartment',
                            ],
                        ],
                        [
                            'value' => 'office',
                            'labels' => [
                                'nl' => 'Kantoor',
                                'fr' => 'Bureau',
                                'en' => 'Office',
                            ],
                        ],
                        [
                            'value' => 'commercial',
                            'labels' => [
                                'nl' => 'Handelspand',
                                'fr' => 'Commerce',
                                'en' => 'Commercial property',
                            ],
                        ],
                        [
                            'value' => 'other',
                            'labels' => [
                                'nl' => 'Andere',
                                'fr' => 'Autre',
                                'en' => 'Other',
                            ],
                        ],
                    ],
                ],
                [
                    'name' => 'airco_floor',
                    'type' => 'text',
                    'required' => false,
                    'labels' => [
                        'nl' => 'Verdieping',
                        'fr' => 'Étage',
                        'en' => 'Floor',
                    ],
                    'placeholder' => [
                        'nl' => 'Gelijkvloers, 1e verdieping, zolder...',
                        'fr' => 'Rez-de-chaussée, 1er étage, grenier...',
                        'en' => 'Ground floor, first floor, attic...',
                    ],
                ],
                [
                    'name' => 'airco_outdoor_unit_possible',
                    'type' => 'select',
                    'required' => false,
                    'labels' => [
                        'nl' => 'Buitenunit mogelijk?',
                        'fr' => 'Unité extérieure possible ?',
                        'en' => 'Outdoor unit possible?',
                    ],
                    'options' => [
                        [
                            'value' => 'yes',
                            'labels' => [
                                'nl' => 'Ja',
                                'fr' => 'Oui',
                                'en' => 'Yes',
                            ],
                        ],
                        [
                            'value' => 'no',
                            'labels' => [
                                'nl' => 'Nee',
                                'fr' => 'Non',
                                'en' => 'No',
                            ],
                        ],
                        [
                            'value' => 'unknown',
                            'labels' => [
                                'nl' => 'Ik weet het niet',
                                'fr' => 'Je ne sais pas',
                                'en' => 'I don’t know',
                            ],
                        ],
                    ],
                ],
                [
                    'name' => 'airco_sun_exposure',
                    'type' => 'select',
                    'required' => false,
                    'labels' => [
                        'nl' => 'Veel zon of grote ramen?',
                        'fr' => 'Beaucoup de soleil ou grandes fenêtres ?',
                        'en' => 'Lots of sun or large windows?',
                    ],
                    'options' => [
                        [
                            'value' => 'yes',
                            'labels' => [
                                'nl' => 'Ja',
                                'fr' => 'Oui',
                                'en' => 'Yes',
                            ],
                        ],
                        [
                            'value' => 'no',
                            'labels' => [
                                'nl' => 'Nee',
                                'fr' => 'Non',
                                'en' => 'No',
                            ],
                        ],
                        [
                            'value' => 'unknown',
                            'labels' => [
                                'nl' => 'Ik weet het niet',
                                'fr' => 'Je ne sais pas',
                                'en' => 'I don’t know',
                            ],
                        ],
                    ],
                ],
                [
                    'name' => 'airco_extra_info',
                    'type' => 'textarea',
                    'required' => false,
                    'labels' => [
                        'nl' => 'Extra uitleg over de ruimtes',
                        'fr' => 'Informations supplémentaires sur les espaces',
                        'en' => 'Extra information about the rooms',
                    ],
                    'placeholder' => [
                        'nl' => 'Bijvoorbeeld: living met veel glas, slaapkamers op zolder, buitenunit kan op plat dak...',
                        'fr' => 'Par exemple : salon avec beaucoup de vitrage, chambres sous le toit, unité extérieure possible sur toit plat...',
                        'en' => 'For example: living room with lots of glass, bedrooms in the attic, outdoor unit can be placed on flat roof...',
                    ],
                ],
            ],
        ],

        [
            'code' => 'technical_details',
            'labels' => [
                'nl' => '6. Technische gegevens',
                'fr' => '6. Informations techniques',
                'en' => '6. Technical details',
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
                        'nl' => 'Ik weet merk/model/serienummer niet',
                        'fr' => 'Je ne connais pas la marque/le modèle/le numéro de série',
                        'en' => 'I don’t know the brand/model/serial number',
                    ],
                ],
            ],
        ],

        [
            'code' => 'location_availability',
            'labels' => [
                'nl' => '7. Locatie en beschikbaarheid',
                'fr' => '7. Lieu et disponibilité',
                'en' => '7. Location and availability',
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
                'nl' => '8. Contactgegevens',
                'fr' => '8. Coordonnées',
                'en' => '8. Contact details',
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
                'nl' => '9. Samenvatting',
                'fr' => '9. Résumé',
                'en' => '9. Summary',
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