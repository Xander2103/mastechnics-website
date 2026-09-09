<?php

namespace App\Services\Spam\Trust\Signals;

use App\Services\Spam\FormProtectionLog;
use App\Services\Spam\RecipientSafety;
use App\Services\Spam\Trust\SignalProvider;
use App\Services\Spam\Trust\TrustContext;
use App\Services\Spam\Trust\TrustSignal;
use Illuminate\Support\Facades\Cache;

/**
 * Local reputation of the client address: flagged in the last day
 * (rejected or sent to review), or used with several different e-mail
 * addresses within the hour. No external reputation service is called.
 */
final class IpSignals implements SignalProvider
{
    public const FLAGGED_KEY_PREFIX = 'form-protection:ip-flagged:';

    public const EMAILS_KEY_PREFIX = 'form-protection:ip-emails:';

    /** @param array<string, mixed> $config */
    public function __construct(private readonly array $config)
    {
    }

    public function collect(TrustContext $ctx): array
    {
        $group = TrustSignal::GROUP_IP;
        $ip = (string) $ctx->request->ip();
        $ipHash = FormProtectionLog::hashIp($ip);
        $signals = [];

        try {
            if (Cache::has(self::FLAGGED_KEY_PREFIX . $ipHash)) {
                $signals[] = TrustSignal::risk('ip_recently_flagged', $group, 2);
            }

            $emails = Cache::get(self::EMAILS_KEY_PREFIX . $ipHash, []);
            $current = RecipientSafety::hash($ctx->facts->email);
            $others = array_filter(is_array($emails) ? $emails : [], fn ($hash) => $hash !== $current);

            if (count($others) >= 3) {
                $signals[] = TrustSignal::risk('ip_many_emails', $group, 3);
            }
        } catch (\Throwable $e) {
            // Cache unavailable: no IP reputation, no penalty.
        }

        $env = strtolower((string) config('app.env', 'production'));

        if ($ip !== '' && ! in_array($env, ['local', 'testing', 'development', 'dev'], true)
            && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            $signals[] = TrustSignal::risk('ip_private_range', $group, 1);
        }

        return $signals;
    }
}
