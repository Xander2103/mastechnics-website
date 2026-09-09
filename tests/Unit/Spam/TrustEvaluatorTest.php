<?php

namespace Tests\Unit\Spam;

use App\Services\Spam\CaptchaVerdict;
use App\Services\Spam\FormTimingToken;
use App\Services\Spam\Rejection;
use App\Services\Spam\SubmissionFacts;
use App\Services\Spam\Trust\EmailDomainCheck;
use App\Services\Spam\Trust\Signals\ContentSignals;
use App\Services\Spam\Trust\Signals\IpSignals;
use App\Services\Spam\Trust\Signals\TimingSignals;
use App\Services\Spam\Trust\Signals\VelocitySignals;
use App\Services\Spam\Trust\TrustContext;
use App\Services\Spam\Trust\TrustDecision;
use App\Services\Spam\Trust\TrustEvaluator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * The trust gate is deterministic: the same context always yields the same
 * verdict, and no single signal (in particular a passed captcha) is enough
 * for "trusted".
 */
class TrustEvaluatorTest extends TestCase
{
    private const BROWSER_SERVER = [
        'REMOTE_ADDR' => '203.0.113.5',
        'HTTP_USER_AGENT' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0 Safari/537.36',
        'HTTP_ACCEPT' => 'text/html,*/*',
        'HTTP_ACCEPT_LANGUAGE' => 'nl-BE,nl;q=0.9',
        'HTTP_SEC_FETCH_SITE' => 'same-origin',
        'HTTP_SEC_FETCH_MODE' => 'navigate',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        EmailDomainCheck::fake(['example.com' => true, 'nomx.invalid' => false]);
    }

    protected function tearDown(): void
    {
        EmailDomainCheck::fake(null);

        parent::tearDown();
    }

    private function evaluator(): TrustEvaluator
    {
        return $this->app->make(TrustEvaluator::class);
    }

    private function request(array $server = [], array $input = []): Request
    {
        $input = array_merge(['form_opened_at' => '1700000000.abc'], $input);

        return Request::create('/nl/contact', 'POST', $input, [], [], array_merge(self::BROWSER_SERVER, $server));
    }

    private function facts(array $overrides = []): SubmissionFacts
    {
        return new SubmissionFacts(
            email: $overrides['email'] ?? 'jan.janssens@example.com',
            phone: array_key_exists('phone', $overrides) ? $overrides['phone'] : '+32 495 12 34 56',
            message: $overrides['message'] ?? 'Kunnen jullie mijn ketel binnenkort eens nakijken? Hij maakt lawaai.',
            name: $overrides['name'] ?? 'Jan Janssens',
            locale: 'nl',
        );
    }

    private function context(array $overrides = []): TrustContext
    {
        return new TrustContext(
            form: $overrides['form'] ?? 'contact',
            request: $overrides['request'] ?? $this->request(),
            facts: $overrides['facts'] ?? $this->facts(),
            captcha: $overrides['captcha'] ?? new CaptchaVerdict(CaptchaVerdict::PASSED, true, true, 30),
            timingStatus: $overrides['timingStatus'] ?? FormTimingToken::OK,
            fillSeconds: array_key_exists('fillSeconds', $overrides) ? $overrides['fillSeconds'] : 60,
            honeypot: $overrides['honeypot'] ?? false,
            hardRejection: $overrides['hardRejection'] ?? null,
        );
    }

    public function test_full_human_submission_is_trusted(): void
    {
        $decision = $this->evaluator()->evaluate($this->context());

        $this->assertSame(TrustDecision::TRUSTED, $decision->verdict, implode(',', $decision->reasons()));
        $this->assertSame(0, $decision->risk);
        $this->assertGreaterThanOrEqual(3, $decision->positives);
        $this->assertFalse($decision->attackMode);
        $this->assertContains('captcha_ok', $decision->positiveCodes());
        $this->assertContains('fill_time_normal', $decision->positiveCodes());
        $this->assertContains('browser_consistent', $decision->positiveCodes());
        $this->assertContains('email_matches_name', $decision->positiveCodes());
        $this->assertContains('phone_plausible', $decision->positiveCodes());
    }

