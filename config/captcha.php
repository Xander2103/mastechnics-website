<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Bot challenge on the public forms (contact form + request wizard)
    |--------------------------------------------------------------------------
    |
    | One provider is active at a time; controllers and views only talk to
    | the CaptchaVerifier interface, so switching is a config change:
    |
    |   CAPTCHA_PROVIDER=turnstile   (default, recommended)
    |   CAPTCHA_PROVIDER=recaptcha   (Google reCAPTCHA v2 checkbox, fallback)
    |
    | 'enabled' = null means automatic:
    |   - local / testing / development: enabled only when both keys of the
    |     active provider are present, so the test suite works without an
    |     external account;
    |   - every other APP_ENV (production, or a typo of it): always required
    |     → without keys every form submission is refused (fail closed) and
    |     an error is logged.
    | CAPTCHA_ENABLED=true|false overrides that. An unrecognisable value is
    | treated as "not set" and logged — never as "off".
    |
    */

    'provider' => env('CAPTCHA_PROVIDER', 'turnstile'),
    'enabled' => env('CAPTCHA_ENABLED'),
    'timeout_seconds' => 5,

    /*
    | A token is only accepted when the provider reports it was issued for
    | one of our hostnames (host of APP_URL, with and without "www.", plus
    | this comma-separated list) and — Turnstile only — for the widget
    | action of the form it is posted to ("contact" / "request"). A solved
    | token from another site or form is refused. The two switches exist
    | for emergencies only; leave them on.
    */
    'expected_hostnames' => env('CAPTCHA_EXPECTED_HOSTNAMES'),
    'verify_hostname' => filter_var(env('CAPTCHA_VERIFY_HOSTNAME', true), FILTER_VALIDATE_BOOL),
    'verify_action' => filter_var(env('CAPTCHA_VERIFY_ACTION', true), FILTER_VALIDATE_BOOL),
    // Age of the solved challenge (challenge_ts) above which the trust
    // evaluator adds risk; the provider itself expires tokens after ~5 min.
    'max_token_age_seconds' => 300,

    'providers' => [
        'turnstile' => [
            'site_key' => env('TURNSTILE_SITE_KEY'),
            'secret_key' => env('TURNSTILE_SECRET_KEY'),
            'verify_url' => 'https://challenges.cloudflare.com/turnstile/v0/siteverify',
            'script_url' => 'https://challenges.cloudflare.com/turnstile/v0/api.js',
            'response_field' => 'cf-turnstile-response',
        ],
        'recaptcha' => [
            'site_key' => env('RECAPTCHA_SITE_KEY'),
            'secret_key' => env('RECAPTCHA_SECRET_KEY'),
            'verify_url' => 'https://www.google.com/recaptcha/api/siteverify',
            'script_url' => 'https://www.google.com/recaptcha/api.js',
            'response_field' => 'g-recaptcha-response',
        ],
    ],
];
