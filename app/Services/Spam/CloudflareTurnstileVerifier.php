<?php

namespace App\Services\Spam;

class CloudflareTurnstileVerifier extends SiteVerifyCaptchaVerifier
{
    public function provider(): string
    {
        return 'turnstile';
    }
}