    public function test_passed_captcha_alone_is_not_enough_for_trusted(): void
    {
        // Captcha solved, but the request has none of the other human traits:
        // fast fill, a browser UA without Sec-Fetch headers, no phone, an
        // address that does not match the name.
        $request = Request::create('/nl/contact', 'POST', ['form_opened_at' => 'x'], [], [], [
            'REMOTE_ADDR' => '203.0.113.9',
            'HTTP_USER_AGENT' => self::BROWSER_SERVER['HTTP_USER_AGENT'],
            'HTTP_ACCEPT' => 'text/html',
            'HTTP_ACCEPT_LANGUAGE' => 'nl',
        ]);

        $decision = $this->evaluator()->evaluate($this->context([
            'request' => $request,
            'fillSeconds' => 5,
            'facts' => $this->facts(['email' => 'x9k2q7@example.com', 'phone' => null, 'name' => 'Peter']),
        ]));

        $this->assertSame(TrustDecision::NEEDS_REVIEW, $decision->verdict);
        $this->assertContains('captcha_ok', $decision->positiveCodes());
        $this->assertContains('fill_time_fast', $decision->reasons());
        $this->assertContains('sec_fetch_missing', $decision->reasons());
        $this->assertSame(1, $decision->positives);
    }

    public function test_scripted_client_with_solved_captcha_needs_review(): void
    {
        $request = Request::create('/nl/contact', 'POST', ['form_opened_at' => 'x'], [], [], [
            'REMOTE_ADDR' => '203.0.113.9',
            'HTTP_USER_AGENT' => 'python-requests/2.31',
        ]);

        $decision = $this->evaluator()->evaluate($this->context(['request' => $request]));

        $this->assertSame(TrustDecision::NEEDS_REVIEW, $decision->verdict);
        $this->assertContains('ua_non_browser', $decision->reasons());
        $this->assertSame(TrustDecision::RISK_MEDIUM, $decision->riskLevel());
    }

    public function test_reused_fill_time_token_sends_to_review(): void
    {
        $key = TimingSignals::seenKey('1700000000.abc');
        Cache::put($key, 1, 600);

        $decision = $this->evaluator()->evaluate($this->context());

        $this->assertSame(TrustDecision::NEEDS_REVIEW, $decision->verdict);
        $this->assertContains('fill_time_token_reused', $decision->reasons());
    }

    public function test_record_accepted_marks_token_as_seen(): void
    {
        $evaluator = $this->evaluator();
        $ctx = $this->context();

        $first = $evaluator->evaluate($ctx);
        $this->assertSame(TrustDecision::TRUSTED, $first->verdict);

        $evaluator->recordAccepted($ctx, $first);

        // Same token again, from another IP and another address: review.
        $second = $evaluator->evaluate($this->context([
            'request' => $this->request(['REMOTE_ADDR' => '198.51.100.7']),
            'facts' => $this->facts(['email' => 'piet.peeters@example.com', 'name' => 'Piet Peeters']),
        ]));

        $this->assertSame(TrustDecision::NEEDS_REVIEW, $second->verdict);
        $this->assertContains('fill_time_token_reused', $second->reasons());
    }

    public function test_attack_mode_prevents_trusted_even_for_perfect_human(): void
    {
        for ($i = 0; $i < 10; $i++) {
            RateLimiter::hit(VelocitySignals::BURST_KEY, 600);
        }

        $decision = $this->evaluator()->evaluate($this->context());

        $this->assertSame(TrustDecision::NEEDS_REVIEW, $decision->verdict);
        $this->assertTrue($decision->attackMode);
        $this->assertContains('velocity_attack', $decision->reasons());
        $this->assertContains('attack_mode', $decision->reasons());
    }

    public function test_elevated_velocity_adds_risk_but_no_attack_mode(): void
    {
        for ($i = 0; $i < 5; $i++) {
            RateLimiter::hit(VelocitySignals::BURST_KEY, 600);
        }

        // A flawless human submission during elevated traffic is still
        // trusted (risk 2 = the ceiling), but elevated traffic plus any
        // other doubt (here: a fast fill) tips it into review.
        $perfect = $this->evaluator()->evaluate($this->context());
        $this->assertSame(TrustDecision::TRUSTED, $perfect->verdict);
        $this->assertFalse($perfect->attackMode);
        $this->assertContains('velocity_elevated', $perfect->reasons());

        $withDoubt = $this->evaluator()->evaluate($this->context(['fillSeconds' => 5]));
        $this->assertSame(TrustDecision::NEEDS_REVIEW, $withDoubt->verdict);
        $this->assertFalse($withDoubt->attackMode);
    }

