<?php

namespace App\Services\Spam\Trust;

use App\Services\Spam\Rejection;

/**
 * Outcome of the trust evaluation of one submission.
 *
 *   trusted       stored, transactional mail allowed
 *   needs_review  stored with trust_verdict = needs_review, 0 mails,
 *                 visible in the admin for a human decision
 *   blocked       nothing stored, nothing mailed; `rejection` says how the
 *                 visitor is answered (error message or silent success)
 *
 * Immutable; FormMailer refuses any Mailable that is not accompanied by a
 * trusted decision, so a controller cannot forget the check.
 */
final class TrustDecision
{
    public const TRUSTED = 'trusted';

    public const NEEDS_REVIEW = 'needs_review';

    public const BLOCKED = 'blocked';

    public const RISK_LOW = 'low';

    public const RISK_MEDIUM = 'medium';

    public const RISK_HIGH = 'high';

    /**
     * @param  array<int, TrustSignal>  $signals
     */
    public function __construct(
        public readonly string $verdict,
        public readonly int $risk,
        public readonly int $positives,
        public readonly array $signals = [],
        public readonly bool $attackMode = false,
        public readonly ?Rejection $rejection = null,
        public readonly ?string $origin = null,
    ) {
    }

    public function trusted(): bool
    {
        return $this->verdict === self::TRUSTED;
    }

    public function needsReview(): bool
    {
        return $this->verdict === self::NEEDS_REVIEW;
    }

    public function blocked(): bool
    {
        return $this->verdict === self::BLOCKED;
    }

    /** Codes of every signal that contributed risk or a block, in evaluation order. */
    public function reasons(): array
    {
        $codes = [];

        foreach ($this->signals as $signal) {
            if ($signal->block || $signal->risk > 0) {
                $codes[] = $signal->code;
            }
        }

        if ($this->attackMode && ! in_array('attack_mode', $codes, true)) {
            $codes[] = 'attack_mode';
        }

        if ($this->origin !== null) {
            $codes[] = $this->origin;
        }

        return array_values(array_unique($codes));
    }

    /** Codes of positive (human) signals. */
    public function positiveCodes(): array
    {
        $codes = [];

        foreach ($this->signals as $signal) {
            if ($signal->positive) {
                $codes[] = $signal->code;
            }
        }

        return array_values(array_unique($codes));
    }

    /**
     * Signal codes grouped for the security log: {group: [code, ...]}.
     * Positive signals are prefixed with "+" so the log shows both sides.
     *
     * @return array<string, array<int, string>>
     */
    public function signalsByGroup(): array
    {
        $groups = [];

        foreach ($this->signals as $signal) {
            $groups[$signal->group][] = $signal->positive ? '+' . $signal->code : $signal->code;
        }

        foreach ($groups as $group => $codes) {
            $groups[$group] = array_values(array_unique($codes));
        }

        return $groups;
    }

    public function riskLevel(): string
    {
        if ($this->blocked() || $this->risk >= 8) {
            return self::RISK_HIGH;
        }

        if ($this->risk >= 3 || $this->attackMode) {
            return self::RISK_MEDIUM;
        }

        return self::RISK_LOW;
    }

    public function signal(string $code): ?TrustSignal
    {
        foreach ($this->signals as $signal) {
            if ($signal->code === $code) {
                return $signal;
            }
        }

        return null;
    }

    /** A blocked decision built from a hard rejection (captcha, honeypot, limits, …). */
    public static function blockedBy(Rejection $rejection, array $signals = []): self
    {
        return new self(self::BLOCKED, 0, 0, $signals, false, $rejection);
    }

    /**
     * Decision an admin makes by hand ("Vrijgeven" in the admin): trusted,
     * with the origin recorded so the security log shows it was a manual
     * release and not the evaluator.
     */
    public static function manualRelease(string $adminEmail): self
    {
        return new self(self::TRUSTED, 0, 0, [], false, null, 'manual_release');
    }

    /** For tests and internal callers that need a trusted decision without evaluating. */
    public static function trustedForTests(): self
    {
        return new self(self::TRUSTED, 0, 3, [
            TrustSignal::positive('captcha_ok', TrustSignal::GROUP_CAPTCHA),
            TrustSignal::positive('fill_time_normal', TrustSignal::GROUP_TIMING),
            TrustSignal::positive('browser_consistent', TrustSignal::GROUP_BROWSER),
        ]);
    }
}
