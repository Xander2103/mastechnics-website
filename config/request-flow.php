<?php

return [
    // ─────────────────────────────────────────────────────────────────────────
    // STEPS
    // Step 0  : service_category_selection   (replaces old steps 0 + 1)
    // Step 1  : cv_onderhoud_details         (conditional: onderhoud_cv)
    // Step 2  : lek_dringend_details         (conditional: dringend_lek)
    // Step 3  : airco_offerte_details        (conditional: airco_offerte)
    // Step 4  : airco_onderhoud_details      (conditional: airco_onderhoud)
    // Step 5  : customer_context
    // Step 6  : description                  (file upload lives here)
    // Step 7  : technical_details
    // Step 8  : location_availability
    // Step 9  : contact_details
    // Step 10 : summary
    // ─────────────────────────────────────────────────────────────────────────
    'steps' => [

        // ── Step 0 ───────────────────────────────────────────────────────────
        [
            'code'   => 'service_category_selection',
            'type'   => 'service_category_selection',
            'labels' => [
                'nl' => 'Waarmee kunnen we u helpen?',
                'fr' => 'Comment pouvons-nous vous aider ?',
                'en' => 'How can we help you?',
            ],
            'helper_text' => [
                'nl' => 'Kies wat het best past. Twijfelt u? Kies \'Ik weet het niet\', dan bekijken we het voor u.',
                'fr' => 'Choisissez ce qui correspond le mieux. Vous hésitez ? Choisissez \'Je ne sais pas\', nous verrons cela pour vous.',
                'en' => 'Choose what fits best. Not sure? Choose \'I\'m not sure\', we will look into it for you.',
            ],
            'options' => [
                [
                    'value'       => 'airco_offerte',
                    'labels'      => [
                        'nl' => 'Airco laten plaatsen',
                        'fr' => 'Faire installer une climatisation',
                        'en' => 'Install air conditioning',
                    ],
                    'description' => [
                        'nl' => 'Voor een nieuwe airco-installatie of offerte.',
                        'fr' => 'Pour une nouvelle installation de climatisation ou un devis.',
                        'en' => 'For a new air conditioning installation or quote.',
                    ],
                ],
                [
                    'value'       => 'airco_onderhoud',
                    'labels'      => [
                        'nl' => 'Onderhoud van airco',
                        'fr' => 'Entretien de votre climatisation',
                        'en' => 'Air conditioning maintenance',
                    ],
                    'description' => [
                        'nl' => 'Voor reiniging, controle of periodiek onderhoud.',
                        'fr' => 'Pour nettoyage, contrôle ou entretien périodique.',
                        'en' => 'For cleaning, inspection or periodic maintenance.',
                    ],
                ],
                [
                    'value'       => 'onderhoud_cv',
                    'labels'      => [
                        'nl' => 'Onderhoud van verwarming',
                        'fr' => 'Entretien du chauffage',
                        'en' => 'Heating maintenance',
                    ],
                    'description' => [
                        'nl' => 'Voor onderhoud van uw ketel of centrale verwarming.',
                        'fr' => 'Pour l\'entretien de votre chaudière ou chauffage central.',
                        'en' => 'For maintenance of your boiler or central heating.',
                    ],
                ],
                [
                    'value'       => 'herstelling_cv',
                    'labels'      => [
                        'nl' => 'Verwarming herstellen',
                        'fr' => 'Réparer le chauffage',
                        'en' => 'Fix heating system',
                    ],
                    'description' => [
                        'nl' => 'Voor storingen, geen warm water of verwarming die niet werkt.',
                        'fr' => 'Pour pannes, pas d\'eau chaude ou chauffage qui ne fonctionne pas.',
                        'en' => 'For breakdowns, no hot water or heating that doesn\'t work.',
                    ],
                ],
                [
                    'value'       => 'dringend_lek',
                    'labels'      => [
                        'nl' => 'Dringend probleem of lek',
                        'fr' => 'Problème urgent ou fuite',
                        'en' => 'Urgent issue or leak',
                    ],
                    'description' => [
                        'nl' => 'Bij waterlek, verlies van druk of dringende panne.',
                        'fr' => 'En cas de fuite d\'eau, perte de pression ou panne urgente.',
                        'en' => 'For water leaks, pressure loss or urgent breakdowns.',
                    ],
                ],
                [
                    'value'       => 'sanitair',
                    'labels'      => [
                        'nl' => 'Sanitair of loodgieterij',
                        'fr' => 'Sanitaire ou plomberie',
                        'en' => 'Plumbing or sanitary',
                    ],
                    'description' => [
                        'nl' => 'Voor kranen, leidingen, afvoer, toilet of badkamer.',
                        'fr' => 'Pour robinets, tuyauterie, évacuation, toilette ou salle de bain.',
                        'en' => 'For taps, pipes, drains, toilet or bathroom work.',
                    ],
                ],
                [
                    'value'       => 'ventilatie',
                    'labels'      => [
                        'nl' => 'Ventilatie',
                        'fr' => 'Ventilation',
                        'en' => 'Ventilation',
                    ],
                    'description' => [
                        'nl' => 'Voor plaatsing, onderhoud of problemen met ventilatie.',
                        'fr' => 'Pour installation, entretien ou problèmes de ventilation.',
                        'en' => 'For installation, maintenance or ventilation issues.',
                    ],
                ],
                [
                    'value'       => 'waterverzachter',
                    'labels'      => [
                        'nl' => 'Waterverzachter',
                        'fr' => 'Adoucisseur d\'eau',
                        'en' => 'Water softener',
                    ],
                    'description' => [
                        'nl' => 'Voor installatie, onderhoud of controle.',
                        'fr' => 'Pour installation, entretien ou contrôle.',
                        'en' => 'For installation, maintenance or inspection.',
                    ],
                ],
                [
                    'value'       => 'koeling',
                    'labels'      => [
                        'nl' => 'Koeling of koelcel',
                        'fr' => 'Réfrigération ou chambre froide',
                        'en' => 'Cooling or cold room',
                    ],
                    'description' => [
                        'nl' => 'Voor commerciële of industriële koeling.',
                        'fr' => 'Pour la réfrigération commerciale ou industrielle.',
                        'en' => 'For commercial or industrial refrigeration.',
                    ],
                ],
                [
                    'value'       => 'andere',
                    'labels'      => [
                        'nl' => 'Ik weet het niet / andere vraag',
                        'fr' => 'Je ne sais pas / autre question',
                        'en' => 'I\'m not sure / other question',
                    ],
                    'description' => [
                        'nl' => 'Niet zeker? Beschrijf uw situatie en wij bekijken het.',
                        'fr' => 'Pas sûr ? Décrivez votre situation et nous verrons cela pour vous.',
                        'en' => 'Not sure? Describe your situation and we\'ll look into it.',
                    ],
                ],
            ],
        ],

        // ── Step 1 (conditional) ─────────────────────────────────────────────
        [
            'code'      => 'cv_onderhoud_details',
            'type'      => 'fields',
            'condition' => [
                'service_categories' => ['onderhoud_cv'],
            ],
            'labels' => [
                'nl' => 'Informatie over uw CV-installatie',
                'fr' => 'Informations sur votre installation de chauffage',
                'en' => 'Information about your heating system',
            ],
            'fields' => [
                [
                    'name'     => 'cv_installation_type',
                    'type'     => 'select',
                    'required' => false,
                    'labels'   => [
                        'nl' => 'Type installatie',
                        'fr' => 'Type d\'installation',
                        'en' => 'Installation type',
                    ],
                    'options' => [
                        [
                            'value'  => 'gasketel',
                            'labels' => [
                                'nl' => 'Gasketel',
                                'fr' => 'Chaudière gaz',
                                'en' => 'Gas boiler',
                            ],
                        ],
                        [
                            'value'  => 'gascondensatieketel',
                            'labels' => [
                                'nl' => 'Gascondensatieketel',
                                'fr' => 'Chaudière gaz à condensation',
                                'en' => 'Gas condensing boiler',
                            ],
                        ],
                        [
                            'value'  => 'stookolieketel',
                            'labels' => [
                                'nl' => 'Stookolieketel',
                                'fr' => 'Chaudière mazout',
                                'en' => 'Oil boiler',
                            ],
                        ],
                        [
                            'value'  => 'warmtepomp',
                            'labels' => [
                                'nl' => 'Warmtepomp',
                                'fr' => 'Pompe à chaleur',
                                'en' => 'Heat pump',
                            ],
                        ],
                        [
                            'value'  => 'unknown',
                            'labels' => [
                                'nl' => 'Ik weet het niet',
                                'fr' => 'Je ne sais pas',
                                'en' => 'I don\'t know',
                            ],
                        ],
                    ],
                ],
                [
                    'name'        => 'cv_brand',
                    'type'        => 'text',
                    'required'    => false,
                    'labels'      => [
                        'nl' => 'Merk ketel',
                        'fr' => 'Marque chaudière',
                        'en' => 'Boiler brand',
                    ],
                    'placeholder' => [
                        'nl' => 'Vaillant, Buderus, Viessmann...',
                        'fr' => 'Vaillant, Buderus, Viessmann...',
                        'en' => 'Vaillant, Buderus, Viessmann...',
                    ],
                ],
                [
                    'name'        => 'cv_device_model',
                    'type'        => 'text',
                    'required'    => false,
                    'labels'      => [
                        'nl' => 'Model ketel',
                        'fr' => 'Modèle chaudière',
                        'en' => 'Boiler model',
                    ],
                    'placeholder' => [
                        'nl' => 'ecoTEC plus, Logamax...',
                        'fr' => 'ecoTEC plus, Logamax...',
                        'en' => 'ecoTEC plus, Logamax...',
                    ],
                ],
                [
                    'name'        => 'cv_last_maintenance',
                    'type'        => 'text',
                    'required'    => false,
                    'labels'      => [
                        'nl' => 'Laatste onderhoud (indien gekend)',
                        'fr' => 'Dernier entretien (si connu)',
                        'en' => 'Last service (if known)',
                    ],
                    'placeholder' => [
                        'nl' => 'Bijvoorbeeld: september 2022',
                        'fr' => 'Par exemple : septembre 2022',
                        'en' => 'For example: September 2022',
                    ],
                ],
                [
                    'name'        => 'preferred_time',
                    'type'        => 'text',
                    'required'    => false,
                    'labels'      => [
                        'nl' => 'Gewenst moment',
                        'fr' => 'Moment préféré',
                        'en' => 'Preferred timing',
                    ],
                    'placeholder' => [
                        'nl' => 'Liefst voormiddag, niet op maandag...',
                        'fr' => 'De préférence le matin, pas le lundi...',
                        'en' => 'Preferably mornings, not on Monday...',
                    ],
                ],
            ],
            'helper_box' => [
                'render_upload' => false,
                'title'         => [
                    'nl' => 'Foto typeplaatje uploaden',
                    'fr' => 'Télécharger une photo de la plaque signalétique',
                    'en' => 'Upload a photo of the nameplate',
                ],
                'text'          => [
                    'nl' => 'U vindt dit op of in de ketel, vaak achter het klepje. Zorg dat merk, model en serienummer leesbaar zijn. Upload de foto bij \'Probleem of project\' hieronder.',
                    'fr' => 'Vous la trouverez sur ou dans la chaudière, souvent derrière le panneau. Assurez-vous que la marque, le modèle et le numéro de série sont lisibles. Téléchargez la photo dans \'Problème ou projet\' ci-dessous.',
                    'en' => 'You will find it on or inside the boiler, often behind the front panel. Make sure the brand, model and serial number are legible. Upload the photo in \'Issue or project\' below.',
                ],
            ],
        ],

        // ── Step 2 (conditional) ─────────────────────────────────────────────
        [
            'code'           => 'lek_dringend_details',
            'type'           => 'fields',
            'condition'      => [
                'service_categories' => ['dringend_lek'],
            ],
            'labels'         => [
                'nl' => 'Meer over uw lek of dringend probleem',
                'fr' => 'Plus d\'informations sur votre fuite ou problème urgent',
                'en' => 'More about your leak or urgent issue',
            ],
            'urgent_warning' => [
                'nl' => 'Bij ernstige wateroverlast of direct gevaar: bel ons direct. Dit formulier is voor niet-spoedeisende situaties.',
                'fr' => 'En cas de dégâts des eaux graves ou de danger immédiat : appelez-nous directement. Ce formulaire est pour les situations non urgentes.',
                'en' => 'In case of serious flooding or immediate danger: call us directly. This form is for non-emergency situations.',
            ],
            'fields' => [
                [
                    'name'     => 'lek_location',
                    'type'     => 'select',
                    'required' => false,
                    'labels'   => [
                        'nl' => 'Waar situeert het probleem zich?',
                        'fr' => 'Où se situe le problème ?',
                        'en' => 'Where is the problem located?',
                    ],
                    'options' => [
                        [
                            'value'  => 'centrale_verwarming',
                            'labels' => [
                                'nl' => 'Centrale verwarming',
                                'fr' => 'Chauffage central',
                                'en' => 'Central heating',
                            ],
                        ],
                        [
                            'value'  => 'sanitair',
                            'labels' => [
                                'nl' => 'Sanitair',
                                'fr' => 'Sanitaire',
                                'en' => 'Plumbing / sanitary',
                            ],
                        ],
                        [
                            'value'  => 'boiler',
                            'labels' => [
                                'nl' => 'Boiler / warmwatertoestel',
                                'fr' => 'Chauffe-eau / boiler',
                                'en' => 'Water heater / boiler',
                            ],
                        ],
                        [
                            'value'  => 'leidingen',
                            'labels' => [
                                'nl' => 'Leidingen',
                                'fr' => 'Tuyauterie',
                                'en' => 'Pipes',
                            ],
                        ],
                        [
                            'value'  => 'unknown',
                            'labels' => [
                                'nl' => 'Ik weet het niet',
                                'fr' => 'Je ne sais pas',
                                'en' => 'I don\'t know',
                            ],
                        ],
                    ],
                ],
                [
                    'name'     => 'urgency_level',
                    'type'     => 'select',
                    'required' => false,
                    'labels'   => [
                        'nl' => 'Wat beschrijft de situatie het best?',
                        'fr' => 'Qu\'est-ce qui décrit le mieux la situation ?',
                        'en' => 'What best describes the situation?',
                    ],
                    'options' => [
                        [
                            'value'  => 'water_leaking',
                            'labels' => [
                                'nl' => 'Er staat water / ernstig lek',
                                'fr' => 'Il y a de l\'eau / fuite grave',
                                'en' => 'Standing water / serious leak',
                            ],
                        ],
                        [
                            'value'  => 'small_leak',
                            'labels' => [
                                'nl' => 'Klein lek',
                                'fr' => 'Petite fuite',
                                'en' => 'Small leak',
                            ],
                        ],
                        [
                            'value'  => 'no_heating',
                            'labels' => [
                                'nl' => 'Geen verwarming',
                                'fr' => 'Pas de chauffage',
                                'en' => 'No heating',
                            ],
                        ],
                        [
                            'value'  => 'no_hot_water',
                            'labels' => [
                                'nl' => 'Geen warm water',
                                'fr' => 'Pas d\'eau chaude',
                                'en' => 'No hot water',
                            ],
                        ],
                        [
                            'value'  => 'other',
                            'labels' => [
                                'nl' => 'Andere',
                                'fr' => 'Autre',
                                'en' => 'Other',
                            ],
                        ],
                    ],
                ],
                [
                    'name'        => 'preferred_time',
                    'type'        => 'text',
                    'required'    => false,
                    'labels'      => [
                        'nl' => 'Wanneer bereikbaar?',
                        'fr' => 'Quand disponible ?',
                        'en' => 'When available?',
                    ],
                    'placeholder' => [
                        'nl' => 'Bijvoorbeeld: elke dag bereikbaar, liefst voor 10u...',
                        'fr' => 'Par exemple : disponible tous les jours, de préférence avant 10h...',
                        'en' => 'For example: available any day, preferably before 10am...',
                    ],
                ],
            ],
            'helper_box' => [
                'render_upload' => false,
                'title'         => [
                    'nl' => 'Foto of video toevoegen',
                    'fr' => 'Ajouter une photo ou vidéo',
                    'en' => 'Add a photo or video',
                ],
                'text'          => [
                    'nl' => 'Voeg een foto of video toe van het lek of het probleem bij \'Probleem of project\' hieronder. Dit helpt ons de ernst inschatten.',
                    'fr' => 'Ajoutez une photo ou vidéo de la fuite ou du problème dans \'Problème ou projet\' ci-dessous. Cela nous aide à évaluer la gravité.',
                    'en' => 'Add a photo or video of the leak or problem in \'Issue or project\' below. This helps us assess the severity.',
                ],
            ],
        ],

        // ── Step 3 (conditional) ─────────────────────────────────────────────
        [
            'code'      => 'airco_offerte_details',
            'type'      => 'fields',
            'condition' => [
                'service_categories' => ['airco_offerte'],
            ],
            'labels' => [
                'nl' => 'Details voor uw airco-offerte',
                'fr' => 'Détails pour votre offre climatisation',
                'en' => 'Details for your air conditioning quote',
            ],
            'fields' => [
                [
                    'name'        => 'airco_rooms_count',
                    'type'        => 'number',
                    'required'    => false,
                    'labels'      => [
                        'nl' => 'Aantal ruimtes',
                        'fr' => 'Nombre de pièces',
                        'en' => 'Number of rooms',
                    ],
                    'placeholder' => [
                        'nl' => '1',
                        'fr' => '1',
                        'en' => '1',
                    ],
                ],
                [
                    'name'        => 'airco_room_types',
                    'type'        => 'text',
                    'required'    => false,
                    'labels'      => [
                        'nl' => 'Type ruimtes',
                        'fr' => 'Type de pièces',
                        'en' => 'Room types',
                    ],
                    'placeholder' => [
                        'nl' => 'Slaapkamer, living, bureau...',
                        'fr' => 'Chambre, salon, bureau...',
                        'en' => 'Bedroom, living room, office...',
                    ],
                ],
                [
                    'name'     => 'airco_has_outdoor_unit',
                    'type'     => 'select',
                    'required' => false,
                    'labels'   => [
                        'nl' => 'Is er al een buitenunit aanwezig?',
                        'fr' => 'Y a-t-il déjà une unité extérieure ?',
                        'en' => 'Is there already an outdoor unit?',
                    ],
                    'options' => [
                        [
                            'value'  => 'yes',
                            'labels' => [
                                'nl' => 'Ja',
                                'fr' => 'Oui',
                                'en' => 'Yes',
                            ],
                        ],
                        [
                            'value'  => 'no',
                            'labels' => [
                                'nl' => 'Nee',
                                'fr' => 'Non',
                                'en' => 'No',
                            ],
                        ],
                        [
                            'value'  => 'unknown',
                            'labels' => [
                                'nl' => 'Ik weet het niet',
                                'fr' => 'Je ne sais pas',
                                'en' => 'I don\'t know',
                            ],
                        ],
                    ],
                ],
                [
                    'name'        => 'preferred_time',
                    'type'        => 'text',
                    'required'    => false,
                    'labels'      => [
                        'nl' => 'Gewenste timing',
                        'fr' => 'Timing souhaité',
                        'en' => 'Desired timing',
                    ],
                    'placeholder' => [
                        'nl' => 'Voor de zomer, zo snel mogelijk...',
                        'fr' => 'Avant l\'été, dès que possible...',
                        'en' => 'Before summer, as soon as possible...',
                    ],
                ],
            ],
            'helper_box' => [
                'render_upload' => false,
                'title'         => [
                    'nl' => 'Foto\'s toevoegen (optioneel)',
                    'fr' => 'Ajouter des photos (facultatif)',
                    'en' => 'Add photos (optional)',
                ],
                'text'          => [
                    'nl' => 'Foto\'s van de ruimtes of de geplande buitenunit-locatie helpen bij de offerte. Upload via \'Probleem of project\' hieronder.',
                    'fr' => 'Des photos des pièces ou de l\'emplacement prévu pour l\'unité extérieure facilitent l\'établissement de l\'offre. Téléchargez via \'Problème ou projet\' ci-dessous.',
                    'en' => 'Photos of the rooms or the planned outdoor unit location help with the quote. Upload via \'Issue or project\' below.',
                ],
            ],
        ],

        // ── Step 4 (conditional) ─────────────────────────────────────────────
        [
            'code'      => 'airco_onderhoud_details',
            'type'      => 'fields',
            'condition' => [
                'service_categories' => ['airco_onderhoud'],
            ],
            'labels' => [
                'nl' => 'Informatie over uw airco',
                'fr' => 'Informations sur votre climatisation',
                'en' => 'Information about your air conditioning',
            ],
            'fields' => [
                [
                    'name'        => 'airco_brand',
                    'type'        => 'text',
                    'required'    => false,
                    'labels'      => [
                        'nl' => 'Merk airco',
                        'fr' => 'Marque climatisation',
                        'en' => 'Air conditioning brand',
                    ],
                    'placeholder' => [
                        'nl' => 'Daikin, Mitsubishi, Samsung...',
                        'fr' => 'Daikin, Mitsubishi, Samsung...',
                        'en' => 'Daikin, Mitsubishi, Samsung...',
                    ],
                ],
                [
                    'name'        => 'airco_indoor_units_count',
                    'type'        => 'number',
                    'required'    => false,
                    'labels'      => [
                        'nl' => 'Aantal binnenunits',
                        'fr' => 'Nombre d\'unités intérieures',
                        'en' => 'Number of indoor units',
                    ],
                    'placeholder' => [
                        'nl' => '1',
                        'fr' => '1',
                        'en' => '1',
                    ],
                ],
                [
                    'name'        => 'airco_last_maintenance',
                    'type'        => 'text',
                    'required'    => false,
                    'labels'      => [
                        'nl' => 'Laatste onderhoud (indien gekend)',
                        'fr' => 'Dernier entretien (si connu)',
                        'en' => 'Last service (if known)',
                    ],
                    'placeholder' => [
                        'nl' => 'Bijvoorbeeld: zomer 2023',
                        'fr' => 'Par exemple : été 2023',
                        'en' => 'For example: summer 2023',
                    ],
                ],
                [
                    'name'        => 'preferred_time',
                    'type'        => 'text',
                    'required'    => false,
                    'labels'      => [
                        'nl' => 'Gewenst afspraakmoment',
                        'fr' => 'Moment de rendez-vous souhaité',
                        'en' => 'Preferred appointment time',
                    ],
                    'placeholder' => [
                        'nl' => 'Liefst voor de zomer, flexibel...',
                        'fr' => 'De préférence avant l\'été, flexible...',
                        'en' => 'Preferably before summer, flexible...',
                    ],
                ],
            ],
            // No helper_box for this step
        ],

        // ── Step 5 ───────────────────────────────────────────────────────────
        [
            'code'   => 'customer_context',
            'labels' => [
                'nl' => 'Klant en urgentie',
                'fr' => 'Client et urgence',
                'en' => 'Customer and urgency',
            ],
            'type'   => 'fields',
            'fields' => [
                [
                    'name'     => 'customer_type',
                    'type'     => 'select',
                    'required' => true,
                    'labels'   => [
                        'nl' => 'Klanttype',
                        'fr' => 'Type de client',
                        'en' => 'Customer type',
                    ],
                    'options' => [
                        [
                            'value'  => 'residential',
                            'labels' => [
                                'nl' => 'Particulier',
                                'fr' => 'Particulier',
                                'en' => 'Residential',
                            ],
                        ],
                        [
                            'value'  => 'business',
                            'labels' => [
                                'nl' => 'Bedrijf',
                                'fr' => 'Entreprise',
                                'en' => 'Business',
                            ],
                        ],
                    ],
                ],
                [
                    'name'     => 'urgency',
                    'type'     => 'select',
                    'required' => true,
                    'labels'   => [
                        'nl' => 'Urgentie',
                        'fr' => 'Urgence',
                        'en' => 'Urgency',
                    ],
                    'options' => [
                        [
                            'value'  => 'urgent',
                            'labels' => [
                                'nl' => 'Dringend',
                                'fr' => 'Urgent',
                                'en' => 'Urgent',
                            ],
                        ],
                        [
                            'value'  => 'within_days',
                            'labels' => [
                                'nl' => 'Binnen enkele dagen',
                                'fr' => 'Dans quelques jours',
                                'en' => 'Within a few days',
                            ],
                        ],
                        [
                            'value'  => 'not_urgent',
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

        // ── Step 6 ───────────────────────────────────────────────────────────
        [
            'code'   => 'description',
            'labels' => [
                'nl' => 'Probleem of project',
                'fr' => 'Problème ou projet',
                'en' => 'Issue or project',
            ],
            'type'   => 'fields',
            'fields' => [
                [
                    'name'        => 'description',
                    'type'        => 'textarea',
                    'required'    => true,
                    'labels'      => [
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
                    'nl' => 'Foto\'s toevoegen',
                    'fr' => 'Ajouter des photos',
                    'en' => 'Add photos',
                ],
                'text'  => [
                    'nl' => 'Voeg indien mogelijk foto\'s toe van het toestel, typeplaatje, foutcode of probleemzone.',
                    'fr' => 'Ajoutez si possible des photos de l\'appareil, de la plaque signalétique, du code erreur ou de la zone du problème.',
                    'en' => 'If possible, add photos of the unit, nameplate, error code or problem area.',
                ],
            ],
        ],

        // ── Step 7 ───────────────────────────────────────────────────────────
        // Always shown; brand/device_model here serve categories without a dedicated detail step (herstelling_cv, sanitair, ventilatie, waterverzachter, koeling, andere).
        [
            'code'   => 'technical_details',
            'labels' => [
                'nl' => 'Technische gegevens',
                'fr' => 'Informations techniques',
                'en' => 'Technical details',
            ],
            'type'   => 'fields',
            'fields' => [
                [
                    'name'        => 'brand',
                    'type'        => 'text',
                    'required'    => false,
                    'labels'      => [
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
                    'name'        => 'device_model',
                    'type'        => 'text',
                    'required'    => false,
                    'labels'      => [
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
                    'name'        => 'serial_number',
                    'type'        => 'text',
                    'required'    => false,
                    'labels'      => [
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
                    'name'     => 'unknown_device_details',
                    'type'     => 'checkbox',
                    'required' => false,
                    'labels'   => [
                        'nl' => 'Ik weet merk/model/serienummer niet',
                        'fr' => 'Je ne connais pas la marque/le modèle/le numéro de série',
                        'en' => 'I don\'t know the brand/model/serial number',
                    ],
                ],
            ],
        ],

        // ── Step 8 ───────────────────────────────────────────────────────────
        [
            'code'   => 'location_availability',
            'labels' => [
                'nl' => 'Locatie en beschikbaarheid',
                'fr' => 'Lieu et disponibilité',
                'en' => 'Location and availability',
            ],
            'type'   => 'fields',
            'fields' => [
                [
                    'name'        => 'street',
                    'type'        => 'text',
                    'required'    => true,
                    'labels'      => [
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
                    'name'        => 'postal_code',
                    'type'        => 'text',
                    'required'    => true,
                    'labels'      => [
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
                    'name'        => 'city',
                    'type'        => 'text',
                    'required'    => true,
                    'labels'      => [
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
                    'name'        => 'availability',
                    'type'        => 'textarea',
                    'required'    => false,
                    'labels'      => [
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

        // ── Step 9 ───────────────────────────────────────────────────────────
        [
            'code'   => 'contact_details',
            'labels' => [
                'nl' => 'Contactgegevens',
                'fr' => 'Coordonnées',
                'en' => 'Contact details',
            ],
            'type'   => 'fields',
            'fields' => [
                [
                    'name'     => 'customer_name',
                    'type'     => 'text',
                    'required' => true,
                    'labels'   => [
                        'nl' => 'Naam',
                        'fr' => 'Nom',
                        'en' => 'Name',
                    ],
                ],
                [
                    'name'     => 'customer_email',
                    'type'     => 'email',
                    'required' => true,
                    'labels'   => [
                        'nl' => 'E-mailadres',
                        'fr' => 'Adresse e-mail',
                        'en' => 'Email address',
                    ],
                ],
                [
                    'name'     => 'customer_phone',
                    'type'     => 'tel',
                    'required' => false,
                    'labels'   => [
                        'nl' => 'Telefoonnummer',
                        'fr' => 'Numéro de téléphone',
                        'en' => 'Phone number',
                    ],
                ],
            ],
        ],

        // ── Step 10 ──────────────────────────────────────────────────────────
        [
            'code'   => 'summary',
            'labels' => [
                'nl' => 'Samenvatting',
                'fr' => 'Résumé',
                'en' => 'Summary',
            ],
            'type'   => 'summary',
        ],
    ],

    // ─────────────────────────────────────────────────────────────────────────
    // SERVICE CATEGORIES
    // Controller reads this to resolve service_key + request_type from the
    // category value the user selected in step 0.
    // ─────────────────────────────────────────────────────────────────────────
    'service_categories' => [
        [
            'value'        => 'airco_offerte',
            'service_key'  => 'airco',
            'request_type' => 'installation',
            'labels'       => [
                'nl' => 'Airco laten plaatsen',
                'fr' => 'Faire installer une climatisation',
                'en' => 'Install air conditioning',
            ],
        ],
        [
            'value'        => 'airco_onderhoud',
            'service_key'  => 'airco',
            'request_type' => 'maintenance',
            'labels'       => [
                'nl' => 'Onderhoud van airco',
                'fr' => 'Entretien de votre climatisation',
                'en' => 'Air conditioning maintenance',
            ],
        ],
        [
            'value'        => 'onderhoud_cv',
            'service_key'  => 'heating',
            'request_type' => 'maintenance',
            'labels'       => [
                'nl' => 'Onderhoud van verwarming',
                'fr' => 'Entretien du chauffage',
                'en' => 'Heating maintenance',
            ],
        ],
        [
            'value'        => 'herstelling_cv',
            'service_key'  => 'heating',
            'request_type' => 'repair',
            'labels'       => [
                'nl' => 'Verwarming herstellen',
                'fr' => 'Réparer le chauffage',
                'en' => 'Fix heating system',
            ],
        ],
        [
            'value'        => 'dringend_lek',
            'service_key'  => 'plumbing',
            'request_type' => 'repair',
            'labels'       => [
                'nl' => 'Dringend probleem of lek',
                'fr' => 'Problème urgent ou fuite',
                'en' => 'Urgent issue or leak',
            ],
        ],
        [
            'value'        => 'sanitair',
            'service_key'  => 'plumbing',
            'request_type' => 'repair',
            'labels'       => [
                'nl' => 'Sanitair of loodgieterij',
                'fr' => 'Sanitaire ou plomberie',
                'en' => 'Plumbing or sanitary',
            ],
        ],
        [
            'value'        => 'ventilatie',
            'service_key'  => 'ventilation',
            'request_type' => 'new_project',
            'labels'       => [
                'nl' => 'Ventilatie',
                'fr' => 'Ventilation',
                'en' => 'Ventilation',
            ],
        ],
        [
            'value'        => 'waterverzachter',
            'service_key'  => 'water-softeners',
            'request_type' => 'installation',
            'labels'       => [
                'nl' => 'Waterverzachter',
                'fr' => 'Adoucisseur d\'eau',
                'en' => 'Water softener',
            ],
        ],
        [
            'value'        => 'koeling',
            'service_key'  => 'cold-rooms',
            'request_type' => 'installation',
            'labels'       => [
                'nl' => 'Koeling of koelcel',
                'fr' => 'Réfrigération ou chambre froide',
                'en' => 'Cooling or cold room',
            ],
        ],
        [
            'value'        => 'andere',
            'service_key'  => 'heating',
            'request_type' => 'repair',
            'labels'       => [
                'nl' => 'Ik weet het niet / andere vraag',
                'fr' => 'Je ne sais pas / autre question',
                'en' => 'I\'m not sure / other question',
            ],
        ],
    ],

    // ─────────────────────────────────────────────────────────────────────────
    // REQUEST TYPES  (used by admin filter — unchanged)
    // ─────────────────────────────────────────────────────────────────────────
    'request_types' => [
        [
            'value'  => 'repair',
            'labels' => [
                'nl' => 'Herstelling',
                'fr' => 'Réparation',
                'en' => 'Repair',
            ],
        ],
        [
            'value'  => 'maintenance',
            'labels' => [
                'nl' => 'Onderhoud',
                'fr' => 'Entretien',
                'en' => 'Maintenance',
            ],
        ],
        [
            'value'  => 'installation',
            'labels' => [
                'nl' => 'Installatie',
                'fr' => 'Installation',
                'en' => 'Installation',
            ],
        ],
        [
            'value'  => 'new_project',
            'labels' => [
                'nl' => 'Nieuw project',
                'fr' => 'Nouveau projet',
                'en' => 'New project',
            ],
        ],
    ],
];
