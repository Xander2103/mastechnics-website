<?php

$platforms = [
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
];

return [
    'enabled' => true,

    'platforms' => $platforms,

    // Manually verified, genuine public reviews only — copied by hand from
    // the platform listings below. Google and Trustpilot both truncate long
    // reviews with an ellipsis in their public UI; where that happened the
    // visible excerpt is kept exactly as shown (see 'truncated'), the
    // missing tail is never reconstructed. Google only exposes an
    // approximate relative date ("een jaar geleden") rather than an exact
    // one, so that label is stored and translated instead of inventing an
    // ISO date. Trustpilot exposes both a published date and a separate
    // "experience date" — both are kept, only the published date is shown.
    'reviews' => [
        // ── Google ──────────────────────────────────────────────────────
        [
            'author'          => 'jeremy burton',
            'rating'          => 5,
            'source'          => 'google',
            'source_url'      => $platforms['google']['url'],
            'original_locale' => 'nl',
            'original_title'  => null,
            'original_text'   => 'Na het vergelijken van meerdere offertes hebben we uiteindelijk gekozen voor MAS Technics. De uitleg was duidelijk, eerlijk en professioneel – en bovendien was …',
            'truncated'       => true,
            'date_label'      => [
                'nl' => 'een jaar geleden',
                'fr' => 'il y a un an',
                'en' => 'a year ago',
            ],
            'translations' => [
                'nl' => 'Na het vergelijken van meerdere offertes hebben we uiteindelijk gekozen voor MAS Technics. De uitleg was duidelijk, eerlijk en professioneel – en bovendien was …',
                'fr' => 'Après avoir comparé plusieurs devis, nous avons finalement choisi MAS Technics. Les explications étaient claires, honnêtes et professionnelles – et en plus, c’était…',
                'en' => 'After comparing several quotes, we ultimately chose MAS Technics. The explanation was clear, honest and professional – and on top of that, it was…',
            ],
        ],
        [
            'author'          => 'Sandesh San',
            'rating'          => 5,
            'source'          => 'google',
            'source_url'      => $platforms['google']['url'],
            'original_locale' => 'nl',
            'original_title'  => 'Allround vakman, super tevreden',
            'original_text'   => 'Zowel de installatie van de airco als het onderhoud aan mijn ketel uitstekend …',
            'truncated'       => true,
            'date_label'      => [
                'nl' => 'een jaar geleden',
                'fr' => 'il y a un an',
                'en' => 'a year ago',
            ],
            'translated_titles' => [
                'nl' => 'Allround vakman, super tevreden',
                'fr' => 'Professionnel polyvalent, très satisfait',
                'en' => 'All-round professional, very satisfied',
            ],
            'translations' => [
                'nl' => 'Zowel de installatie van de airco als het onderhoud aan mijn ketel uitstekend …',
                'fr' => 'Tant l’installation de la climatisation que l’entretien de ma chaudière étaient excellents…',
                'en' => 'Both the air-conditioning installation and the maintenance of my boiler were excellent…',
            ],
        ],
        [
            'author'          => 'Sue-Liza Eta',
            'rating'          => 5,
            'source'          => 'google',
            'source_url'      => $platforms['google']['url'],
            'original_locale' => 'en',
            'original_title'  => null,
            'original_text'   => 'If you are looking for a plumber with good work ethics and integrity. He is the one. …',
            'truncated'       => true,
            'date_label'      => [
                'nl' => 'een jaar geleden',
                'fr' => 'il y a un an',
                'en' => 'a year ago',
            ],
            'translations' => [
                'nl' => 'Als u een loodgieter zoekt met een goede werkethiek en integriteit, dan is hij de juiste persoon…',
                'fr' => 'Si vous recherchez un plombier avec une bonne éthique de travail et de l’intégrité, c’est lui qu’il vous faut…',
                'en' => 'If you are looking for a plumber with good work ethics and integrity. He is the one. …',
            ],
        ],
        [
            'author'          => 'Viktorija Riskute',
            'rating'          => 1,
            'source'          => 'google',
            'source_url'      => $platforms['google']['url'],
            'original_locale' => 'en',
            'original_title'  => null,
            // Misspelling ("dissappeared") is in the genuine public review — preserved as-is.
            'original_text'   => 'Not very reliable... Started work 5 weeks ago and dissappeared.',
            'truncated'       => false,
            'date_label'      => [
                'nl' => '8 maanden geleden',
                'fr' => 'il y a 8 mois',
                'en' => '8 months ago',
            ],
            'translations' => [
                'nl' => 'Niet erg betrouwbaar... Begon 5 weken geleden aan het werk en is daarna verdwenen.',
                'fr' => 'Pas très fiable… Il a commencé les travaux il y a 5 semaines, puis a disparu.',
                'en' => 'Not very reliable... Started work 5 weeks ago and dissappeared.',
            ],
        ],
        [
            'author'          => 'Jeroen Everaerts',
            'rating'          => 5,
            'source'          => 'google',
            'source_url'      => $platforms['google']['url'],
            'original_locale' => 'nl',
            'original_title'  => null,
            'original_text'   => "Top kerel, vriendelijk én gedienstig. Steeds in de weer voor zijn klant!\nEen tevreden klant",
            'truncated'       => false,
            'date_label'      => [
                'nl' => 'een jaar geleden',
                'fr' => 'il y a un an',
                'en' => 'a year ago',
            ],
            'translations' => [
                'nl' => "Top kerel, vriendelijk én gedienstig. Steeds in de weer voor zijn klant!\nEen tevreden klant",
                'fr' => "Un gars au top, aimable et serviable. Toujours prêt à se démener pour son client !\nUn client satisfait",
                'en' => "Great guy, friendly and helpful. Always going the extra mile for his customer!\nA satisfied customer",
            ],
        ],
        [
            'author'          => 'Tout Clean Services',
            'rating'          => 5,
            'source'          => 'google',
            'source_url'      => $platforms['google']['url'],
            'original_locale' => 'fr',
            'original_title'  => null,
            'original_text'   => 'Nous avons fait appel à leurs services pour l’installation de notre système de climatisation. De vrais professionnels et bosseurs ! Ils ont même réalisé plus …',
            'truncated'       => true,
            'date_label'      => [
                'nl' => 'een jaar geleden',
                'fr' => 'il y a un an',
                'en' => 'a year ago',
            ],
            'translations' => [
                'nl' => 'We hebben een beroep gedaan op hun diensten voor de installatie van ons aircosysteem. Echte professionals en harde werkers! Ze hebben zelfs meer gerealiseerd…',
                'fr' => 'Nous avons fait appel à leurs services pour l’installation de notre système de climatisation. De vrais professionnels et bosseurs ! Ils ont même réalisé plus …',
                'en' => 'We called on their services for the installation of our air-conditioning system. True professionals and hard workers! They even completed more…',
            ],
        ],

        // ── Trustpilot ──────────────────────────────────────────────────
        [
            'author'          => 'colm o\'brien',
            'rating'          => 5,
            'source'          => 'trustpilot',
            'source_url'      => $platforms['trustpilot']['url'],
            'original_locale' => 'en',
            'original_title'  => 'Always a pleasure dealing with Martin',
            'original_text'   => 'Always a pleasure dealing with Martin',
            'truncated'       => false,
            'published_date'  => '2025-04-28',
            'experience_date' => '2025-04-28',
            'translated_titles' => [
                'nl' => 'Altijd prettig om met Martin zaken te doen',
                'fr' => 'C’est toujours un plaisir de travailler avec Martin',
                'en' => 'Always a pleasure dealing with Martin',
            ],
            'translations' => [
                'nl' => 'Altijd prettig om met Martin zaken te doen.',
                'fr' => 'C’est toujours un plaisir de travailler avec Martin.',
                'en' => 'Always a pleasure dealing with Martin.',
            ],
        ],
        [
            'author'          => 'Rolland Jacques',
            'rating'          => 5,
            'source'          => 'trustpilot',
            'source_url'      => $platforms['trustpilot']['url'],
            'original_locale' => 'fr',
            'original_title'  => 'encore une intervention parfaite',
            'original_text'   => "encore une intervention parfaite, rapide et efficace.\nPrix raisonnable. Je recommande.",
            'truncated'       => false,
            'published_date'  => '2025-04-10',
            'experience_date' => '2025-04-10',
            'translated_titles' => [
                'nl' => 'Opnieuw een perfecte interventie',
                'fr' => 'encore une intervention parfaite',
                'en' => 'Another perfect intervention',
            ],
            'translations' => [
                'nl' => "Opnieuw een perfecte interventie, snel en efficiënt.\nRedelijke prijs. Ik beveel hem aan.",
                'fr' => "encore une intervention parfaite, rapide et efficace.\nPrix raisonnable. Je recommande.",
                'en' => "Another perfect intervention, fast and efficient.\nReasonable price. I recommend him.",
            ],
        ],
        [
            'author'          => 'Olga Andriese',
            'rating'          => 5,
            'source'          => 'trustpilot',
            'source_url'      => $platforms['trustpilot']['url'],
            'original_locale' => 'en',
            'original_title'  => 'Excellent service',
            'original_text'   => 'Excellent service! Good communication! Strongly recommended!',
            'truncated'       => false,
            'published_date'  => '2023-02-20',
            'experience_date' => '2023-02-19',
            'translated_titles' => [
                'nl' => 'Uitstekende service',
                'fr' => 'Excellent service',
                'en' => 'Excellent service',
            ],
            'translations' => [
                'nl' => 'Uitstekende service! Goede communicatie! Sterk aanbevolen!',
                'fr' => 'Excellent service ! Bonne communication ! Fortement recommandé !',
                'en' => 'Excellent service! Good communication! Strongly recommended!',
            ],
        ],
        [
            'author'          => 'Lucia',
            'rating'          => 5,
            'source'          => 'trustpilot',
            'source_url'      => $platforms['trustpilot']['url'],
            'original_locale' => 'nl',
            'original_title'  => 'Heel tevreden',
            // Genuine public review mixes Dutch and French — preserved exactly as published.
            'original_text'   => 'Heel tevreden. Chauffage in panne op 22 december. Nog gedepanneerd voor Kerstdag. Onze familiefeest gered. Dank u wel!',
            'truncated'       => false,
            'published_date'  => '2022-12-26',
            'experience_date' => '2022-12-22',
            'translated_titles' => [
                'nl' => 'Heel tevreden',
                'fr' => 'Très satisfaite',
                'en' => 'Very satisfied',
            ],
            'translations' => [
                'nl' => 'Heel tevreden. De verwarming viel uit op 22 december. Ze werd nog vóór Kerstdag hersteld. Ons familiefeest was gered. Dank u wel!',
                'fr' => 'Très satisfaite. Le chauffage est tombé en panne le 22 décembre. Le dépannage a encore été effectué avant Noël. Notre fête de famille a été sauvée. Merci beaucoup !',
                'en' => 'Very satisfied. The heating broke down on 22 December. It was repaired before Christmas Day. Our family celebration was saved. Thank you very much!',
            ],
        ],
        [
            'author'          => 'David M',
            'rating'          => 5,
            'source'          => 'trustpilot',
            'source_url'      => $platforms['trustpilot']['url'],
            'original_locale' => 'en',
            'original_title'  => 'Excellent service !',
            'original_text'   => 'Had a couple of visits. Excellent service and job well done. Can only warmly recommend everyone to use this company.',
            'truncated'       => false,
            'published_date'  => '2022-11-29',
            'experience_date' => '2022-11-11',
            'translated_titles' => [
                'nl' => 'Uitstekende service!',
                'fr' => 'Excellent service !',
                'en' => 'Excellent service!',
            ],
            'translations' => [
                'nl' => 'Een paar keer langs geweest. Uitstekende service en goed uitgevoerd werk. Ik kan iedereen alleen maar van harte aanraden om dit bedrijf te gebruiken.',
                'fr' => 'J’ai reçu leur visite à plusieurs reprises. Excellent service et travail très bien réalisé. Je ne peux que recommander chaleureusement cette entreprise à tout le monde.',
                'en' => 'Had a couple of visits. Excellent service and job well done. Can only warmly recommend everyone to use this company.',
            ],
        ],
    ],
];
