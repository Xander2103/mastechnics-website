<?php

namespace Tests\Feature\Spam;

use App\Services\Spam\FormTimingToken;
use App\Services\Spam\PublicFormGuard;
use Database\Seeders\PageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Support\FakeCaptchaVerifier;
use Tests\Support\InteractsWithFormProtection;
use Tests\TestCase;

/**
 * Release-blocking proof for the anti-spam sprint: a rejected submission
 * creates 0 database records and causes 0 calls to the mail transport
 * (the layer that talks to Brevo/SMTP), for every layer, on both public
 * forms. A valid submission creates exactly 1 record, 1 admin mail and
 * 1 customer mail. Mail::fake() is deliberately not used — the spy
 * transport counts what would actually leave the server.
 */
class FormProtectionTest extends TestCase
{
    use InteractsWithFormProtection;
    use RefreshDatabase;

    private const CONTACT = PublicFormGuard::FORM_CONTACT;

    private const REQUEST = PublicFormGuard::FORM_REQUEST;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'site.contact_notification_email' => 'martin@mastechnics.be',
            'site.request_notification_email' => 'martin@mastechnics.be',
            'admin.notification_emails' => [],
            // The budget has its own tests below; the limiter tests send
            // more mail per hour than the production budget allows.
            'form-protection.mail.burst_limit_per_hour' => 500,
            'form-protection.mail.customer_confirmation_daily_limit' => 500,
            'form-protection.mail.admin_notification_daily_limit' => 500,
        ]);

        $this->useSpyMailTransport();
        $this->useFakeCaptcha();
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    private function payload(string $form, array $overrides = []): array
    {
        $base = $form === self::CONTACT
            ? [
                'name' => 'Jan Janssens',
                'email' => 'jan@example.com',
                'phone' => '+32 495 12 34 56',
                'subject' => 'Vraag over onderhoud',
                'message' => 'Kunnen jullie mijn ketel binnenkort eens nakijken?',
            ]
            : [
                'service_category' => 'sanitair',
                'customer_type' => 'residential',
                'urgency' => 'not_urgent',
                'description' => 'Lekkende kraan in de keuken, graag een afspraak.',
                'unknown_device_details' => '1',
                'street' => 'Voorbeeldstraat 12',
                'postal_code' => '1000',
                'city' => 'Brussel',
                'customer_name' => 'Jan Janssens',
                'customer_email' => 'jan@example.com',
                'customer_phone' => '+32 495 12 34 56',
                'privacy_consent' => '1',
            ];

        return array_merge($base, $this->protectionFields($form), $overrides);
    }

    /** A payload that differs per iteration so no limiter or fingerprint trips by accident. */
    private function distinctPayload(string $form, int $i, array $overrides = []): array
    {
        return $this->payload($form, array_merge($form === self::CONTACT
            ? ['email' => "klant{$i}@example.com", 'message' => "Bericht nummer {$i}: kunnen jullie mijn ketel nakijken?"]
            : ['customer_email' => "klant{$i}@example.com", 'description' => "Aanvraag nummer {$i}: lekkende kraan in de keuken."],
            $overrides));
    }

    private function submit(string $form, array $payload, array $headers = []): TestResponse
    {
        $route = $form === self::CONTACT ? 'contact.store' : 'customer-requests.store';

        return $this->withHeaders($headers)->post(route($route, ['locale' => 'nl']), $payload);
    }

    private function emailField(string $form): string
    {
        return $form === self::CONTACT ? 'email' : 'customer_email';
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

    private function assertNothingStoredOrMailed(string $form): void
    {
        $this->assertDatabaseCount($this->table($form), 0);
        $this->assertProviderCalls(0, "{$form}: a rejected submission must not reach the mail provider");
    }

    // ── Valid submission ────────────────────────────────────────────────────

    public function test_valid_submission_stores_one_record_and_sends_exactly_one_admin_and_one_customer_mail(): void
    {
        foreach ($this->forms() as $form) {
            $this->useSpyMailTransport();
            $this->useFakeCaptcha();

            $this->submit($form, $this->payload($form))
                ->assertSessionHasNoErrors()
                ->assertSessionHas('success', $this->successKey($form));

            $this->assertDatabaseCount($this->table($form), 1);
            $this->assertProviderCalls(2, "{$form}: exactly one admin + one customer mail");
            $this->assertEqualsCanonicalizing(
                ['martin@mastechnics.be', 'jan@example.com'],
                $this->mailTransport->recipients()
            );
            $this->assertCount(1, $this->captcha->calls, "{$form}: captcha verified exactly once");
            $this->assertSame(FakeCaptchaVerifier::VALID_TOKEN, $this->captcha->calls[0]['token']);
        }
    }

    public function test_validation_failure_happens_before_the_captcha_provider_is_asked(): void
    {
        foreach ($this->forms() as $form) {
            $this->submit($form, $this->payload($form, [$this->emailField($form) => 'not-an-email']))
                ->assertSessionHasErrors($this->emailField($form));

            $this->assertSame([], $this->captcha->calls, "{$form}: invalid input must not cost a siteverify call");
            $this->assertNothingStoredOrMailed($form);
        }
    }

    // ── Captcha ─────────────────────────────────────────────────────────────

    public function test_missing_captcha_token_creates_no_record_and_no_mail(): void
    {
        foreach ($this->forms() as $form) {
            $payload = $this->payload($form);
            unset($payload[$this->captcha->responseField()]);

            $this->submit($form, $payload)
                ->assertSessionHasErrors('captcha')
                ->assertSessionMissing('success');

            $this->assertNothingStoredOrMailed($form);
        }
    }

    public function test_invalid_captcha_token_creates_no_record_and_no_mail(): void
    {
        foreach ($this->forms() as $form) {
            $this->submit($form, $this->payload($form, [$this->captcha->responseField() => 'forged-token']))
                ->assertSessionHasErrors('captcha');

            $this->assertNothingStoredOrMailed($form);
        }
    }

    public function test_captcha_error_message_is_localized(): void
    {
        $expected = [
            'nl' => 'De anti-spamcontrole is niet gelukt',
            'fr' => 'La vérification anti-spam a échoué',
            'en' => 'The anti-spam check failed',
        ];

        foreach ($expected as $locale => $fragment) {
            $response = $this->post(
                route('contact.store', ['locale' => $locale]),
                $this->payload(self::CONTACT, [$this->captcha->responseField() => 'bad'])
            );

            $this->assertStringContainsString($fragment, $response->getSession()->get('errors')->first('captcha'));
        }

        $this->assertProviderCalls(0);
    }

    public function test_captcha_provider_can_be_swapped_without_touching_the_controllers(): void
    {
        // Same controllers, same views: only the bound verifier (and thus the
        // POST field name) changes — this is what CAPTCHA_PROVIDER does.
        $this->captcha = new FakeCaptchaVerifier(
            provider: 'recaptcha',
            responseField: 'g-recaptcha-response',
        );
        $this->app->instance(\App\Services\Spam\CaptchaVerifier::class, $this->captcha);

        $this->submit(self::CONTACT, $this->payload(self::CONTACT))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('contact_submissions', 1);
        $this->assertProviderCalls(2);

        // A Turnstile-shaped token is now meaningless.
        $payload = $this->distinctPayload(self::CONTACT, 2);
        unset($payload['g-recaptcha-response']);
        $payload['cf-turnstile-response'] = FakeCaptchaVerifier::VALID_TOKEN;

        $this->submit(self::CONTACT, $payload)->assertSessionHasErrors('captcha');
        $this->assertDatabaseCount('contact_submissions', 1);
        $this->assertProviderCalls(2);
    }

    // ── Honeypot ────────────────────────────────────────────────────────────

    public function test_any_filled_honeypot_field_is_silently_dropped(): void
    {
        foreach ($this->forms() as $form) {
            foreach (config('form-protection.honeypot_fields') as $field) {
                $this->submit($form, $this->payload($form, [$field => 'http://spam.example']))
                    ->assertSessionHasNoErrors()
                    ->assertSessionHas('success', $this->successKey($form));

                $this->assertNothingStoredOrMailed($form);
            }
        }

        $this->assertCount(2, config('form-protection.honeypot_fields'), 'two honeypot signals are configured');
    }

    // ── Fill time ───────────────────────────────────────────────────────────

    public function test_submit_faster_than_the_minimum_fill_time_is_rejected(): void
    {
        foreach ($this->forms() as $form) {
            $justRendered = $this->app->make(FormTimingToken::class)->issue($form, time());

            $this->submit($form, $this->payload($form, [config('form-protection.timing.field') => $justRendered]))
                ->assertSessionHasErrors('captcha');

            $this->assertNothingStoredOrMailed($form);
        }
    }

    public function test_missing_forged_or_expired_fill_time_token_is_rejected(): void
    {
        $field = config('form-protection.timing.field');
        $timing = $this->app->make(FormTimingToken::class);

        foreach ($this->forms() as $form) {
            $cases = [
                'missing' => null,
                'forged' => (time() - 120) . '.' . str_repeat('a', 64),
                'other form' => $timing->issue($form === self::CONTACT ? self::REQUEST : self::CONTACT, time() - 120),
                'expired' => $timing->issue($form, time() - 13 * 3600),
                'garbage' => 'now',
            ];

            foreach ($cases as $label => $value) {
                $payload = $this->payload($form);
                unset($payload[$field]);

                if ($value !== null) {
                    $payload[$field] = $value;
                }

                $this->submit($form, $payload)->assertSessionHasErrors('captcha');
                $this->assertNothingStoredOrMailed($form);
            }
        }
    }

    public function test_slow_visitors_and_a_re_rendered_form_keep_working(): void
    {
        // Eleven hours on the page is still fine.
        $this->submit(self::CONTACT, $this->payload(self::CONTACT, [
            config('form-protection.timing.field') => $this->timingToken(self::CONTACT, 11 * 3600),
        ]))->assertSessionHasNoErrors();

        $this->assertDatabaseCount('contact_submissions', 1);
    }

    // ── Rate limiting ───────────────────────────────────────────────────────

    public function test_ip_daily_limit_blocks_further_submissions_and_mail(): void
    {
        foreach ($this->forms() as $form) {
            $limit = (int) config("form-protection.forms.{$form}.daily_limit");

            for ($i = 1; $i <= $limit; $i++) {
                $this->submit($form, $this->distinctPayload($form, $i))->assertSessionHasNoErrors();
            }

            $callsBefore = $this->mailTransport->calls();
            $this->assertSame($limit * 2, $callsBefore);

            $this->submit($form, $this->distinctPayload($form, 99))->assertSessionHasErrors('rate_limit');

            $this->assertDatabaseCount($this->table($form), $limit);
            $this->assertProviderCalls($callsBefore, "{$form}: nothing mailed above the limit");

            $this->useSpyMailTransport();
        }
    }

    public function test_email_daily_limit_protects_a_victim_address_across_ips_and_spellings(): void
    {
        foreach ($this->forms() as $form) {
            $limit = (int) config("form-protection.forms.{$form}.email_daily_limit");
            $spellings = ['victim@gmail.com', 'Victim@Gmail.com', 'vic.tim+promo@googlemail.com', 'v.i.c.t.i.m@gmail.com'];
            $this->assertGreaterThan($limit, count($spellings));

            for ($i = 0; $i < $limit; $i++) {
                $this->submit($form, $this->distinctPayload($form, $i, [$this->emailField($form) => $spellings[$i]]), [
                    'REMOTE_ADDR' => "203.0.113.{$i}",
                ])->assertSessionHasNoErrors();
            }

            $callsBefore = $this->mailTransport->calls();

            $this->submit($form, $this->distinctPayload($form, 50, [$this->emailField($form) => $spellings[$limit]]))
                ->assertSessionHasErrors($this->emailField($form));

            $this->assertDatabaseCount($this->table($form), $limit);
            $this->assertProviderCalls($callsBefore);

            $this->useSpyMailTransport();
        }
    }

    public function test_form_daily_limit_caps_distributed_bots(): void
    {
        config(['form-protection.forms.contact.global_daily_limit' => 2]);

        foreach ([1, 2] as $i) {
            $this->submit(self::CONTACT, $this->distinctPayload(self::CONTACT, $i), ['REMOTE_ADDR' => "198.51.100.{$i}"])
                ->assertSessionHasNoErrors();
        }

        $this->submit(self::CONTACT, $this->distinctPayload(self::CONTACT, 3), ['REMOTE_ADDR' => '198.51.100.3'])
            ->assertSessionHasErrors('rate_limit');

        $this->assertDatabaseCount('contact_submissions', 2);
        $this->assertProviderCalls(4);
    }

    public function test_global_burst_limit_spans_both_forms(): void
    {
        config(['form-protection.accepted_burst_limit_per_10_minutes' => 1]);

        $this->submit(self::CONTACT, $this->payload(self::CONTACT))->assertSessionHasNoErrors();
        $this->submit(self::REQUEST, $this->payload(self::REQUEST), ['REMOTE_ADDR' => '198.51.100.9'])
            ->assertSessionHasErrors('rate_limit');

        $this->assertDatabaseCount('customer_requests', 0);
        $this->assertProviderCalls(2);
    }

    public function test_attempt_flood_gets_a_429_before_validation_or_captcha(): void
    {
        config(['form-protection.forms.contact.attempt_limit_per_10_minutes' => 2]);

        $this->submit(self::CONTACT, ['name' => 'x'])->assertSessionHasErrors();
        $this->submit(self::CONTACT, ['name' => 'x'])->assertSessionHasErrors();

        $this->submit(self::CONTACT, $this->payload(self::CONTACT))->assertStatus(429);

        $this->assertSame([], $this->captcha->calls);
        $this->assertNothingStoredOrMailed(self::CONTACT);
    }

    public function test_site_wide_attempt_flood_from_many_ips_gets_a_429(): void
    {
        config(['form-protection.global_attempt_limit_per_10_minutes' => 3]);

        foreach ([1, 2, 3] as $i) {
            $this->submit(self::REQUEST, ['service_category' => 'sanitair'], ['REMOTE_ADDR' => "203.0.113.{$i}"])
                ->assertSessionHasErrors();
        }

        $this->submit(self::REQUEST, $this->payload(self::REQUEST), ['REMOTE_ADDR' => '203.0.113.200'])
            ->assertStatus(429);

        $this->assertNothingStoredOrMailed(self::REQUEST);
    }

    public function test_spoofed_proxy_headers_do_not_reset_any_ip_limiter(): void
    {
        $limit = (int) config('form-protection.forms.request.daily_limit');
        $headerSets = [
            fn (int $i) => ['X-Forwarded-For' => "203.0.113.{$i}"],
            fn (int $i) => ['Forwarded' => "for=203.0.113.{$i};proto=https"],
            fn (int $i) => ['X-Real-IP' => "203.0.113.{$i}"],
            fn (int $i) => ['X-Forwarded-For' => "203.0.113.{$i}, 10.0.0.{$i}", 'X-Real-IP' => "198.51.100.{$i}", 'Forwarded' => "for=192.0.2.{$i}"],
        ];

        $i = 0;

        while ($i < $limit) {
            $headers = $headerSets[$i % count($headerSets)]($i);
            $this->submit(self::REQUEST, $this->distinctPayload(self::REQUEST, $i), $headers)->assertSessionHasNoErrors();
            $i++;
        }

        foreach ($headerSets as $set) {
            $this->submit(self::REQUEST, $this->distinctPayload(self::REQUEST, 100 + $i++), $set(250))
                ->assertSessionHasErrors('rate_limit');
        }

        $this->assertDatabaseCount('customer_requests', $limit);
        $this->assertProviderCalls($limit * 2);
    }

    // ── Idempotency + fingerprint ───────────────────────────────────────────

    public function test_duplicate_submission_token_never_sends_twice(): void
    {
        foreach ($this->forms() as $form) {
            $this->useSpyMailTransport();
            $payload = $this->payload($form, ['submission_token' => "token-{$form}-1111"]);

            $this->submit($form, $payload)->assertSessionHas('success', $this->successKey($form));
            $this->submit($form, $payload)->assertSessionHas('success', $this->successKey($form));
            $this->submit($form, $payload)->assertSessionHas('success', $this->successKey($form));

            $this->assertDatabaseCount($this->table($form), 1);
            $this->assertProviderCalls(2, "{$form}: one admin + one customer mail for three identical POSTs");
        }
    }

    public function test_identical_submission_with_a_fresh_token_is_rejected_as_duplicate(): void
    {
        foreach ($this->forms() as $form) {
            $this->useSpyMailTransport();

            $this->submit($form, $this->payload($form, ['submission_token' => "fresh-{$form}-a"]))->assertSessionHasNoErrors();
            $second = $this->submit($form, $this->payload($form, ['submission_token' => "fresh-{$form}-b"]));

            $second->assertSessionHasErrors('captcha');
            $this->assertStringContainsString('zonet al verstuurd', $second->getSession()->get('errors')->first('captcha'));
            $this->assertDatabaseCount($this->table($form), 1);
            $this->assertProviderCalls(2);
        }
    }

    public function test_same_message_body_from_rotating_addresses_is_capped(): void
    {
        $limit = (int) config('form-protection.fingerprint.content_limit');

        for ($i = 1; $i <= $limit; $i++) {
            $this->submit(self::CONTACT, $this->payload(self::CONTACT, [
                'email' => "bot{$i}@example.com",
                'submission_token' => "campaign-{$i}",
            ]), ['REMOTE_ADDR' => "203.0.113.{$i}"])->assertSessionHasNoErrors();
        }

        $this->submit(self::CONTACT, $this->payload(self::CONTACT, [
            'email' => 'bot99@example.com',
            'submission_token' => 'campaign-99',
        ]), ['REMOTE_ADDR' => '203.0.113.99'])->assertSessionHasErrors('captcha');

        $this->assertDatabaseCount('contact_submissions', $limit);
        $this->assertProviderCalls($limit * 2);
    }

    // ── Mail budget / circuit breaker ───────────────────────────────────────

    public function test_customer_confirmation_budget_reached_keeps_the_request_but_skips_the_customer_mail(): void
    {
        config(['form-protection.mail.customer_confirmation_daily_limit' => 1]);

        $this->submit(self::REQUEST, $this->distinctPayload(self::REQUEST, 1))->assertSessionHasNoErrors();
        $this->assertProviderCalls(2);

        $this->submit(self::REQUEST, $this->distinctPayload(self::REQUEST, 2))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'request_created');

        $this->assertDatabaseCount('customer_requests', 2);
        $this->assertProviderCalls(3, 'second request: admin mail only');
        $this->assertSame('martin@mastechnics.be', $this->mailTransport->sent[2]['to'][0]);
        $this->assertDatabaseHas('mail_logs', [
            'status' => 'skipped',
            'error' => 'mail_budget_customer',
            'recipient' => 'klant2@example.com',
        ]);
    }

    public function test_admin_notification_budget_is_separate_from_the_customer_budget(): void
    {
        config(['form-protection.mail.admin_notification_daily_limit' => 1]);

        $this->submit(self::CONTACT, $this->distinctPayload(self::CONTACT, 1))->assertSessionHasNoErrors();
        $this->submit(self::CONTACT, $this->distinctPayload(self::CONTACT, 2))->assertSessionHasNoErrors();

        $this->assertDatabaseCount('contact_submissions', 2);
        $this->assertProviderCalls(3, 'second contact: customer confirmation only');
        $this->assertSame(['klant2@example.com'], $this->mailTransport->sent[2]['to']);
        $this->assertDatabaseHas('mail_logs', ['status' => 'skipped', 'error' => 'mail_budget_admin']);
    }

    public function test_form_mail_burst_budget_stops_all_form_mail_but_never_the_storage(): void
    {
        config(['form-protection.mail.burst_limit_per_hour' => 2]);

        $this->submit(self::CONTACT, $this->distinctPayload(self::CONTACT, 1))->assertSessionHasNoErrors();
        $this->submit(self::REQUEST, $this->distinctPayload(self::REQUEST, 2))->assertSessionHasNoErrors();

        $this->assertDatabaseCount('contact_submissions', 1);
        $this->assertDatabaseCount('customer_requests', 1);
        $this->assertProviderCalls(2);
        $this->assertSame(2, \App\Models\MailLog::where('status', 'skipped')->where('error', 'mail_budget_burst')->count());
    }

    public function test_mail_guard_can_be_disabled_by_config(): void
    {
        config([
            'form-protection.mail.customer_confirmation_daily_limit' => 0,
            'form-protection.mail.guard_enabled' => false,
        ]);
        $this->app->forgetInstance(\App\Services\Spam\MailBudget::class);

        $this->submit(self::CONTACT, $this->payload(self::CONTACT))->assertSessionHasNoErrors();

        $this->assertProviderCalls(2);
    }

    // ── Kill switches ───────────────────────────────────────────────────────

    public function test_customer_confirmation_kill_switch_keeps_admin_mail_and_storage(): void
    {
        config(['form-protection.mail.customer_confirmation_enabled' => false]);

        foreach ($this->forms() as $form) {
            $this->useSpyMailTransport();

            $this->submit($form, $this->payload($form))->assertSessionHas('success', $this->successKey($form));

            $this->assertDatabaseCount($this->table($form), 1);
            $this->assertProviderCalls(1, "{$form}: admin mail only");
            $this->assertSame(['martin@mastechnics.be'], $this->mailTransport->recipients());
        }

        $this->assertSame(2, \App\Models\MailLog::where('status', 'skipped')->where('error', 'mail_customer_disabled')->count());
    }

    public function test_form_kill_switch_refuses_posts_and_hides_the_form_without_a_500(): void
    {
        $this->seed(PageSeeder::class);
        config([
            'form-protection.forms.contact.enabled' => false,
            'form-protection.forms.request.enabled' => false,
        ]);

        foreach ($this->forms() as $form) {
            $this->submit($form, $this->payload($form))
                ->assertStatus(302)
                ->assertSessionHasErrors('form_disabled');

            $this->assertNothingStoredOrMailed($form);
        }

        $contact = $this->get(route('pages.show', ['locale' => 'nl', 'slug' => 'contact']))->assertOk();
        $contact->assertSee('Formulier tijdelijk niet beschikbaar');
        $contact->assertDontSee('id="contactForm"', false);

        $request = $this->get(route('pages.show', ['locale' => 'fr', 'slug' => config('site.page_slugs.request.fr')]))->assertOk();
        $request->assertSee('Formulaire temporairement indisponible');
        $request->assertDontSee('id="requestWizardForm"', false);
    }

    // ── Failure modes ───────────────────────────────────────────────────────

    public function test_transport_failure_never_loses_the_stored_submission(): void
    {
        $this->mailTransport->failWith = new \RuntimeException('SMTP unavailable');

        foreach ($this->forms() as $form) {
            $this->submit($form, $this->payload($form))
                ->assertSessionHasNoErrors()
                ->assertSessionHas('success', $this->successKey($form));

            $this->assertDatabaseCount($this->table($form), 1);
        }

        $this->assertProviderCalls(4, 'both mails were attempted for both forms');
        $this->assertSame(4, \App\Models\MailLog::where('status', 'failed')->count());
    }

    public function test_unsafe_recipient_is_never_handed_to_the_transport(): void
    {
        // Validation accepts this; the last-line check before the send does not.
        config(['site.contact_notification_email' => "martin@mastechnics.be\r\nBcc: victim@example.com"]);

        $this->submit(self::CONTACT, $this->payload(self::CONTACT))->assertSessionHasNoErrors();

        $this->assertDatabaseCount('contact_submissions', 1);
        $this->assertProviderCalls(1, 'only the customer confirmation went out');
        $this->assertDatabaseHas('mail_logs', ['status' => 'skipped', 'error' => 'mail_unsafe_recipient']);
    }

    // ── Rendering ───────────────────────────────────────────────────────────

    public function test_forms_render_the_captcha_widget_timing_field_and_both_honeypots(): void
    {
        $this->seed(PageSeeder::class);

        $contact = $this->get(route('pages.show', ['locale' => 'nl', 'slug' => 'contact']))->assertOk()->getContent();
        $request = $this->get(route('pages.show', ['locale' => 'nl', 'slug' => config('site.page_slugs.request.nl')]))->assertOk()->getContent();

        foreach ([$contact, $request] as $html) {
            $this->assertSame(1, substr_count($html, 'name="' . config('form-protection.timing.field') . '"'));
            $this->assertSame(1, substr_count($html, 'class="mt-captcha"'));
            $this->assertStringContainsString('data-provider="turnstile"', $html);
            $this->assertStringContainsString('data-sitekey="fake-site-key"', $html);

            foreach (config('form-protection.honeypot_fields') as $field) {
                $this->assertSame(1, substr_count($html, 'name="' . $field . '"'));
            }
        }

        $this->assertStringContainsString('data-render="auto"', $contact);
        $this->assertStringContainsString('data-render="manual"', $request);
    }

    public function test_captcha_widget_is_absent_when_the_challenge_is_disabled(): void
    {
        $this->seed(PageSeeder::class);
        $this->useFakeCaptcha(enabled: false);

        $html = $this->get(route('pages.show', ['locale' => 'nl', 'slug' => 'contact']))->assertOk()->getContent();

        $this->assertStringNotContainsString('mt-captcha', $html);

        // …and a submission without any captcha token is then accepted.
        $this->submit(self::CONTACT, $this->payload(self::CONTACT))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('contact_submissions', 1);
    }

    public function test_rejections_are_counted_for_the_admin_dashboard(): void
    {
        $this->submit(self::CONTACT, $this->payload(self::CONTACT, [$this->captcha->responseField() => 'bad']));
        $this->submit(self::CONTACT, $this->payload(self::CONTACT, ['website_url' => 'x']));

        $summary = $this->app->make(\App\Services\Spam\FormProtectionLog::class)->summary(1);

        $this->assertSame(1, $summary['today']['captcha_failed']);
        $this->assertSame(1, $summary['today']['honeypot']);
        $this->assertSame(2, $summary['today_total']);

        $this->withSession($this->adminSession())
            ->get(route('admin.requests.index'))
            ->assertOk()
            ->assertSee('Spam tegengehouden vandaag');
    }
}
