<?php

return [
    'name' => 'Mastechnics',

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

    'request_daily_limit' => (int) env('REQUEST_DAILY_LIMIT', 5),
    'request_burst_limit_per_hour' => (int) env('REQUEST_BURST_LIMIT_PER_HOUR', 10),

    'locales' => [
        'nl',
        'fr',
        'en',
    ],

    'default_locale' => 'nl',
];