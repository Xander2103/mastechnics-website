<?php

/*
|--------------------------------------------------------------------------
| Anti-abuse layers of the public forms (see App\Services\Spam)
|--------------------------------------------------------------------------
|
| Every accepted submission costs two transactional e-mails on a 300/day
| Brevo quota. The limits below are deliberately generous for humans (a
| handful of submissions a day is normal traffic) and tight for bots.
| All counters live in the cache store (CACHE_STORE), so they are shared
| by every PHP worker.
|
*/

return [
    // Visually hidden fields humans never fill. Any non-empty value = bot.
    // The first is a text field, every further one is rendered as an
    // unchecked checkbox: browser autofill never ticks checkboxes, so a
    // human with a saved address profile can never trip it by accident
    // (an off-screen "address line 2" text field could be autofilled).
    'honeypot_fields' => ['website_url', 'newsletter_optin'],

    // Signed "form opened at" timestamp: a submit faster than min_seconds
    // after the page was rendered is refused, as is a token older than
    // max_hours (refresh gives a fresh one).
    'timing' => [
        'field' => 'form_opened_at',
        'min_seconds' => (int) env('FORM_MIN_FILL_SECONDS', 3),
        'max_hours' => (int) env('FORM_MAX_FILL_HOURS', 12),
    ],

    'forms' => [
        'contact' => [
            'enabled' => filter_var(env('CONTACT_FORM_ENABLED', true), FILTER_VALIDATE_BOOL),
            // POSTs per client before validation even runs (bounds siteverify calls).
            'attempt_limit_per_10_minutes' => (int) env('CONTACT_ATTEMPT_LIMIT_PER_10_MINUTES', 30),
            // Accepted submissions per client.
            'daily_limit' => (int) env('CONTACT_DAILY_LIMIT', 10),
            'burst_limit_per_hour' => (int) env('CONTACT_BURST_LIMIT_PER_HOUR', 20),
            // Accepted submissions per (normalized) e-mail address per day.
            'email_daily_limit' => (int) env('CONTACT_EMAIL_DAILY_LIMIT', 3),
            // Accepted submissions site-wide per day for this form.
            'global_daily_limit' => (int) env('CONTACT_GLOBAL_DAILY_LIMIT', 60),
        ],
        'request' => [
            'enabled' => filter_var(env('REQUEST_WIZARD_ENABLED', true), FILTER_VALIDATE_BOOL),
            'attempt_limit_per_10_minutes' => (int) env('REQUEST_ATTEMPT_LIMIT_PER_10_MINUTES', 30),
            'daily_limit' => (int) env('REQUEST_DAILY_LIMIT', 5),
            'burst_limit_per_hour' => (int) env('REQUEST_BURST_LIMIT_PER_HOUR', 10),
            'email_daily_limit' => (int) env('REQUEST_EMAIL_DAILY_LIMIT', 3),
            'global_daily_limit' => (int) env('REQUEST_GLOBAL_DAILY_LIMIT', 60),
        ],
    ],

    // Site-wide POST attempts (both forms, any client) per 10 minutes: a
    // botnet flood gets a 429 before it can reach validation or Cloudflare.
    'global_attempt_limit_per_10_minutes' => (int) env('GLOBAL_ATTEMPT_LIMIT_PER_10_MINUTES', 120),

    // Site-wide accepted submissions (both forms) per 10 minutes.
    'accepted_burst_limit_per_10_minutes' => (int) env('FORM_ACCEPTED_BURST_LIMIT_PER_10_MINUTES', 20),

    // Privacy-aware repeat detection (hashed, nothing stored in clear).
    'fingerprint' => [
        // Identical submission (ip + e-mail + phone + message + user agent).
        'exact_limit' => 1,
        'exact_window_seconds' => 600,
        // Same message body, whoever sends it (spam campaigns rotate addresses).
        'content_limit' => 3,
        'content_window_seconds' => 3600,
    ],

    // Retention of the form security log (form_security_events), pruned by
    // `php artisan forms:prune-security-log` (scheduled daily).
    'security_log' => [
        'retention_days' => (int) env('FORM_SECURITY_LOG_RETENTION_DAYS', 90),
    ],

    // Transactional mail budget (circuit breaker for the Brevo quota) and
    // emergency switches. A blocked mail never blocks the submission: the
    // request is stored, the skip is logged in mail_logs, nothing retries.
    'mail' => [
        'guard_enabled' => filter_var(env('MAIL_GUARD_ENABLED', true), FILTER_VALIDATE_BOOL),
        // Customer confirmations are OFF by default since sprint 21: every
        // confirmation is an external (Brevo) call a bot can provoke. Turn on
        // deliberately with CUSTOMER_CONFIRMATION_MAIL_ENABLED=true.
        'customer_confirmation_enabled' => filter_var(env('CUSTOMER_CONFIRMATION_MAIL_ENABLED', false), FILTER_VALIDATE_BOOL),
        // Joint circuit breaker: ALL form-related external mails (admin +
        // customer, both forms) share these two counters. Once full: zero
        // further provider calls from the public forms until the window ends.
        'external_daily_limit' => (int) env('FORM_EXTERNAL_MAIL_DAILY_LIMIT', 20),
        'external_limit_per_10_minutes' => (int) env('FORM_EXTERNAL_MAIL_LIMIT_PER_10_MINUTES', 4),
        'customer_confirmation_daily_limit' => (int) env('CUSTOMER_CONFIRMATION_DAILY_LIMIT', 100),
        'admin_notification_daily_limit' => (int) env('ADMIN_NOTIFICATION_DAILY_LIMIT', 150),
        // All form-triggered mails together, per hour.
        'burst_limit_per_hour' => (int) env('FORM_MAIL_BURST_LIMIT', 30),
    ],

    // Pre-mail trust gate (App\Services\Spam\Trust\TrustEvaluator). Every
    // submission that passes the hard checks is scored on independent
    // signals; only "trusted" ones may trigger transactional mail, the rest
    // is stored as needs_review for a human decision in the admin.
    'trust' => [
        // Trusted needs BOTH a low risk sum AND enough independent positive
        // signals (captcha ok, normal fill time, consistent browser, ...):
        // a passed captcha alone is one positive signal, never enough.
        'trusted_max_risk' => (int) env('FORM_TRUST_MAX_RISK', 2),
        'trusted_min_positives' => (int) env('FORM_TRUST_MIN_POSITIVES', 3),
        // Risk sum at which a submission is silently dropped instead of stored.
        'block_score' => (int) env('FORM_TRUST_BLOCK_SCORE', 12),
        // Turnstile challenge_ts older than this counts against the token.
        'captcha_max_age_seconds' => 300,
        // Fill time below this (but above the hard minimum) is "fast".
        'fast_seconds' => [
            'contact' => 15,
            'request' => 40,
        ],
        // Site-wide accepted submissions: elevated → risk, attack → nobody
        // is trusted automatically until the window cools down.
        'velocity' => [
            'review_per_10_minutes' => (int) env('FORM_VELOCITY_REVIEW_PER_10_MINUTES', 5),
            'attack_per_10_minutes' => (int) env('FORM_VELOCITY_ATTACK_PER_10_MINUTES', 10),
            'attack_per_hour' => (int) env('FORM_VELOCITY_ATTACK_PER_HOUR', 25),
        ],
        // Near-duplicate messages (SimHash Hamming distance) in a rolling window.
        'similarity' => [
            'max_hamming' => 12,
            'window_seconds' => 3600,
            'max_entries' => 200,
        ],
        'email' => [
            // MX/A lookup of the sender domain, cached per domain for a day.
            'dns_check' => filter_var(env('FORM_EMAIL_DNS_CHECK', true), FILTER_VALIDATE_BOOL),
            'dns_check_in_tests' => false,
            'disposable_domains' => [
                'mailinator.com', 'guerrillamail.com', '10minutemail.com', 'tempmail.com',
                'temp-mail.org', 'yopmail.com', 'sharklasers.com', 'trashmail.com',
                'getnada.com', 'dispostable.com', 'maildrop.cc', 'mohmal.com',
                'throwawaymail.com', 'fakeinbox.com', 'mailnesia.com', 'tempr.email',
                'discard.email', 'spamgourmet.com', 'mintemail.com', 'emailondeck.com',
                'moakt.com', 'tmpmail.net', 'burnermail.io', 'mailsac.com', 'inboxkitten.com',
            ],
        ],
    ],
];
