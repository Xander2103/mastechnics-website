<?php

namespace Tests\Feature\Spam;

use App\Models\ContactSubmission;
use App\Models\CustomerRequest;
use App\Models\FormSecurityEvent;
use App\Services\Spam\CaptchaVerdict;
use App\Services\Spam\FormMailer;
use App\Services\Spam\FormProtectionLog;
use App\Services\Spam\PublicFormGuard;
use App\Services\Spam\Trust\Signals\VelocitySignals;
use App\Services\Spam\Trust\TrustDecision;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\TestResponse;
use Tests\Support\FakeCaptchaVerifier;
use Tests\Support\InteractsWithFormProtection;
use Tests\TestCase;

/**
 * Release-blocking proof for sprint 21: only a submission that scores as
 * human on several independent signals may cause transactional mail. A
 * captcha pass alone is not enough; doubt is stored as needs_review with
 * zero provider calls; the joint circuit breaker cannot be bypassed by
 * rotating IPs and addresses. SpyMailTransport counts what would leave
 * the server — Mail::fake() is deliberately not used.
 */
class TrustGateTest extends TestCase
{
    use InteractsWithFormProtection;
    use RefreshDatabase;

    private const CONTACT = PublicFormGuard::FORM_CONTACT;

    private const REQUEST = PublicFormGuard::FORM_REQUEST;

