<?php

namespace Tests\Feature\Admin;

use App\Models\ContactSubmission;
use App\Models\CustomerRequest;
use App\Models\FormSecurityEvent;
use App\Services\Spam\Trust\TrustDecision;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * The admin security monitor: dashboard tile, security-log page (filters,
 * pagination, links to the stored row), access control, and the privacy
 * promise that the page shows hashes and masked addresses only.
 */
class SecurityLogTest extends TestCase
{
    use RefreshDatabase;

    private function event(array $attrs = []): FormSecurityEvent
    {
        return FormSecurityEvent::create(array_merge([
            'occurred_at' => now(),
            'form' => 'contact',
            'decision' => TrustDecision::BLOCKED,
            'risk_score' => 0,
            'risk_level' => 'high',
            'reasons' => ['honeypot'],
            'signals' => ['timing' => ['honeypot']],
            'captcha_status' => 'passed',
            'captcha_hostname_ok' => true,
            'captcha_action_ok' => true,
            'fill_time_bucket' => 'normal',
            'fill_time_seconds' => 45,
            'mail_admin' => 'not_applicable',
            'mail_customer' => 'not_applicable',
            'mails_prevented' => 1,
            'ip_hash' => 'abcdef0123456789',
            'email_masked' => 'sp***@example.com',
            'email_hash' => '0123456789abcdef',
            'locale' => 'nl',
            'user_agent_family' => 'curl',
        ], $attrs));
    }

    private function request(array $attrs = []): CustomerRequest
    {
        return CustomerRequest::create(array_merge([
            'locale' => 'nl',
            'service_slug' => 'heating',
            'request_type' => 'repair',
            'customer_name' => 'Twijfel Klant',
            'customer_email' => 'twijfel@example.com',
            'description' => 'Test aanvraag',
            'status' => 'new',
            'trust_verdict' => TrustDecision::NEEDS_REVIEW,
            'trust_score' => 5,
            'trust_reasons' => ['ua_non_browser'],
        ], $attrs));
    }

    private function contact(array $attrs = []): ContactSubmission
    {
        return ContactSubmission::create(array_merge([
            'token' => 'tok-' . uniqid(),
            'name' => 'Twijfel Contact',
            'email' => 'contact@example.com',
            'subject' => 'Vraag',
            'message' => 'Een bericht.',
            'locale' => 'nl',
            'trust_verdict' => TrustDecision::NEEDS_REVIEW,
            'trust_score' => 4,
            'trust_reasons' => ['fill_time_fast'],
        ], $attrs));
    }

    // ── Access ──────────────────────────────────────────────────────────────

    public function test_guest_is_redirected_to_login_for_every_new_route(): void
    {
        $request = $this->request();
        $contact = $this->contact();

        $this->get(route('admin.security.index'))->assertRedirect(route('admin.login'));
        $this->get(route('admin.contact-submissions.index'))->assertRedirect(route('admin.login'));
        $this->get(route('admin.contact-submissions.show', $contact))->assertRedirect(route('admin.login'));
        $this->post(route('admin.contact-submissions.trust', $contact), ['action' => 'release'])->assertRedirect(route('admin.login'));
        $this->post(route('admin.requests.trust', $request), ['action' => 'release'])->assertRedirect(route('admin.login'));

        $this->assertSame(TrustDecision::NEEDS_REVIEW, $request->fresh()->trust_verdict);
        $this->assertSame(TrustDecision::NEEDS_REVIEW, $contact->fresh()->trust_verdict);
    }

    // ── Log page ────────────────────────────────────────────────────────────

    public function test_log_lists_blocked_needs_review_and_trusted_events_with_labels(): void
    {
        $this->event(['decision' => TrustDecision::BLOCKED, 'reasons' => ['honeypot']]);
        $this->event(['decision' => TrustDecision::NEEDS_REVIEW, 'form' => 'request', 'reasons' => ['ua_non_browser'], 'mail_admin' => 'skipped', 'mail_reason' => 'mail_not_trusted']);
        $this->event(['decision' => TrustDecision::TRUSTED, 'reasons' => [], 'mail_admin' => 'sent', 'mail_customer' => 'skipped', 'mail_reason' => 'mail_customer_disabled']);

        $this->withSession($this->adminSession())
            ->get(route('admin.security.index'))
            ->assertOk()
            ->assertSee('Beveiligingslog')
            ->assertSee('Geblokkeerd')
            ->assertSee('Te controleren')
            ->assertSee('Vertrouwd')
            ->assertSee('Honeypot ingevuld')
            ->assertSee('Geen browser (script/tool)')
            ->assertSee('Mail overgeslagen: niet vertrouwd')
            ->assertSee('Klantmail uitgeschakeld')
            ->assertSee('bewust overgeslagen')
            ->assertSee('Aanvraag')
            ->assertSee('Contact');
    }

