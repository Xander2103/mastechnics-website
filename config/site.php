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

    'contact_daily_limit' => (int) env('CONTACT_DAILY_LIMIT', 10),
    'contact_burst_limit_per_hour' => (int) env('CONTACT_BURST_LIMIT_PER_HOUR', 20),

    'locales' => [
        'nl',
        'fr',
        'en',
    ],

    'default_locale' => 'nl',

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