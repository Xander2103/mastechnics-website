<?php

namespace App\Services\Spam\Trust\Signals;

use App\Services\Spam\CaptchaVerdict;
use App\Services\Spam\Trust\SignalProvider;
use App\Services\Spam\Trust\TrustContext;
use App\Services\Spam\Trust\TrustSignal;

/**
 * The captcha verdict as trust signals. A passed token with matching
 * hostname/action is exactly ONE positive signal — never enough for
 * "trusted" on its own (see TrustEvaluator thresholds).
 */
final class CaptchaSignals implements SignalProvider
{
    /** @param array<string, mixed> $config */
    public function __construct(private readonly array $config)
    {
    }

    public function collect(TrustContext $ctx): array
    {
        $verdict = $ctx->captcha;
        $group = TrustSignal::GROUP_CAPTCHA;

        switch ($verdict->status) {
            case CaptchaVerdict::DISABLED:
                return [];
            case CaptchaVerdict::HOSTNAME_MISMATCH:
                return [TrustSignal::block('captcha_hostname', $group)];
            case CaptchaVerdict::ACTION_MISMATCH:
                return [TrustSignal::block('captcha_action', $group)];
            case CaptchaVerdict::PASSED:
                if ($verdict->hostnameOk === false) {
                    return [TrustSignal::block('captcha_hostname', $group)];
                }

                if ($verdict->actionOk === false) {
                    return [TrustSignal::block('captcha_action', $group)];
                }

                $signals = [TrustSignal::positive('captcha_ok', $group)];
                $maxAge = (int) ($this->config['captcha_max_age_seconds'] ?? 300);

                if ($verdict->challengeAgeSeconds !== null && $verdict->challengeAgeSeconds > $maxAge) {
                    $signals[] = TrustSignal::risk('captcha_token_old', $group, 2);
                }

                return $signals;
            default:
                return [TrustSignal::block('captcha_failed', $group)];
        }
    }
}