    public function test_high_risk_sum_is_blocked_silently(): void
    {
        // curl (4) + reused token (4) + disposable (3) + urls (2) = 13 >= 12
        Cache::put(TimingSignals::seenKey('1700000000.abc'), 1, 600);
        $request = Request::create('/nl/contact', 'POST', ['form_opened_at' => '1700000000.abc'], [], [], [
            'REMOTE_ADDR' => '203.0.113.9',
            'HTTP_USER_AGENT' => 'curl/8.0',
        ]);

        $decision = $this->evaluator()->evaluate($this->context([
            'request' => $request,
            'facts' => $this->facts([
                'email' => 'x@mailinator.com',
                'message' => 'Check http://a.example and https://b.example for cheap stuff now.',
            ]),
        ]));

        $this->assertSame(TrustDecision::BLOCKED, $decision->verdict);
        $this->assertGreaterThanOrEqual(12, $decision->risk);
        $this->assertInstanceOf(Rejection::class, $decision->rejection);
        $this->assertSame('trust_score', $decision->rejection->reason);
        $this->assertTrue($decision->rejection->silentSuccess);
        $this->assertSame(TrustDecision::RISK_HIGH, $decision->riskLevel());
    }

    public function test_hard_rejection_is_blocked_with_that_rejection_and_no_side_effects(): void
    {
        $rejection = new Rejection('captcha_failed', 'captcha', 'captcha');
        EmailDomainCheck::fake([]); // any lookup would return null; we assert none is needed

        $decision = $this->evaluator()->evaluate($this->context([
            'hardRejection' => $rejection,
            'captcha' => CaptchaVerdict::failed(['invalid-input-response']),
        ]));

        $this->assertSame(TrustDecision::BLOCKED, $decision->verdict);
        $this->assertSame($rejection, $decision->rejection);
        $this->assertContains('captcha_failed', $decision->reasons());
        // Only captcha + timing providers ran: no e-mail/content/ip signals.
        $this->assertArrayNotHasKey('email', $decision->signalsByGroup());
        $this->assertArrayNotHasKey('content', $decision->signalsByGroup());
    }

    public function test_hostname_mismatch_and_honeypot_are_hard_blocks(): void
    {
        $hostname = $this->evaluator()->evaluate($this->context([
            'captcha' => new CaptchaVerdict(CaptchaVerdict::HOSTNAME_MISMATCH, false, null),
        ]));
        $this->assertSame(TrustDecision::BLOCKED, $hostname->verdict);
        $this->assertSame('captcha_hostname', $hostname->rejection->reason);
        $this->assertFalse($hostname->rejection->silentSuccess);

        $action = $this->evaluator()->evaluate($this->context([
            'captcha' => new CaptchaVerdict(CaptchaVerdict::ACTION_MISMATCH, true, false),
        ]));
        $this->assertSame('captcha_action', $action->rejection->reason);

        $honeypot = $this->evaluator()->evaluate($this->context(['honeypot' => true]));
        $this->assertSame(TrustDecision::BLOCKED, $honeypot->verdict);
        $this->assertSame('honeypot', $honeypot->rejection->reason);
        $this->assertTrue($honeypot->rejection->silentSuccess);

        $tooFast = $this->evaluator()->evaluate($this->context(['timingStatus' => FormTimingToken::TOO_FAST, 'fillSeconds' => 1]));
        $this->assertSame('fill_time_too_fast', $tooFast->rejection->reason);
    }

    public function test_disposable_domain_and_missing_mx_are_flagged(): void
    {
        $disposable = $this->evaluator()->evaluate($this->context([
            'facts' => $this->facts(['email' => 'jan@mailinator.com']),
        ]));
        $this->assertSame(TrustDecision::NEEDS_REVIEW, $disposable->verdict);
        $this->assertContains('email_disposable', $disposable->reasons());
        $this->assertNotContains('email_plausible', $disposable->positiveCodes());

        $noMx = $this->evaluator()->evaluate($this->context([
            'facts' => $this->facts(['email' => 'jan@nomx.invalid']),
        ]));
        $this->assertContains('email_domain_no_mx', $noMx->reasons());
    }

