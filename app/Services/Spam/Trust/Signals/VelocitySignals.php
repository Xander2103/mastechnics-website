<?php

namespace App\Services\Spam\Trust\Signals;

use App\Services\Spam\FormProtectionLog;
use App\Services\Spam\Trust\SignalProvider;
use App\Services\Spam\Trust\TrustContext;
use App\Services\Spam\Trust\TrustSignal;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Site-wide submission rate. A handful of accepted submissions in ten
 * minutes is normal traffic; well above that is a campaign, and during
 * one nobody gets mailed automatically (attack mode). Also: the same
 * browser signature arriving from many different IPs in minutes is a
 * rotating-proxy bot, whatever each individual request looks like.
 */
final class VelocitySignals implements SignalProvider
{
    public const BURST_KEY = 'form-protection:accepted:global:burst';

    public const HOUR_KEY = 'form-protection:accepted:global:hour';

    public const UA_IPS_KEY_PREFIX = 'form-protection:ua-ips:';

    public const ATTACK_CODE = 'velocity_attack';

    /** @param array<string, mixed> $config */
    public function __construct(private readonly array $config)
    {
    }

    public function collect(TrustContext $ctx): array
    {
        $group = TrustSignal::GROUP_VELOCITY;
        $velocity = (array) ($this->config['velocity'] ?? []);
        $signals = [];

        try {
            $tenMinutes = RateLimiter::attempts(self::BURST_KEY);
            $hour = RateLimiter::attempts(self::HOUR_KEY);
        } catch (\Throwable $e) {
            $tenMinutes = 0;
            $hour = 0;
        }

        if ($tenMinutes >= (int) ($velocity['attack_per_10_minutes'] ?? 10) || $hour >= (int) ($velocity['attack_per_hour'] ?? 25)) {
            $signals[] = TrustSignal::risk(self::ATTACK_CODE, $group, 4);
        } elseif ($tenMinutes >= (int) ($velocity['review_per_10_minutes'] ?? 5)) {
            $signals[] = TrustSignal::risk('velocity_elevated', $group, 2);
        }

        try {
            $ips = Cache::get(self::UA_IPS_KEY_PREFIX . self::uaHash($ctx->request->userAgent()), []);
            $current = FormProtectionLog::hashIp($ctx->request->ip());
            $others = array_unique(array_filter(is_array($ips) ? $ips : [], fn ($hash) => $hash !== $current));

            if (count($others) >= 2) {
                $signals[] = TrustSignal::risk('velocity_same_ua_many_ips', $group, 3);
            }
        } catch (\Throwable $e) {
            // Cache unavailable: no UA/IP correlation possible.
        }

        return $signals;
    }

    public static function uaHash(?string $userAgent): string
    {
        return substr(hash('sha256', (string) $userAgent), 0, 16);
    }
}
