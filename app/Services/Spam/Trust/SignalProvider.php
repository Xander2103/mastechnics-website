<?php

namespace App\Services\Spam\Trust;

/**
 * One independent source of trust signals (captcha, timing, browser, …).
 * Providers are pure observers: they read the context and the cache, they
 * never store anything — TrustEvaluator::recordAccepted() does that.
 */
interface SignalProvider
{
    /** @return array<int, TrustSignal> */
    public function collect(TrustContext $ctx): array;
}
