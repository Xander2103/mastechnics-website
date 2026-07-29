<?php

namespace Tests\Feature\Admin;

use App\Mail\StandardReplyMail;
use App\Models\CustomerRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class StandardReplyTest extends TestCase
{
    use RefreshDatabase;

    private function adminSession(): array
    {
        return ['admin_user_email' => 'admin@test.com', 'admin_user_name' => 'Admin'];
    }

    private function makeRequest(array $attrs = []): CustomerRequest
    {
        return CustomerRequest::create(array_merge([
            'locale' => 'nl',
            'service_slug' => 'airco',
            'request_type' => 'installation',
            'customer_name' => 'Test Klant',
            'customer_email' => 'klant@example.com',
            'description' => 'Test aanvraag',
            'status' => 'new',
        ], $attrs));
    }

    public function test_send_sends_mail_marks_sent_and_sets_contacted(): void
    {
        Mail::fake();
        $request = $this->makeRequest();

        $response = $this->withSession($this->adminSession())
            ->post(route('admin.requests.standard-reply.send', $request));

        $response->assertRedirect()->assertSessionHas('success', 'standard_reply_sent');
        Mail::assertSent(StandardReplyMail::class, 1);

        $fresh = $request->fresh();
        $this->assertNotNull($fresh->standard_reply_sent_at);
        $this->assertSame('admin@test.com', $fresh->standard_reply_sent_by);
        $this->assertSame('contacted', $fresh->status);
        $this->assertNotNull($fresh->contacted_at);

        $this->assertDatabaseHas('mail_logs', [
            'customer_request_id' => $request->id,
            'mailable' => 'StandardReplyMail',
            'recipient' => 'klant@example.com',
            'status' => 'sent',
        ]);
    }

    public function test_duplicate_post_sends_exactly_once(): void
    {
        Mail::fake();
        $request = $this->makeRequest();

        $this->withSession($this->adminSession())
            ->post(route('admin.requests.standard-reply.send', $request));
        $this->withSession($this->adminSession())
            ->post(route('admin.requests.standard-reply.send', $request))
            ->assertSessionHas('success', 'standard_reply_already_sent');

        Mail::assertSent(StandardReplyMail::class, 1);
    }

    public function test_send_route_never_resends_when_already_sent(): void
    {
        Mail::fake();
        $sentAt = now()->subDay();
        $request = $this->makeRequest([
            'standard_reply_sent_at' => $sentAt,
            'standard_reply_sent_by' => 'old@test.com',
        ]);

        $this->withSession($this->adminSession())
            ->post(route('admin.requests.standard-reply.send', $request))
            ->assertSessionHas('success', 'standard_reply_already_sent');

        Mail::assertNotSent(StandardReplyMail::class);
        $this->assertSame('old@test.com', $request->fresh()->standard_reply_sent_by);
    }

    public function test_resend_requires_prior_send(): void
    {
        Mail::fake();
        $request = $this->makeRequest();

        $this->withSession($this->adminSession())
            ->post(route('admin.requests.standard-reply.resend', $request))
            ->assertSessionHas('success', 'standard_reply_not_sent_yet');

        Mail::assertNotSent(StandardReplyMail::class);
    }

    public function test_resend_sends_again_and_updates_stamp(): void
    {
        Mail::fake();
        $request = $this->makeRequest([
            'standard_reply_sent_at' => now()->subDay(),
            'standard_reply_sent_by' => 'old@test.com',
            'status' => 'contacted',
            'contacted_at' => now()->subDay(),
        ]);

        $this->withSession($this->adminSession())
            ->post(route('admin.requests.standard-reply.resend', $request))
            ->assertSessionHas('success', 'standard_reply_resent');

        Mail::assertSent(StandardReplyMail::class, 1);

        $fresh = $request->fresh();
        $this->assertTrue($fresh->standard_reply_sent_at->greaterThan(now()->subHour()));
        $this->assertSame('admin@test.com', $fresh->standard_reply_sent_by);
    }

    public function test_failed_mail_does_not_mark_sent_or_contacted(): void
    {
        Mail::shouldReceive('to->send')->andThrow(new \RuntimeException('SMTP down'));
        $request = $this->makeRequest();

        $this->withSession($this->adminSession())
            ->post(route('admin.requests.standard-reply.send', $request))
            ->assertSessionHas('success', 'standard_reply_failed');

        $fresh = $request->fresh();
        $this->assertNull($fresh->standard_reply_sent_at);
        $this->assertNull($fresh->standard_reply_sent_by);
        $this->assertSame('new', $fresh->status);
        $this->assertNull($fresh->contacted_at);

        $this->assertDatabaseHas('mail_logs', [
            'customer_request_id' => $request->id,
            'status' => 'failed',
        ]);
    }

    public function test_mail_log_failure_does_not_cause_second_send(): void
    {
        Mail::fake();
        $request = $this->makeRequest();
        Schema::dropIfExists('mail_logs');

        $this->withSession($this->adminSession())
            ->post(route('admin.requests.standard-reply.send', $request))
            ->assertSessionHas('success', 'standard_reply_sent');

        Mail::assertSent(StandardReplyMail::class, 1);
        $this->assertNotNull($request->fresh()->standard_reply_sent_at);
    }

    public function test_send_does_not_regress_status_beyond_contacted(): void
    {
        Mail::fake();
        $request = $this->makeRequest(['status' => 'won', 'won_at' => now()]);

        $this->withSession($this->adminSession())
            ->post(route('admin.requests.standard-reply.send', $request));

        $fresh = $request->fresh();
        $this->assertSame('won', $fresh->status);
        $this->assertNotNull($fresh->contacted_at);
        $this->assertNotNull($fresh->standard_reply_sent_at);
    }

    public function test_unauthenticated_cannot_send_or_resend(): void
    {
        Mail::fake();
        $request = $this->makeRequest();

        $this->post(route('admin.requests.standard-reply.send', $request))
            ->assertRedirect(route('admin.login'));
        $this->post(route('admin.requests.standard-reply.resend', $request))
            ->assertRedirect(route('admin.login'));

        Mail::assertNothingSent();
        $this->assertNull($request->fresh()->standard_reply_sent_at);
    }

    public function test_show_page_renders_standard_reply_block(): void
    {
        $request = $this->makeRequest();

        $this->withSession($this->adminSession())
            ->get(route('admin.requests.show', $request))
            ->assertOk()
            ->assertSee('Standaardantwoord')
            ->assertSee('Verstuur per e-mail')
            ->assertSee('Dag Test Klant,');
    }
}