    public function test_zero_mail_decisions_are_visible_and_addresses_are_masked(): void
    {
        $this->event(['decision' => TrustDecision::NEEDS_REVIEW, 'mail_admin' => 'skipped', 'mail_customer' => 'skipped', 'mail_reason' => 'mail_not_trusted', 'email_masked' => 'xa***@gmail.com']);

        $html = $this->withSession($this->adminSession())
            ->get(route('admin.security.index'))
            ->assertOk()
            ->assertSee('xa***@gmail.com')
            ->assertSee('abcdef0123456789')
            ->getContent();

        $this->assertStringNotContainsString('xander@gmail.com', $html);
        $this->assertStringNotContainsString('@gmail.com"', $html);
    }

    public function test_filters_narrow_the_log(): void
    {
        $this->event(['decision' => TrustDecision::BLOCKED, 'form' => 'contact', 'reasons' => ['honeypot'], 'email_masked' => 'bl***@example.com']);
        $this->event(['decision' => TrustDecision::NEEDS_REVIEW, 'form' => 'request', 'reasons' => ['ua_non_browser'], 'mail_admin' => 'skipped', 'mail_reason' => 'mail_not_trusted', 'email_masked' => 're***@example.com']);
        $this->event(['decision' => TrustDecision::TRUSTED, 'form' => 'request', 'reasons' => [], 'mail_admin' => 'sent', 'email_masked' => 'tr***@example.com']);

        $admin = $this->withSession($this->adminSession());

        $admin->get(route('admin.security.index', ['decision' => 'blocked']))
            ->assertOk()->assertSee('bl***@example.com')->assertDontSee('re***@example.com')->assertDontSee('tr***@example.com');

        $admin->get(route('admin.security.index', ['form' => 'request']))
            ->assertOk()->assertDontSee('bl***@example.com')->assertSee('re***@example.com')->assertSee('tr***@example.com');

        $admin->get(route('admin.security.index', ['reason' => 'ua_non_browser']))
            ->assertOk()->assertSee('re***@example.com')->assertDontSee('bl***@example.com')->assertDontSee('tr***@example.com');

        $admin->get(route('admin.security.index', ['mail' => 'skipped']))
            ->assertOk()->assertSee('re***@example.com')->assertDontSee('tr***@example.com')->assertDontSee('bl***@example.com');

        $admin->get(route('admin.security.index', ['mail' => 'sent']))
            ->assertOk()->assertSee('tr***@example.com')->assertDontSee('re***@example.com');

        $admin->get(route('admin.security.index', ['date_from' => now()->addDay()->format('Y-m-d')]))
            ->assertOk()->assertSee('Geen events gevonden');

        $admin->get(route('admin.security.index', ['date_from' => 'not-a-date']))
            ->assertSessionHasErrors('date_from');
    }

    public function test_log_is_paginated_at_25(): void
    {
        for ($i = 1; $i <= 26; $i++) {
            $this->event(['occurred_at' => now()->subMinutes($i), 'email_masked' => sprintf('e%02d***@example.com', $i)]);
        }

        $admin = $this->withSession($this->adminSession());

        $admin->get(route('admin.security.index', ['decision' => 'blocked']))
            ->assertOk()
            ->assertSee('e01***@example.com')
            ->assertSee('e25***@example.com')
            ->assertDontSee('e26***@example.com')
            ->assertSee('page=2');

        $admin->get(route('admin.security.index', ['decision' => 'blocked', 'page' => 2]))
            ->assertOk()
            ->assertSee('e26***@example.com')
            ->assertDontSee('e01***@example.com');
    }

    public function test_needs_review_events_link_to_the_stored_request_and_contact_message(): void
    {
        $request = $this->request();
        $contact = $this->contact();

        $this->event(['decision' => TrustDecision::NEEDS_REVIEW, 'form' => 'request', 'subject_type' => $request->getMorphClass(), 'subject_id' => $request->id]);
        $this->event(['decision' => TrustDecision::NEEDS_REVIEW, 'form' => 'contact', 'subject_type' => $contact->getMorphClass(), 'subject_id' => $contact->id]);

        $this->withSession($this->adminSession())
            ->get(route('admin.security.index'))
            ->assertOk()
            ->assertSee('Open aanvraag')
            ->assertSee(route('admin.requests.show', $request), false)
            ->assertSee('Open bericht')
            ->assertSee(route('admin.contact-submissions.show', $contact), false);

        $this->withSession($this->adminSession())
            ->get(route('admin.requests.show', $request))
            ->assertOk()
            ->assertSee('Te controleren')
            ->assertSee('Geen browser (script/tool)')
            ->assertSee('Vrijgeven en');

        $this->withSession($this->adminSession())
            ->get(route('admin.contact-submissions.show', $contact))
            ->assertOk()
            ->assertSee('Twijfel Contact')
            ->assertSee('Snel ingevuld')
            ->assertSee('Markeer als spam');
    }

