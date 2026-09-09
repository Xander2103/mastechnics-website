<?php

namespace App\Services\Spam\Trust;

use App\Services\Spam\FormProtectionLog;
use App\Services\Spam\RecipientSafety;
use App\Services\Spam\Rejection;
use App\Services\Spam\Trust\Signals\CaptchaSignals;
use App\Services\Spam\Trust\Signals\ContentSignals;
use App\Services\Spam\Trust\Signals\IpSignals;
use App\Services\Spam\Trust\Signals\TimingSignals;
use App\Services\Spam\Trust\Signals\VelocitySignals;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Deterministic pre-mail trust gate. Collects independent signals and
 * turns them into trusted / needs_review / blocked:
 *
 *   trusted       risk <= trusted_max_risk AND positives >= trusted_min_positives
 *                 AND no attack mode
 *   blocked       any hard signal (captcha, honeypot, fill time, limits)
 *                 or risk >= block_score
 *   needs_review  everything in between
 *
 * No single signal can produce "trusted": a passed captcha is one positive
 * signal and at least three are required. Thresholds and weights come from
 * config('form-protection.trust'); nothing here is learned or random.
 */
class TrustEvaluator
{
    /**
     * @param  array<string, mixed>  $config  config('form-protection.trust')
     * @param  array<int, SignalProvider>  $providers
     */
    public function __construct(
        private readonly array $config,
        private readonly array $providers,
    ) {
    }

    public function evaluate(TrustContext $ctx): TrustDecision
    {
        // A hard rejection was already established by the guard (captcha,
        // honeypot, fill time, limits, duplicate): only record which, do
        // not run DNS lookups or cache scans for a bot.
        if ($ctx->hardRejection !== null) {
            return TrustDecision::blockedBy($ctx->hardRejection, $this->collectFrom($ctx, [CaptchaSignals::class, TimingSignals::class]));
        }

        $signals = $this->collectFrom($ctx, null);

        foreach ($signals as $signal) {
            if ($signal->block) {
                return TrustDecision::blockedBy(
                    new Rejection($signal->code, 'captcha', 'captcha', $signal->code === 'honeypot'),
                    $signals
                );
            }
        }

        $risk = 0;
        $positives = 0;
        $attackMode = false;

        foreach ($signals as $signal) {
            $risk += $signal->risk;

            if ($signal->positive) {
                $positives++;
            }

            if ($signal->code === VelocitySignals::ATTACK_CODE) {
                $attackMode = true;
            }
        }

        if ($risk >= (int) ($this->config['block_score'] ?? 12)) {
            // Silent success: a scripted client learns nothing from being
            // scored out, while a human that somehow lands here loses only
            // one submission attempt (and can call).
            return new TrustDecision(
                TrustDecision::BLOCKED,
                $risk,
                $positives,
                $signals,
                $attackMode,
                new Rejection('trust_score', 'captcha', 'captcha', true)
            );
        }

        $trusted = ! $attackMode
            && $risk <= (int) ($this->config['trusted_max_risk'] ?? 2)
            && $positives >= (int) ($this->config['trusted_min_positives'] ?? 3);

        return new TrustDecision(
            $trusted ? TrustDecision::TRUSTED : TrustDecision::NEEDS_REVIEW,
            $risk,
            $positives,
            $signals,
            $attackMode
        );
    }

    /**
     * Feed the rolling windows the signal providers read. Called once per
     * stored submission (trusted or needs_review). Never throws: the row
     * already exists and the decision is already made.
     */
    public function recordAccepted(TrustContext $ctx, TrustDecision $decision): void
    {
        try {
            $timingTtl = max(60, (int) config('form-protection.timing.max_hours', 12) * 3600);
            $timingKey = TimingSignals::seenKey($ctx->request->input((string) config('form-protection.timing.field', 'form_opened_at')));

            if ($timingKey !== null) {
                Cache::add($timingKey, 0, $timingTtl);
                Cache::increment($timingKey);
            }

            $hash = ContentSignals::messageHash($ctx->facts->message);

            if ($hash !== null) {
                $this->pushWindowed(
                    ContentSignals::SIMHASH_KEY_PREFIX . $ctx->form,
                    [$hash, time()],
                    (int) (($this->config['similarity']['window_seconds'] ?? 3600)),
                    (int) (($this->config['similarity']['max_entries'] ?? 200))
                );
            }

            $nameKey = ContentSignals::nameKey($ctx->facts->name);

            if ($nameKey !== null) {
                Cache::add($nameKey, 0, 3600);
                Cache::increment($nameKey);
            }

            $ipHash = FormProtectionLog::hashIp($ctx->request->ip());
            $this->addToSet(IpSignals::EMAILS_KEY_PREFIX . $ipHash, RecipientSafety::hash($ctx->facts->email), 3600);
            $this->addToSet(VelocitySignals::UA_IPS_KEY_PREFIX . VelocitySignals::uaHash($ctx->request->userAgent()), $ipHash, 600);

            RateLimiter::hit(VelocitySignals::HOUR_KEY, 3600);

            if (! $decision->trusted()) {
                Cache::put(IpSignals::FLAGGED_KEY_PREFIX . $ipHash, 1, 86400);
            }
        } catch (\Throwable $e) {
            Log::error('trust evaluator windows could not be updated', ['form' => $ctx->form, 'error' => $e->getMessage()]);
        }
    }

    /** A blocked submission only marks the IP; nothing else is recorded. */
    public function recordBlocked(TrustContext $ctx): void
    {
        try {
            Cache::put(IpSignals::FLAGGED_KEY_PREFIX . FormProtectionLog::hashIp($ctx->request->ip()), 1, 86400);
        } catch (\Throwable $e) {
            Log::debug('trust evaluator could not flag ip', ['error' => $e->getMessage()]);
        }
    }

    /**
     * @param  array<int, class-string<SignalProvider>>|null  $only
     * @return array<int, TrustSignal>
     */
    private function collectFrom(TrustContext $ctx, ?array $only): array
    {
        $signals = [];

        foreach ($this->providers as $provider) {
            if ($only !== null && ! in_array($provider::class, $only, true)) {
                continue;
            }

            try {
                foreach ($provider->collect($ctx) as $signal) {
                    $signals[] = $signal;
                }
            } catch (\Throwable $e) {
                // A broken provider must never take the form down; the
                // submission simply misses that provider's evidence (and so
                // tends toward needs_review, never toward trusted).
                Log::error('trust signal provider failed', ['provider' => $provider::class, 'error' => $e->getMessage()]);
            }
        }

        return $signals;
    }

    /** @param array{0: string, 1: int} $entry */
    private function pushWindowed(string $key, array $entry, int $windowSeconds, int $maxEntries): void
    {
        $since = time() - $windowSeconds;
        $entries = Cache::get($key, []);
        $entries = array_values(array_filter(is_array($entries) ? $entries : [], fn ($e) => is_array($e) && (int) ($e[1] ?? 0) >= $since));
        $entries[] = $entry;

        if (count($entries) > $maxEntries) {
            $entries = array_slice($entries, -$maxEntries);
        }

        Cache::put($key, $entries, $windowSeconds);
    }

    private function addToSet(string $key, string $member, int $ttl): void
    {
        $members = Cache::get($key, []);
        $members = is_array($members) ? $members : [];

        if (! in_array($member, $members, true)) {
            $members[] = $member;
        }

        Cache::put($key, array_slice($members, -50), $ttl);
    }
}
