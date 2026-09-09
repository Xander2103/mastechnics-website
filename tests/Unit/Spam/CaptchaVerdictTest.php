<?php

namespace Tests\Unit\Spam;

use App\Services\Spam\CaptchaVerdict;
use PHPUnit\Framework\TestCase;

class CaptchaVerdictTest extends TestCase
{
    public function test_only_the_passed_status_passes(): void
    {
        $this->assertTrue((new CaptchaVerdict(CaptchaVerdict::PASSED, true, true, 3))->passed());

        foreach ([
            CaptchaVerdict::FAILED,
            CaptchaVerdict::MISSING,
            CaptchaVerdict::DISABLED,
            CaptchaVerdict::HOSTNAME_MISMATCH,
            CaptchaVerdict::ACTION_MISMATCH,
        ] as $status) {
            $this->assertFalse((new CaptchaVerdict($status))->passed(), $status);
        }
    }

    public function test_factories_set_status_and_leave_checks_unknown(): void
    {
        $disabled = CaptchaVerdict::disabled();
        $this->assertSame(CaptchaVerdict::DISABLED, $disabled->status);
        $this->assertNull($disabled->hostnameOk);
        $this->assertNull($disabled->actionOk);
        $this->assertNull($disabled->challengeAgeSeconds);
        $this->assertSame([], $disabled->errorCodes);

        $this->assertSame(CaptchaVerdict::MISSING, CaptchaVerdict::missing()->status);

        $failed = CaptchaVerdict::failed(['invalid-input-response', 42]);
        $this->assertSame(CaptchaVerdict::FAILED, $failed->status);
        $this->assertSame(['invalid-input-response', '42'], $failed->errorCodes);
    }
}
