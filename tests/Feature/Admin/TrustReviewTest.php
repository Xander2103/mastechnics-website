<?php

namespace Tests\Feature\Admin;

use App\Models\ContactSubmission;
use App\Models\CustomerRequest;
use App\Models\FormSecurityEvent;
use App\Services\Spam\Trust\TrustDecision;
use App\Services\TrustReviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\InteractsWithFormProtection;
use Tests\TestCase;

/**
 * Manual review of a needs_review submission: releasing sends exactly the
 * expected mails through the same gate (spy transport), marking as spam
 * sends nothing, and both are visible in the security log.
 */
class TrustReviewTest extends TestCase
{
    use InteractsWithFormProtection;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'site.contact_notification_email' => 'martin@mastechnics.be',
            'site.request_notification_email' => 'martin@mastechnics.be',
            'admin.notification_emails' => [],
            'form-protection.mail.external_daily_limit' => 500,
            'form-protection.mail.external_limit_per_10_minutes' => 500,
            'form-protection.mail.burst_limit_per_hour' => 500,
        ]);

        $this->useSpyMailTransport();
    }

    private function request(): CustomerRequest
    {
        return CustomerRequest::create([
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
        ]);
    }

    private function contact(): ContactSubmission
    {
        return ContactSubmission::create([
            'token' => 'tok-' . uniqid(),
            'name' => 'Twijfel Contact',
            'email' => 'contact@example.com',
            'subject' => 'Vraag',
            'message' => 'Een bericht van minstens twintig tekens.',
            'locale' => 'nl',
            'trust_verdict' => TrustDecision::NEEDS_REVIEW,
            'trust_score' => 4,
            'trust_reasons' => ['fill_time_fast'],
        ]);
    }

    public function test_releasing_a_request_sends_admin_and_customer_mail_when_confirmations_are_on(): void
    {
        config(['form-protection.mail.customer_confirmation_enabled' => true]);
        $request = $this->request();

        $this->withSession($this->adminSession('martin@mastechnics.be'))
            ->post(route('admin.requests.trust', $request), ['action' => 'release'])
            ->assertRedirect(route('admin.requests.show', $request))
            ->assertSessionHas('success', 'trust_released');

        $request->refresh();
        $this->assertSame(TrustDecision::TRUSTED, $request->trust_verdict);
        $this->assertSame('martin@mastechnics.be', $request->trust_reviewed_by);
        $this->assertNotNull($request->trust_reviewed_at);

        $this->assertProviderCalls(2);
        $this->assertEqualsCanonicalizing(['martin@mastechnics.be', 'twijfel@example.com'], $this->mailTransport->recipients());

        $event = FormSecurityEvent::latest('id')->first();
        $this->assertSame(TrustDecision::TRUSTED, $event->decision);
        $this->assertSame(['manual_release'], $event->reasons);
        $this->assertSame('sent', $event->mail_admin);
        $this->assertSame('sent', $event->mail_customer);
        $this->assertSame($request->id, $event->subject_id);
    }

    public function test_releasing_sends_only_the_admin_mail_when_customer_confirmations_are_off(): void
    {
        config(['form-protection.mail.customer_confirmation_enabled' => false]);
        $contact = $this->contact();

        $this->withSession($this->adminSession())
            ->post(route('admin.contact-submissions.trust', $contact), ['action' => 'release'])
            ->assertRedirect(route('admin.contact-submissions.show', $contact))
            ->assertSessionHas('success', 'trust_released');

        $this->assertProviderCalls(1);
        $this->assertSame(['martin@mastechnics.be'], $this->mailTransport->recipients());
        $this->assertSame(TrustDecision::TRUSTED, $contact->fresh()->trust_verdict);
        $this->assertNotNull($contact->fresh()->mail_sent_at);

        $event = FormSecurityEvent::latest('id')->first();
        $this->assertSame('contact', $event->form);
        $this->assertSame('skipped', $event->mail_customer);
        $this->assertSame('co***@example.com', $event->email_masked);
    }

    public function test_release_still_respects_the_mail_circuit_breaker(): void
    {
        config(['form-protection.mail.external_daily_limit' => 1]);
        \Illuminate\Support\Facades\RateLimiter::hit('form-protection:mail-circuit:day', 86400);
        $request = $this->request();

        $this->withSession($this->adminSession())
            ->post(route('admin.requests.trust', $request), ['action' => 'release'])
            ->assertSessionHas('success', 'trust_released_no_mail');

        $this->assertProviderCalls(0);
        $this->assertSame(TrustDecision::TRUSTED, $request->fresh()->trust_verdict);
    }

    public function test_marking_as_spam_sends_nothing_and_keeps_the_row(): void
    {
        $request = $this->request();
        $contact = $this->contact();

        $this->withSession($this->adminSession('martin@mastechnics.be'))
            ->post(route('admin.requests.trust', $request), ['action' => 'spam'])
            ->assertSessionHas('success', 'trust_marked_spam');

        $this->withSession($this->adminSession('martin@mastechnics.be'))
            ->post(route('admin.contact-submissions.trust', $contact), ['action' => 'spam'])
            ->assertSessionHas('success', 'trust_marked_spam');

        $this->assertProviderCalls(0);
        $this->assertDatabaseCount('customer_requests', 1);
        $this->assertDatabaseCount('contact_submissions', 1);
        $this->assertSame(TrustReviewService::VERDICT_SPAM, $request->fresh()->trust_verdict);
        $this->assertSame(TrustReviewService::VERDICT_SPAM, $contact->fresh()->trust_verdict);
        $this->assertSame('martin@mastechnics.be', $contact->fresh()->trust_reviewed_by);

        $this->assertSame(2, FormSecurityEvent::where('decision', TrustDecision::BLOCKED)->count());
        $this->assertSame(['manual_spam'], FormSecurityEvent::first()->reasons);

        $this->withSession($this->adminSession())
            ->get(route('admin.requests.show', $request))
            ->assertOk()
            ->assertSee('Als spam gemarkeerd');
    }

    public function test_review_is_refused_when_the_row_is_not_awaiting_review(): void
    {
        $request = $this->request();
        $request->forceFill(['trust_verdict' => TrustDecision::TRUSTED])->save();

        $this->withSession($this->adminSession())
            ->post(route('admin.requests.trust', $request), ['action' => 'release'])
            ->assertSessionHas('success', 'trust_not_reviewable');

        $this->assertProviderCalls(0);
        $this->assertDatabaseCount('form_security_events', 0);

        $this->withSession($this->adminSession())
            ->post(route('admin.requests.trust', $request), ['action' => 'nonsense'])
            ->assertSessionHasErrors('action');
    }
}
