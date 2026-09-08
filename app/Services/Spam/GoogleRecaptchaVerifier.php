<?php

namespace App\Services\Spam;

/** Google reCAPTCHA v2 ("I'm not a robot" checkbox). Fallback provider. */
class GoogleRecaptchaVerifier extends SiteVerifyCaptchaVerifier
{
    public function provider(): string
    {
        return 'recaptcha';
    }
}
