<?php

namespace App\Services\Spam\Trust\Signals;

use App\Services\Spam\FormTimingToken;
use App\Services\Spam\Trust\SignalProvider;
use App\Services\Spam\Trust\TrustContext;
use App\Services\Spam\Trust\TrustSignal;
use Illuminate\Support\Facades\Cache;

/**
 * Honeypot + fill time. Besides "too fast" (hard block, as before) the
 * evaluator now sees *how* fast, and whether this exact signed timestamp
 * was posted before: the token is valid for hours and a bot that fetches
 * the page once and re-posts the same token from many IPs is caught here.
 */
final class TimingSignals implements SignalProvider
{
    public const SEEN_KEY_PREFIX = 'form-protection:timing-seen:';

    private const VERY_OLD_SECONDS = 21600;

    /** @param array<string, mixed> $config */
    public function __construct(private readonly array $config)
    {
    }

    public function collect(TrustContext $ctx): array
    {
        $group = TrustSignal::GROUP_TIMING;

        if ($ctx->honeypot) {
            return [TrustSignal::block('honeypot', $group)];
        }

        if ($ctx->timingStatus !== FormTimingToken::OK) {
            return [TrustSignal::block('fill_time_' . $ctx->timingStatus, $group)];
        }

        $signals = [];
        $fast = (int) (($this->config['fast_seconds'] ?? [])[$ctx->form] ?? 15);
        $seconds = $ctx->fillSeconds;

        if ($seconds !== null && $seconds < $fast) {
            $signals[] = TrustSignal::risk('fill_time_fast', $group, 2);
        } elseif ($seconds !== null && $seconds > self::VERY_OLD_SECONDS) {
            $signals[] = TrustSignal::risk('fill_time_very_old', $group, 1);
        } else {
            $signals[] = TrustSignal::positive('fill_time_normal', $group);
        }

        if ($this->tokenSeenBefore($ctx)) {
            $signals[] = TrustSignal::risk('fill_time_token_reused', $group, 4);
        }

        return $signals;
    }

    public static function seenKey(mixed $token): ?string
    {
        if (! is_string($token) || trim($token) === '') {
            return null;
        }

        return self::SEEN_KEY_PREFIX . hash('sha256', trim($token));
    }

    private function tokenSeenBefore(TrustContext $ctx): bool
    {
        $key = self::seenKey($ctx->request->input((string) config('form-protection.timing.field', 'form_opened_at')));

        if ($key === null) {
            return false;
        }

        try {
            return (int) Cache::get($key, 0) >= 1;
        } catch (\Throwable $e) {
            return false;
        }
    }
}
