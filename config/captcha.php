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
    |   - production: always required → without keys every form submission
    |     is refused (fail closed) and an error is logged;
    |   - other environments: enabled only when both keys of the active
    |     provider are present, so local development and the test suite work
    |     without an external account.
    | CAPTCHA_ENABLED=true|false overrides that; TURNSTILE_ENABLED is kept
    | as an alias for the same switch.
    |
    */

    'provider' => env('CAPTCHA_PROVIDER', 'turnstile'),
    'enabled' => env('CAPTCHA_ENABLED', env('TURNSTILE_ENABLED')),
    'timeout_seconds' => 5,

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