    public function test_similar_recent_message_is_flagged_after_record_accepted(): void
    {
        $evaluator = $this->evaluator();
        $ctx = $this->context([
            'facts' => $this->facts(['message' => 'Hallo, ik zoek een goedkope airco voor mijn woning in Gent, graag offerte.']),
        ]);
        $evaluator->recordAccepted($ctx, $evaluator->evaluate($ctx));

        $variant = $this->context([
            'request' => $this->request(['REMOTE_ADDR' => '198.51.100.8'], ['form_opened_at' => 'other']),
            'facts' => $this->facts([
                'email' => 'piet.peeters@example.com',
                'name' => 'Piet Peeters',
                'message' => 'Hallo, ik zoek een goedkope airco voor mijn woning in Brugge, graag offerte.',
            ]),
        ]);

        $decision = $evaluator->evaluate($variant);

        $this->assertContains('content_similar_recent', $decision->reasons());
        $this->assertSame(TrustDecision::NEEDS_REVIEW, $decision->verdict);
    }

    public function test_ip_with_many_addresses_and_flagged_ip_add_risk(): void
    {
        $evaluator = $this->evaluator();

        foreach (['a@example.com', 'b@example.com', 'c@example.com'] as $email) {
            $ctx = $this->context([
                'request' => $this->request([], ['form_opened_at' => 'tok-' . $email]),
                'facts' => $this->facts(['email' => $email]),
            ]);
            $evaluator->recordAccepted($ctx, $evaluator->evaluate($ctx));
        }

        $decision = $evaluator->evaluate($this->context([
            'request' => $this->request([], ['form_opened_at' => 'tok-4']),
            'facts' => $this->facts(['email' => 'd@example.com']),
        ]));

        $this->assertContains('ip_many_emails', $decision->reasons());

        $blockedCtx = $this->context(['request' => $this->request(['REMOTE_ADDR' => '192.0.2.44'])]);
        $evaluator->recordBlocked($blockedCtx);
        $this->assertTrue(Cache::has(IpSignals::FLAGGED_KEY_PREFIX . \App\Services\Spam\FormProtectionLog::hashIp('192.0.2.44')));

        $flagged = $evaluator->evaluate($blockedCtx);
        $this->assertContains('ip_recently_flagged', $flagged->reasons());
    }

    public function test_same_user_agent_from_many_ips_is_flagged(): void
    {
        $evaluator = $this->evaluator();

        foreach (['203.0.113.1', '203.0.113.2'] as $ip) {
            $ctx = $this->context(['request' => $this->request(['REMOTE_ADDR' => $ip], ['form_opened_at' => 'tok-' . $ip])]);
            $evaluator->recordAccepted($ctx, $evaluator->evaluate($ctx));
        }

        $decision = $evaluator->evaluate($this->context([
            'request' => $this->request(['REMOTE_ADDR' => '203.0.113.3'], ['form_opened_at' => 'tok-3']),
        ]));

        $this->assertContains('velocity_same_ua_many_ips', $decision->reasons());
    }

    public function test_signals_by_group_shape(): void
    {
        $groups = $this->evaluator()->evaluate($this->context())->signalsByGroup();

        $this->assertSame(['+captcha_ok'], $groups['captcha']);
        $this->assertSame(['+fill_time_normal'], $groups['timing']);
        $this->assertSame(['+browser_consistent'], $groups['browser']);
        $this->assertContains('+email_plausible', $groups['email']);
        $this->assertContains('+phone_plausible', $groups['content']);
    }

    public function test_content_signals_expose_name_repetition_after_records(): void
    {
        $evaluator = $this->evaluator();

        for ($i = 0; $i < 3; $i++) {
            $ctx = $this->context([
                'request' => $this->request(['REMOTE_ADDR' => "203.0.113.1{$i}"], ['form_opened_at' => "tok-{$i}"]),
                'facts' => $this->facts(['email' => "user{$i}@example.com", 'message' => "Bericht nummer {$i} over een totaal ander onderwerp dan de rest, echt waar."]),
            ]);
            $evaluator->recordAccepted($ctx, $evaluator->evaluate($ctx));
        }

        $decision = $evaluator->evaluate($this->context([
            'request' => $this->request(['REMOTE_ADDR' => '203.0.113.99'], ['form_opened_at' => 'tok-x']),
            'facts' => $this->facts(['email' => 'nieuw@example.com', 'message' => 'Nog een ander bericht met een nieuwe vraag over verwarming thuis.']),
        ]));

        $this->assertContains('name_repeated_recent', $decision->reasons());
        $this->assertSame(ContentSignals::NAME_KEY_PREFIX . hash('sha256', 'jan janssens'), ContentSignals::nameKey(' Jan   Janssens '));
    }
}