    private const CAPTCHA_TOKEN = FakeCaptchaVerifier::VALID_TOKEN;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'site.contact_notification_email' => 'martin@mastechnics.be',
            'site.request_notification_email' => 'martin@mastechnics.be',
            'admin.notification_emails' => [],
            'form-protection.mail.burst_limit_per_hour' => 500,
            'form-protection.mail.customer_confirmation_daily_limit' => 500,
            'form-protection.mail.admin_notification_daily_limit' => 500,
            'form-protection.mail.external_daily_limit' => 500,
            'form-protection.mail.external_limit_per_10_minutes' => 500,
            'form-protection.mail.customer_confirmation_enabled' => false,
        ]);

        $this->useSpyMailTransport();
        $this->useFakeCaptcha();
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    /** A payload a real customer would send; every call differs in name, address and text. */
    private function humanPayload(string $form, int $i = 0, array $overrides = []): array
    {
        $names = ['Jan Janssens', 'Marie Peeters', 'Tom Claes', 'Els Maes', 'Pieter Willems', 'Sofie Jacobs', 'Koen Mertens', 'An Wouters'];
        $messages = [
            'Onze ketel maakt sinds gisteren een vreemd geluid bij het opstarten, kunnen jullie langskomen?',
            'Graag een offerte voor een warmtepomp in een nieuwbouw met drie slaapkamers in Leuven.',
            'De waterverzachter geeft een foutcode E4 en het water is opnieuw hard. Wanneer kan iemand komen?',
            'Wij zoeken een onderhoudscontract voor de airco in ons kantoor, twee binnenunits.',
            'Er lekt water uit de buis onder de gootsteen, dringend graag een loodgieter.',
            'Kan ik een afspraak maken voor het jaarlijkse onderhoud van de gasketel in oktober?',
            'Bij het opstarten van de verwarming ruik ik gas in de kelder, wat moet ik doen?',
            'Wij willen een koelcel voor de bakkerij, ongeveer 10 m², wat is de richtprijs?',
        ];
        $name = $names[$i % count($names)];
        $local = strtolower(str_replace(' ', '.', $name));
        $email = "{$local}{$i}@example.com";
        $message = $messages[$i % count($messages)] . " (ref {$i})";

        $base = $form === self::CONTACT
            ? [
                'name' => $name,
                'email' => $email,
                'phone' => '+32 495 12 34 ' . str_pad((string) ($i % 100), 2, '0', STR_PAD_LEFT),
                'subject' => 'Vraag ' . $i,
                'message' => $message,
            ]
            : [
                'service_category' => 'sanitair',
                'customer_type' => 'residential',
                'urgency' => 'not_urgent',
                'description' => $message,
                'unknown_device_details' => '1',
                'street' => 'Voorbeeldstraat ' . ($i + 1),
                'postal_code' => '1000',
                'city' => 'Brussel',
                'customer_name' => $name,
                'customer_email' => $email,
                'customer_phone' => '+32 495 12 34 ' . str_pad((string) ($i % 100), 2, '0', STR_PAD_LEFT),
                'privacy_consent' => '1',
            ];

        return array_merge($base, $this->protectionFields($form), $overrides);
    }

    private function submit(string $form, array $payload, array $server = []): TestResponse
    {
        $route = $form === self::CONTACT ? 'contact.store' : 'customer-requests.store';

        return $this->withHeaders($server)->post(route($route, ['locale' => 'nl']), $payload);
    }

    private function table(string $form): string
    {
        return $form === self::CONTACT ? 'contact_submissions' : 'customer_requests';
    }

    private function successKey(string $form): string
    {
        return $form === self::CONTACT ? 'contact_message_sent' : 'request_created';
    }

    /** @return array<int, string> */
    private function forms(): array
    {
        return [self::CONTACT, self::REQUEST];
    }

    /** A different, realistic browser per index so the same-UA-many-IPs signal does not fire for distinct humans. */
    private function browser(int $i): string
    {
        $agents = [
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 14_6) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.6 Safari/605.1.15',
            'Mozilla/5.0 (iPhone; CPU iPhone OS 17_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.6 Mobile/15E148 Safari/604.1',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:130.0) Gecko/20100101 Firefox/130.0',
            'Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0.0.0 Mobile Safari/537.36',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/127.0.0.0 Safari/537.36 Edg/127.0.0.0',
        ];

        return $agents[$i % count($agents)];
    }

    // ── Trusted ─────────────────────────────────────────────────────────────

    public function test_trusted_submission_stores_one_row_and_sends_exactly_the_expected_mails(): void
    {
        foreach ($this->forms() as $form) {
            $this->useSpyMailTransport();

            $this->submit($form, $this->humanPayload($form))
                ->assertSessionHasNoErrors()
                ->assertSessionHas('success', $this->successKey($form));

            $this->assertDatabaseCount($this->table($form), 1);
            $this->assertDatabaseHas($this->table($form), ['trust_verdict' => TrustDecision::TRUSTED]);

            // Customer confirmations are off by default: admin mail only.
            $this->assertProviderCalls(1, "{$form}: exactly one admin mail, no customer mail");
            $this->assertSame(['martin@mastechnics.be'], $this->mailTransport->recipients());

            $event = FormSecurityEvent::where('form', $form)->latest('id')->first();
            $this->assertNotNull($event);
            $this->assertSame(TrustDecision::TRUSTED, $event->decision);
            $this->assertSame(FormMailer::OUTCOME_SENT, $event->mail_admin);
            $this->assertSame(FormMailer::OUTCOME_SKIPPED, $event->mail_customer);
            $this->assertSame(FormProtectionLog::MAIL_CUSTOMER_DISABLED, $event->mail_reason);
            $this->assertSame('passed', $event->captcha_status);
            $this->assertTrue($event->captcha_hostname_ok);
            $this->assertTrue($event->captcha_action_ok);
            $this->assertSame('normal', $event->fill_time_bucket);
            $this->assertGreaterThanOrEqual(3, count($event->signals['captcha'] ?? []) + count($event->signals['timing'] ?? []) + count($event->signals['browser'] ?? []));
            $this->assertContains('+captcha_ok', $event->signals['captcha']);
            $this->assertContains('+fill_time_normal', $event->signals['timing']);
            $this->assertContains('+browser_consistent', $event->signals['browser']);
        }
    }

    public function test_trusted_submission_sends_the_customer_confirmation_only_when_enabled(): void
    {
        config(['form-protection.mail.customer_confirmation_enabled' => true]);

        $this->submit(self::CONTACT, $this->humanPayload(self::CONTACT))->assertSessionHasNoErrors();

        $this->assertProviderCalls(2);
        $this->assertEqualsCanonicalizing(['martin@mastechnics.be', 'jan.janssens0@example.com'], $this->mailTransport->recipients());
        $this->assertSame(FormMailer::OUTCOME_SENT, FormSecurityEvent::first()->mail_customer);
    }

    public function test_customer_confirmation_disabled_means_zero_customer_transport_calls_on_both_forms(): void
    {
        config(['form-protection.mail.customer_confirmation_enabled' => false]);

        foreach ($this->forms() as $i => $form) {
            $this->submit($form, $this->humanPayload($form, $i))->assertSessionHasNoErrors();
        }

        $this->assertProviderCalls(2, 'one admin mail per form, no customer mail');
        $this->assertSame(['martin@mastechnics.be', 'martin@mastechnics.be'], $this->mailTransport->recipients());
        $this->assertSame(2, FormSecurityEvent::where('mail_customer', FormMailer::OUTCOME_SKIPPED)->count());
    }

    // ── Captcha alone is not enough ─────────────────────────────────────────

    public function test_captcha_success_alone_is_not_enough_to_send_mail(): void
    {
        foreach ($this->forms() as $form) {
            $this->useSpyMailTransport();

            // Valid captcha token, but: filled in 5 s and without the headers
            // every browser sends on a form POST. Two independent doubts →
            // stored for review, no mail.
            $payload = $this->humanPayload($form, 3, [
                config('form-protection.timing.field') => $this->timingToken($form, 5),
            ]);

            $this->withoutHeader('Sec-Fetch-Site')->withoutHeader('Sec-Fetch-Mode');

            $this->submit($form, $payload)
                ->assertSessionHasNoErrors()
                ->assertSessionHas('success', $this->successKey($form));

            $this->assertSame(1, count($this->captcha->calls), 'captcha verified once');
            $this->assertDatabaseCount($this->table($form), 1);
            $this->assertDatabaseHas($this->table($form), ['trust_verdict' => TrustDecision::NEEDS_REVIEW]);
            $this->assertProviderCalls(0, "{$form}: a submission under review must never reach the provider");

            $event = FormSecurityEvent::where('form', $form)->latest('id')->first();
            $this->assertSame(TrustDecision::NEEDS_REVIEW, $event->decision);
            $this->assertSame('passed', $event->captcha_status);
            $this->assertContains('fill_time_fast', $event->reasons);
            $this->assertContains('sec_fetch_missing', $event->reasons);
            $this->assertSame(FormMailer::OUTCOME_SKIPPED, $event->mail_admin);
            $this->assertSame(FormProtectionLog::MAIL_NOT_TRUSTED, $event->mail_reason);
            $this->assertGreaterThanOrEqual(1, $event->mails_prevented);

            $this->captcha->calls = [];
        }
    }

    public function test_script_client_with_a_solved_captcha_is_stored_for_review_without_mail(): void
    {
        $this->submit(self::CONTACT, $this->humanPayload(self::CONTACT, 1), ['User-Agent' => 'python-requests/2.32'])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('contact_submissions', ['trust_verdict' => TrustDecision::NEEDS_REVIEW]);
        $this->assertProviderCalls(0);
        $this->assertContains('ua_non_browser', FormSecurityEvent::first()->reasons);
        $this->assertSame('python-requests', FormSecurityEvent::first()->user_agent_family);
    }

    public function test_reused_fill_time_token_is_stored_for_review_without_mail(): void
    {
        $token = $this->timingToken(self::CONTACT, 120);

        $this->submit(self::CONTACT, $this->humanPayload(self::CONTACT, 0, [config('form-protection.timing.field') => $token]))
            ->assertSessionHasNoErrors();
        $this->assertProviderCalls(1, 'first use of the token: trusted');

        // Same page token, different visitor identity (as a bot rotating IP + address would do).
        $this->submit(self::CONTACT, $this->humanPayload(self::CONTACT, 1, [config('form-protection.timing.field') => $token]), ['REMOTE_ADDR' => '198.51.100.9'])
            ->assertSessionHasNoErrors();

        $this->assertProviderCalls(1, 'second use of the same token: no mail');
        $this->assertSame(1, ContactSubmission::where('trust_verdict', TrustDecision::NEEDS_REVIEW)->count());
        $this->assertContains('fill_time_token_reused', FormSecurityEvent::latest('id')->first()->reasons);
    }

    public function test_attack_mode_puts_every_submission_under_review(): void
    {
        $attackThreshold = (int) config('form-protection.trust.velocity.attack_per_10_minutes');

        for ($i = 0; $i < $attackThreshold; $i++) {
            RateLimiter::hit(VelocitySignals::BURST_KEY, 600);
        }

        // Keep the hard global burst limiter out of the way: this test is about the trust gate.
        config(['form-protection.accepted_burst_limit_per_10_minutes' => 1000]);

        $this->submit(self::REQUEST, $this->humanPayload(self::REQUEST))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('customer_requests', ['trust_verdict' => TrustDecision::NEEDS_REVIEW]);
        $this->assertProviderCalls(0);

        $event = FormSecurityEvent::first();
        $this->assertTrue($event->attack_mode);
        $this->assertContains('velocity_attack', $event->reasons);
    }

    // ── Turnstile metadata ──────────────────────────────────────────────────

    public function test_captcha_token_for_another_hostname_is_refused_with_zero_storage_and_zero_mail(): void
    {
        $this->captcha->verdict = new CaptchaVerdict(CaptchaVerdict::HOSTNAME_MISMATCH, hostnameOk: false, actionOk: null);

        foreach ($this->forms() as $form) {
            $this->submit($form, $this->humanPayload($form))->assertSessionHasErrors('captcha');
            $this->assertDatabaseCount($this->table($form), 0);
        }

        $this->assertProviderCalls(0);
        $this->assertSame(2, FormSecurityEvent::where('decision', TrustDecision::BLOCKED)->where('captcha_status', 'hostname_mismatch')->count());
        $this->assertContains('captcha_hostname', FormSecurityEvent::first()->reasons);
        $this->assertFalse(FormSecurityEvent::first()->captcha_hostname_ok);
    }

    public function test_captcha_token_for_another_action_is_refused_with_zero_storage_and_zero_mail(): void
    {
        $this->captcha->verdict = new CaptchaVerdict(CaptchaVerdict::ACTION_MISMATCH, hostnameOk: true, actionOk: false);

        foreach ($this->forms() as $form) {
            $this->submit($form, $this->humanPayload($form))->assertSessionHasErrors('captcha');
            $this->assertDatabaseCount($this->table($form), 0);
        }

        $this->assertProviderCalls(0);
        $this->assertSame(2, FormSecurityEvent::where('captcha_status', 'action_mismatch')->count());
        $this->assertFalse(FormSecurityEvent::first()->captcha_action_ok);
        $this->assertSame(['contact', 'request'], array_map(fn ($c) => $c['expectedAction'], $this->captcha->calls), 'the form id is passed as the expected action');
    }

    // ── Circuit breaker cannot be bypassed ──────────────────────────────────

    public function test_rotating_ips_and_addresses_cannot_bypass_the_external_mail_daily_limit(): void
    {
        config(['form-protection.mail.external_daily_limit' => 3]);

        for ($i = 0; $i < 6; $i++) {
            $form = $i % 2 === 0 ? self::CONTACT : self::REQUEST;

            $this->submit($form, $this->humanPayload($form, $i), [
                'REMOTE_ADDR' => "203.0.113.{$i}",
                'User-Agent' => $this->browser($i),
            ])->assertSessionHasNoErrors();
        }

        $this->assertProviderCalls(3, 'the joint daily limit stops every further provider call, whatever the IP or address');
        $this->assertSame(6, FormSecurityEvent::where('decision', TrustDecision::TRUSTED)->count(), 'all six were human and stored');
        $this->assertDatabaseCount('contact_submissions', 3);
        $this->assertDatabaseCount('customer_requests', 3);
        $this->assertSame(3, FormSecurityEvent::where('mail_reason', FormProtectionLog::MAIL_CIRCUIT_DAILY)->count());
    }

    public function test_external_mail_burst_limit_stops_mail_after_the_limit_within_ten_minutes(): void
    {
        config(['form-protection.mail.external_limit_per_10_minutes' => 2]);

        for ($i = 0; $i < 4; $i++) {
            $this->submit(self::CONTACT, $this->humanPayload(self::CONTACT, $i), [
                'REMOTE_ADDR' => "203.0.113.{$i}",
                'User-Agent' => $this->browser($i),
            ])->assertSessionHasNoErrors();
        }

        $this->assertProviderCalls(2);
        $this->assertDatabaseCount('contact_submissions', 4);
        $this->assertSame(2, FormSecurityEvent::where('mail_reason', FormProtectionLog::MAIL_CIRCUIT_BURST)->count());
    }

    // ── Security log guarantees ─────────────────────────────────────────────

    public function test_blocked_submissions_are_logged_without_storage(): void
    {
        $this->submit(self::CONTACT, $this->humanPayload(self::CONTACT, 0, [PublicFormGuard::HONEYPOT_FIELD => 'http://spam.example']))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('contact_submissions', 0);
        $this->assertProviderCalls(0);

        $event = FormSecurityEvent::first();
        $this->assertSame(TrustDecision::BLOCKED, $event->decision);
        $this->assertContains('honeypot', $event->reasons);
        $this->assertNull($event->subject_id);
        $this->assertSame(1, $event->mails_prevented);
    }

    public function test_needs_review_event_links_to_the_stored_row(): void
    {
        $this->submit(self::REQUEST, $this->humanPayload(self::REQUEST), ['User-Agent' => 'curl/8.4.0'])->assertSessionHasNoErrors();

        $request = CustomerRequest::first();
        $event = FormSecurityEvent::first();

        $this->assertSame(TrustDecision::NEEDS_REVIEW, $event->decision);
        $this->assertSame($request->getMorphClass(), $event->subject_type);
        $this->assertSame($request->id, $event->subject_id);
        $this->assertTrue($event->subject->is($request));
        $this->assertTrue($request->needsReview());
        $this->assertSame($event->reasons, $request->trust_reasons);
    }

    public function test_security_event_never_contains_tokens_headers_names_or_message_bodies(): void
    {
        $payload = $this->humanPayload(self::CONTACT, 2);
        $userAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0.0.0 Safari/537.36';

        $this->submit(self::CONTACT, $payload, ['REMOTE_ADDR' => '203.0.113.77', 'User-Agent' => $userAgent])->assertSessionHasNoErrors();

        $row = (array) \DB::table('form_security_events')->first();
        $json = json_encode($row);

        $this->assertStringNotContainsString(self::CAPTCHA_TOKEN, $json);
        $this->assertStringNotContainsString($payload[config('form-protection.timing.field')], $json);
        $this->assertStringNotContainsString($payload['message'], $json);
        $this->assertStringNotContainsString($payload['name'], $json);
        $this->assertStringNotContainsString($payload['email'], $json);
        $this->assertStringNotContainsString('203.0.113.77', $json);
        $this->assertStringNotContainsString('AppleWebKit', $json);
        $this->assertStringNotContainsString('sec-fetch', strtolower($json));

        $this->assertSame('to***@example.com', $row['email_masked']);
        $this->assertSame(16, strlen($row['ip_hash']));
        $this->assertSame('Chrome 128 · Windows', $row['user_agent_family']);
    }

    public function test_a_failing_security_log_never_breaks_the_submission_or_doubles_mail(): void
    {
        Schema::drop('form_security_events');

        $this->submit(self::CONTACT, $this->humanPayload(self::CONTACT))
            ->assertRedirect()
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'contact_message_sent');

        $this->assertDatabaseCount('contact_submissions', 1);
        $this->assertProviderCalls(1, 'exactly one admin mail, the log failure changes nothing');
    }
}
