<?php

return [
    'name' => 'Mastechnics',
    'legal_name' => 'Mastechnics',
    'vat_number' => 'BE 0760.768.228',

    // Short, reusable one-liner. Used as the meta-description fallback when a
    // page translation has none, and as the schema.org "description".
    'tagline' => [
        'nl' => 'Erkende technische service voor verwarming, airco, sanitair, ventilatie, waterverzachters en koelcellen in de Druivenstreek en Vlaams-Brabant.',
        'fr' => 'Service technique agréé pour le chauffage, la climatisation, la plomberie, la ventilation, les adoucisseurs d\'eau et les chambres froides dans le Druivenstreek et le Brabant flamand.',
        'en' => 'Certified technical service for heating, air conditioning, plumbing, ventilation, water softeners and cold rooms in the Druivenstreek region and Flemish Brabant.',
    ],

    'contact' => [
        'phone_display' => '+32 495 12 11 78',
        'phone_link' => '+32495121178',
        'email' => 'martin@mastechnics.be',
        'whatsapp_display' => '+32 495 12 11 78',
        'whatsapp_link' => '+32495121178',
        'messenger' => 'mastechnics',
        // TODO(Martin): provide real company number/VAT and legal address before launch.
        'company_number' => env('COMPANY_NUMBER'),
        'address' => env('COMPANY_ADDRESS'),
    ],

    'request_notification_email' => env('REQUEST_NOTIFICATION_EMAIL', 'martin@mastechnics.be'),
    'contact_notification_email' => env('CONTACT_NOTIFICATION_EMAIL', 'martin@mastechnics.be'),

    'request_daily_limit' => (int) env('REQUEST_DAILY_LIMIT', 5),
    'request_burst_limit_per_hour' => (int) env('REQUEST_BURST_LIMIT_PER_HOUR', 10),

    'contact_daily_limit' => (int) env('CONTACT_DAILY_LIMIT', 10),
    'contact_burst_limit_per_hour' => (int) env('CONTACT_BURST_LIMIT_PER_HOUR', 20),

    'locales' => [
        'nl',
        'fr',
        'en',
    ],

    'default_locale' => 'nl',

    // Slugs of the fixed (non-service) pages, per locale. Single source of
    // truth for navigation, breadcrumbs and internal links — the same maps
    // used to live inline in half a dozen Blade templates.
    'page_slugs' => [
        'services' => [
            'nl' => 'diensten',
            'fr' => 'services',
            'en' => 'services',
        ],
        'service_area' => [
            'nl' => 'werkgebied',
            'fr' => 'zone-intervention',
            'en' => 'service-area',
        ],
        'request' => [
            'nl' => 'aanvraag',
            'fr' => 'demande',
            'en' => 'request',
        ],
        'contact' => [
            'nl' => 'contact',
            'fr' => 'contact',
            'en' => 'contact',
        ],
        'privacy' => [
            'nl' => 'privacybeleid',
            'fr' => 'politique-confidentialite',
            'en' => 'privacy-policy',
        ],
    ],

    'page_labels' => [
        'home' => [
            'nl' => 'Home',
            'fr' => 'Accueil',
            'en' => 'Home',
        ],
        'services' => [
            'nl' => 'Diensten',
            'fr' => 'Services',
            'en' => 'Services',
        ],
        'service_area' => [
            'nl' => 'Werkgebied',
            'fr' => 'Zone d\'intervention',
            'en' => 'Service area',
        ],
        'request' => [
            'nl' => 'Aanvraag',
            'fr' => 'Demande',
            'en' => 'Request',
        ],
        'contact' => [
            'nl' => 'Contact',
            'fr' => 'Contact',
            'en' => 'Contact',
        ],
        'privacy' => [
            'nl' => 'Privacybeleid',
            'fr' => 'Politique de confidentialité',
            'en' => 'Privacy Policy',
        ],
    ],

    // ── Local SEO ───────────────────────────────────────────────────────────
    // Nothing in this block is invented. Postal address parts stay env-driven
    // and are only emitted in schema.org markup once they are actually
    // configured — publishing a wrong NAP is worse than publishing none.
    'address' => [
        'street'      => env('COMPANY_ADDRESS'),
        'locality'    => env('COMPANY_LOCALITY'),
        'postal_code' => env('COMPANY_POSTAL_CODE'),
        'region'      => env('COMPANY_REGION', 'Vlaams-Brabant'),
        'country'     => 'BE',
    ],

    // Opening hours show up verbatim in Google's local panel, so they are only
    // published when explicitly configured. Format: "Mo-Fr 08:00-18:00".
    'opening_hours' => array_values(array_filter(
        explode('|', (string) env('COMPANY_OPENING_HOURS', ''))
    )),

    // Geographic centre of the served region (Druivenstreek), NOT the company
    // address. Used for the areaServed GeoCircle so a service-area business
    // without a public storefront still carries a location signal.
    'service_area_center' => [
        'latitude'  => 50.8100,
        'longitude' => 4.5300,
        'radius_m'  => 25000,
    ],

    // Municipalities actively served. Drives areaServed structured data, the
    // service-area hub page and the footer region links. Keys map 1:1 onto
    // location page slugs; `page` marks the ones with a dedicated landing page.
    'service_areas' => [
        ['name' => 'Tervuren',         'postal_code' => '3080', 'page' => true],
        ['name' => 'Overijse',         'postal_code' => '3090', 'page' => true],
        ['name' => 'Hoeilaart',        'postal_code' => '1560', 'page' => true],
        ['name' => 'Huldenberg',       'postal_code' => '3040', 'page' => true],
        ['name' => 'Bertem',           'postal_code' => '3060', 'page' => true],
        ['name' => 'Wezembeek-Oppem',  'postal_code' => '1970', 'page' => true],
        ['name' => 'Kraainem',         'postal_code' => '1950', 'page' => false],
        ['name' => 'Zaventem',         'postal_code' => '1930', 'page' => false],
        ['name' => 'Kortenberg',       'postal_code' => '3070', 'page' => false],
        ['name' => 'Oud-Heverlee',     'postal_code' => '3050', 'page' => false],
        ['name' => 'Leuven',           'postal_code' => '3000', 'page' => false],
    ],

    // Which service_category / urgency_level values count as "urgent" —
    // single source of truth, reused by both stat queries and the
    // reminder/badge service so the definition never drifts out of sync.
    'urgent_categories' => ['dringend_lek'],
    'urgent_levels' => ['water_leaking', 'small_leak', 'no_heating', 'no_hot_water', 'urgent'],

    // Follow-up reminder thresholds (admin dashboard only — no automatic
    // customer emails are sent based on these yet).
    'reminders' => [
        'new_not_viewed_hours'        => (int) env('REMINDER_NEW_NOT_VIEWED_HOURS', 24),
        'contact_not_contacted_hours' => (int) env('REMINDER_CONTACT_NOT_CONTACTED_HOURS', 48),
        'quote_awaiting_reply_days'   => (int) env('REMINDER_QUOTE_AWAITING_REPLY_DAYS', 7),
        'lost_inactive_days'          => (int) env('REMINDER_LOST_INACTIVE_DAYS', 30),
    ],
];