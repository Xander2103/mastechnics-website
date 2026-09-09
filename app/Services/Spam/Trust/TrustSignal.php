<?php

namespace App\Services\Spam\Trust;

/**
 * One observation about a submission. `risk` is added to the risk score
 * (0 = neutral), `positive` marks independent evidence of a human visitor,
 * `block` marks a hard rejection (nothing stored, nothing mailed). A
 * signal carries only its code and group: no values, so it can be stored
 * in the security log as-is.
 */
final class TrustSignal
{
    public const GROUP_CAPTCHA = 'captcha';

    public const GROUP_TIMING = 'timing';

    public const GROUP_BROWSER = 'browser';

    public const GROUP_EMAIL = 'email';

    public const GROUP_CONTENT = 'content';

    public const GROUP_IP = 'ip';

    public const GROUP_VELOCITY = 'velocity';

    public const GROUP_RATE_LIMIT = 'rate_limit';

    public const GROUP_DUPLICATE = 'duplicate';

    public const GROUP_FORM = 'form';

    public function __construct(
        public readonly string $code,
        public readonly string $group,
        public readonly int $risk = 0,
        public readonly bool $positive = false,
        public readonly bool $block = false,
    ) {
    }

    public static function positive(string $code, string $group): self
    {
        return new self($code, $group, 0, true, false);
    }

    public static function risk(string $code, string $group, int $risk): self
    {
        return new self($code, $group, max(0, $risk), false, false);
    }

    public static function block(string $code, string $group): self
    {
        return new self($code, $group, 0, false, true);
    }
}
