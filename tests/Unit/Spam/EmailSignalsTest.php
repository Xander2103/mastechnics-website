<?php

namespace Tests\Unit\Spam;

use App\Services\Spam\CaptchaVerdict;
use App\Services\Spam\FormTimingToken;
use App\Services\Spam\SubmissionFacts;
use App\Services\Spam\Trust\EmailDomainCheck;
use App\Services\Spam\Trust\Signals\ContentSignals;
use App\Services\Spam\Trust\Signals\EmailSignals;
use App\Services\Spam\Trust\TrustContext;
use Illuminate\Http\Request;
use Tests\TestCase;

class EmailSignalsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        EmailDomainCheck::fake(['example.com' => true, 'gmail.com' => true, 'nomx.invalid' => false]);
    }

    protected function tearDown(): void
    {
        EmailDomainCheck::fake(null);

        parent::tearDown();
    }

    /** @return array<int, string> */
    private function codes(string $email, ?string $name = null): array
    {
        $config = (array) config('form-protection.trust');
        $request = Request::create('/nl/contact', 'POST');
        $ctx = new TrustContext('contact', $request, new SubmissionFacts($email, null, null, $name), CaptchaVerdict::disabled(), FormTimingToken::OK, 30, false);

        return array_map(fn ($s) => ($s->positive ? '+' : '') . $s->code, (new EmailSignals($config, new EmailDomainCheck($config)))->collect($ctx));
    }

    public function test_plausible_address_matching_name_is_positive_twice(): void
    {
        $codes = $this->codes('jan.janssens@example.com', 'Jan Janssens');

        $this->assertContains('+email_plausible', $codes);
        $this->assertContains('+email_matches_name', $codes);
    }

    public function test_accented_name_still_matches_ascii_local_part(): void
    {
        $this->assertContains('+email_matches_name', $this->codes('francois.d@gmail.com', 'François Dupont'));
    }

    public function test_disposable_domain_is_flagged(): void
    {
        $codes = $this->codes('jan@mailinator.com', 'Jan');

        $this->assertContains('email_disposable', $codes);
        $this->assertNotContains('+email_plausible', $codes);
    }

    public function test_domain_without_mail_server_is_flagged_but_unknown_is_neutral(): void
    {
        $this->assertContains('email_domain_no_mx', $this->codes('jan@nomx.invalid'));
        $this->assertContains('+email_plausible', $this->codes('jan@unknown-domain.test'));
    }

    public function test_random_local_parts_are_detected(): void
    {
        $this->assertTrue(EmailSignals::looksRandom('k8q2zx91'));
        $this->assertTrue(EmailSignals::looksRandom('83920174'));
        $this->assertTrue(EmailSignals::looksRandom('xkqzrtpwbnmfgh'));
        $this->assertTrue(EmailSignals::looksRandom('abcdfghjk'));
        $this->assertFalse(EmailSignals::looksRandom('jan.janssens'));
        $this->assertFalse(EmailSignals::looksRandom('marie2024'));
        $this->assertFalse(EmailSignals::looksRandom('jan1985'));
        $this->assertFalse(EmailSignals::looksRandom('info'));
        $this->assertContains('email_random_local_part', $this->codes('k8q2zx91@example.com'));
    }

    public function test_phone_plausibility_helper(): void
    {
        $this->assertTrue(ContentSignals::belgianPhone('+32 495 12 34 56'));
        $this->assertTrue(ContentSignals::belgianPhone('0495123456'));
        $this->assertTrue(ContentSignals::belgianPhone('02 123 45 67'));
        $this->assertFalse(ContentSignals::belgianPhone('+1 555 0100'));
        $this->assertFalse(ContentSignals::belgianPhone(null));
    }
}