    // ── Dashboard tile ──────────────────────────────────────────────────────

    public function test_dashboard_tile_shows_todays_numbers_and_the_log_button(): void
    {
        $this->event(['decision' => TrustDecision::BLOCKED, 'mails_prevented' => 1]);
        $this->event(['decision' => TrustDecision::BLOCKED, 'mails_prevented' => 1]);
        $this->event(['decision' => TrustDecision::NEEDS_REVIEW, 'mails_prevented' => 2]);
        $this->event(['decision' => TrustDecision::TRUSTED, 'mails_prevented' => 0, 'mail_admin' => 'sent']);
        $this->event(['decision' => TrustDecision::BLOCKED, 'occurred_at' => now()->subDays(2), 'mails_prevented' => 9]);

        $html = $this->withSession($this->adminSession())
            ->get(route('admin.requests.index'))
            ->assertOk()
            ->assertSee('Formulierbeveiliging')
            ->assertSee('Geblokkeerd vandaag')
            ->assertSee('Te controleren vandaag')
            ->assertSee('Vertrouwd vandaag')
            ->assertSee('Brevo-mails voorkomen')
            ->assertSee('Externe form-mails vandaag')
            ->assertSee('Bekijk beveiligingslog')
            ->assertSee(route('admin.security.index'), false)
            ->assertSee('Noodrem gesloten')
            ->getContent();

        preg_match('~data-testid="security-tile".*?</div>\s*</div>\s*<div class="admin-security-circuit~s', $html, $tile);
        $this->assertNotEmpty($tile);
        $this->assertStringContainsString('>2<', $tile[0]); // geblokkeerd
        $this->assertStringContainsString('>4<', $tile[0]); // 1 + 1 + 2 mails voorkomen
    }

    public function test_dashboard_tile_shows_the_open_circuit_breaker(): void
    {
        config(['form-protection.mail.external_daily_limit' => 3]);

        for ($i = 0; $i < 3; $i++) {
            RateLimiter::hit('form-protection:mail-circuit:day', 86400);
        }

        $this->withSession($this->adminSession())
            ->get(route('admin.requests.index'))
            ->assertOk()
            ->assertSee('Noodrem actief')
            ->assertSee('Noodrem: daglimiet externe mails')
            ->assertDontSee('Noodrem gesloten');
    }

    public function test_dashboard_tile_shows_attack_indicator_from_deterministic_threshold(): void
    {
        $threshold = (int) config('form-protection.trust.velocity.attack_per_10_minutes');

        for ($i = 0; $i < $threshold - 1; $i++) {
            $this->event(['decision' => TrustDecision::NEEDS_REVIEW]);
        }

        $this->withSession($this->adminSession())
            ->get(route('admin.requests.index'))
            ->assertOk()
            ->assertDontSee('Aanval actief');

        $this->event(['decision' => TrustDecision::BLOCKED]);

        $this->withSession($this->adminSession())
            ->get(route('admin.requests.index'))
            ->assertOk()
            ->assertSee('Aanval actief');
    }

    public function test_dashboard_still_renders_when_the_security_log_table_is_missing(): void
    {
        \Illuminate\Support\Facades\Schema::drop('form_security_events');

        $this->withSession($this->adminSession())
            ->get(route('admin.requests.index'))
            ->assertOk()
            ->assertSee('Formulierbeveiliging');
    }

    public function test_request_list_can_be_filtered_on_trust_verdict(): void
    {
        $this->request(['customer_name' => 'Twijfel Klant']);
        $this->request(['customer_name' => 'Zekere Klant', 'trust_verdict' => TrustDecision::TRUSTED]);

        $this->withSession($this->adminSession())
            ->get(route('admin.requests.index', ['trust' => 'needs_review']))
            ->assertOk()
            ->assertSee('Twijfel Klant')
            ->assertDontSee('Zekere Klant');
    }

    public function test_contact_submissions_index_lists_and_filters(): void
    {
        $this->contact(['name' => 'Twijfel Contact']);
        $this->contact(['name' => 'Zekere Contact', 'trust_verdict' => TrustDecision::TRUSTED]);

        $admin = $this->withSession($this->adminSession());

        $admin->get(route('admin.contact-submissions.index'))
            ->assertOk()->assertSee('Twijfel Contact')->assertSee('Zekere Contact');

        $admin->get(route('admin.contact-submissions.index', ['trust' => 'needs_review']))
            ->assertOk()->assertSee('Twijfel Contact')->assertDontSee('Zekere Contact');
    }
}
