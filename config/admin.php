<?php

return [
    // Optional extra recipients CC'd on every new customer request,
    // in addition to config('site.request_notification_email').
    // Comma-separate multiple addresses in ADMIN_NOTIFICATION_EMAIL if needed.
    'notification_emails' => array_filter(
        array_map('trim', explode(',', (string) env('ADMIN_NOTIFICATION_EMAIL', '')))
    ),
];
