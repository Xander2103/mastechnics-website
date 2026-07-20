<?php

namespace Tests\Feature;

use App\Mail\ContactMessageConfirmationMail;
use App\Mail\ContactMessageMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ContactFormTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name'    => 'Jan Janssens',
            'email'   => 'jan@example.com',
            'phone'   => '+32 495 12 34 56',
            'subject' => 'Vraag over onderhoud',
            'message' => 'Kunnen jullie mijn ketel nakijken?',
        ], $overrides);
    }

    public function test_valid_contact_message_is_accepted(): void
    {
        $response = $this->post(route('contact.store', ['locale' => 'nl']), $this->validPayload());

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();
        $response->assertSessionHas('success', 'contact_message_sent');
    }

    public function test_martin_receives_notification_mail(): void
    {
        $this->post(route('contact.store', ['locale' => 'nl']), $this->validPayload());

        Mail::assertSent(ContactMessageMail::class, function (ContactMessageMail $mail) {
            return $mail->hasTo(config('site.contact_notification_email'));
        });
    }

    public function test_notification_mail_never_goes_to_old_placeholder_address(): void
    {
        $this->post(route('contact.store', ['locale' => 'nl']), $this->validPayload());

        Mail::assertSent(ContactMessageMail::class, function (ContactMessageMail $mail) {
            return ! $mail->hasTo('hello@example.com');
        });
    }

    public function test_confirmation_mail_is_sent_to_visitor(): void
    {
        $this->post(
            route('contact.store', ['locale' => 'nl']),
            $this->validPayload(['email' => 'klant@example.com'])
        );

        Mail::assertSent(ContactMessageConfirmationMail::class, function (ContactMessageConfirmationMail $mail) {
            return $mail->hasTo('klant@example.com');
        });
    }

    public function test_martin_mail_uses_visitor_email_as_reply_to(): void
    {
        $this->post(
            route('contact.store', ['locale' => 'nl']),
            $this->validPayload(['email' => 'klant@example.com'])
        );

        Mail::assertSent(ContactMessageMail::class, function (ContactMessageMail $mail) {
            return $mail->envelope()->replyTo[0]->address === 'klant@example.com';
        });
    }

    public function test_martin_mail_subject_includes_site_name_and_customer_name(): void
    {
        $this->post(
            route('contact.store', ['locale' => 'nl']),
            $this->validPayload(['name' => 'Jan Janssens'])
        );

        Mail::assertSent(ContactMessageMail::class, function (ContactMessageMail $mail) {
            return $mail->envelope()->subject === 'Nieuwe contactaanvraag via Mastechnics — Jan Janssens';
        });
    }

    public function test_no_mail_is_sent_when_required_fields_are_missing(): void
    {
        $payload = $this->validPayload();
        unset($payload['message']);

        $response = $this->post(route('contact.store', ['locale' => 'nl']), $payload);

        $response->assertSessionHasErrors('message');
        Mail::assertNothingSent();
    }

    public function test_name_is_required(): void
    {
        $payload = $this->validPayload();
        unset($payload['name']);

        $this->post(route('contact.store', ['locale' => 'nl']), $payload)
            ->assertSessionHasErrors('name');
    }

    public function test_email_is_required(): void
    {
        $payload = $this->validPayload();
        unset($payload['email']);

        $this->post(route('contact.store', ['locale' => 'nl']), $payload)
            ->assertSessionHasErrors('email');
    }

    public function test_invalid_email_is_rejected(): void
    {
        $response = $this->post(
            route('contact.store', ['locale' => 'nl']),
            $this->validPayload(['email' => 'not-an-email'])
        );

        $response->assertSessionHasErrors('email');
        Mail::assertNothingSent();
    }

    public function test_message_is_required(): void
    {
        $payload = $this->validPayload();
        unset($payload['message']);

        $this->post(route('contact.store', ['locale' => 'nl']), $payload)
            ->assertSessionHasErrors('message');
    }

    public function test_subject_falls_back_to_default_when_left_empty(): void
    {
        $payload = $this->validPayload();
        unset($payload['subject']);

        $this->post(route('contact.store', ['locale' => 'nl']), $payload)
            ->assertSessionHasNoErrors();

        // The envelope subject is now always "... — {customer name}"; the
        // fallback default text lives in the mail body instead.
        Mail::assertSent(ContactMessageMail::class, function (ContactMessageMail $mail) {
            return $mail->data['subject'] === 'Contactaanvraag via website';
        });
    }

    public function test_dutch_confirmation_template_is_used_for_nl_locale(): void
    {
        $this->post(route('contact.store', ['locale' => 'nl']), $this->validPayload());

        Mail::assertSent(ContactMessageConfirmationMail::class, function (ContactMessageConfirmationMail $mail) {
            return str_contains($mail->envelope()->subject, 'We hebben uw bericht goed ontvangen');
        });
    }

    public function test_french_confirmation_template_is_used_for_fr_locale(): void
    {
        $this->post(route('contact.store', ['locale' => 'fr']), $this->validPayload());

        Mail::assertSent(ContactMessageConfirmationMail::class, function (ContactMessageConfirmationMail $mail) {
            return str_contains($mail->envelope()->subject, 'Nous avons bien reçu votre message');
        });
    }

    public function test_english_confirmation_template_is_used_for_en_locale(): void
    {
        $this->post(route('contact.store', ['locale' => 'en']), $this->validPayload());

        Mail::assertSent(ContactMessageConfirmationMail::class, function (ContactMessageConfirmationMail $mail) {
            return str_contains($mail->envelope()->subject, 'We have received your message');
        });
    }

    public function test_rate_limit_blocks_after_daily_limit_is_reached(): void
    {
        $limit = (int) config('site.contact_daily_limit', 10);

        for ($i = 1; $i <= $limit; $i++) {
            $response = $this->post(
                route('contact.store', ['locale' => 'nl']),
                $this->validPayload(['email' => "jan{$i}@example.com"])
            );

            $response->assertSessionHasNoErrors();
        }

        $blocked = $this->post(
            route('contact.store', ['locale' => 'nl']),
            $this->validPayload(['email' => 'jan-extra@example.com'])
        );

        $blocked->assertSessionHasErrors('rate_limit');
    }

    public function test_mail_failure_still_returns_success_response(): void
    {
        Mail::shouldReceive('to')->andReturnSelf();
        Mail::shouldReceive('send')->andThrow(new \RuntimeException('SMTP unavailable'));

        $response = $this->post(route('contact.store', ['locale' => 'nl']), $this->validPayload());

        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('success', 'contact_message_sent');
    }

    /**
     * Regression test for the reported production 500: mail_logs existed as
     * a migration but had never actually been applied to the database, so
     * MailLog::create() threw "no such table: mail_logs" right after a
     * successful send, and that exception was not caught — it propagated
     * all the way out of the request and returned HTTP 500 to the visitor.
     */
    public function test_submission_succeeds_even_when_mail_log_table_is_missing(): void
    {
        Schema::dropIfExists('mail_logs');

        $response = $this->post(route('contact.store', ['locale' => 'nl']), $this->validPayload());

        $response->assertStatus(302);
        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('success', 'contact_message_sent');

        Mail::assertSent(ContactMessageMail::class);
        Mail::assertSent(ContactMessageConfirmationMail::class);
    }

    public function test_contact_page_no_longer_uses_mailto_form_submission(): void
    {
        $this->seed(\Database\Seeders\PageSeeder::class);

        $response = $this->get(route('pages.show', ['locale' => 'nl', 'slug' => 'contact']));

        $response->assertOk();
        $response->assertDontSee('data-mailto', false);
        $response->assertSee('method="POST"', false);
        $response->assertSee('action="' . route('contact.store', ['locale' => 'nl']) . '"', false);
    }

    public function test_contact_page_renders_each_field_and_form_exactly_once(): void
    {
        $this->seed(\Database\Seeders\PageSeeder::class);

        $html = $this->get(route('pages.show', ['locale' => 'nl', 'slug' => 'contact']))
            ->assertOk()
            ->getContent();

        $this->assertSame(1, substr_count($html, 'id="contactForm"'));
        $this->assertSame(1, substr_count($html, 'name="name"'));
        $this->assertSame(1, substr_count($html, 'name="email"'));
        $this->assertSame(1, substr_count($html, 'name="phone"'));
        $this->assertSame(1, substr_count($html, 'name="subject"'));
        $this->assertSame(1, substr_count($html, 'name="message"'));
        $this->assertSame(1, substr_count($html, 'id="contactSubmitBtn"'));
        $this->assertSame(1, substr_count($html, '<form'));
        $this->assertSame(1, substr_count($html, '</form>'));
    }

    public function test_contact_page_renders_a_hidden_submission_token(): void
    {
        $this->seed(\Database\Seeders\PageSeeder::class);

        $html = $this->get(route('pages.show', ['locale' => 'nl', 'slug' => 'contact']))
            ->assertOk()
            ->getContent();

        $this->assertSame(1, substr_count($html, 'name="submission_token"'));
    }

    /**
     * Regression test for the reported bug: Martin received the same
     * contact email many times because a retried/duplicated POST (double
     * click, refresh, client retry) sent mail again each time. The hidden
     * submission_token is identical across such retries, and the unique
     * index on contact_submissions.token means only the first insert wins.
     */
    public function test_double_submission_with_same_token_sends_mail_only_once(): void
    {
        $payload = $this->validPayload(['submission_token' => 'repeat-token-123']);

        $first = $this->post(route('contact.store', ['locale' => 'nl']), $payload);
        $second = $this->post(route('contact.store', ['locale' => 'nl']), $payload);

        $first->assertSessionHas('success', 'contact_message_sent');
        $second->assertSessionHas('success', 'contact_message_sent');

        Mail::assertSentTimes(ContactMessageMail::class, 1);
        Mail::assertSentTimes(ContactMessageConfirmationMail::class, 1);

        $this->assertDatabaseCount('contact_submissions', 1);
    }

    public function test_three_rapid_duplicate_submissions_still_send_mail_only_once(): void
    {
        $payload = $this->validPayload(['submission_token' => 'repeat-token-456']);

        $this->post(route('contact.store', ['locale' => 'nl']), $payload);
        $this->post(route('contact.store', ['locale' => 'nl']), $payload);
        $this->post(route('contact.store', ['locale' => 'nl']), $payload);

        Mail::assertSentTimes(ContactMessageMail::class, 1);
        Mail::assertSentTimes(ContactMessageConfirmationMail::class, 1);
    }

    public function test_different_tokens_each_send_their_own_mail(): void
    {
        $this->post(route('contact.store', ['locale' => 'nl']), $this->validPayload([
            'submission_token' => 'token-a',
            'email' => 'a@example.com',
        ]));
        $this->post(route('contact.store', ['locale' => 'nl']), $this->validPayload([
            'submission_token' => 'token-b',
            'email' => 'b@example.com',
        ]));

        Mail::assertSentTimes(ContactMessageMail::class, 2);
        Mail::assertSentTimes(ContactMessageConfirmationMail::class, 2);
        $this->assertDatabaseCount('contact_submissions', 2);
    }

    public function test_submission_without_token_still_sends_mail_once(): void
    {
        // A direct POST that never loaded the GET form (no JS, a bot, an
        // API client) carries no submission_token — that must not break
        // the flow, it's simply always a "new" submission.
        $payload = $this->validPayload();
        unset($payload['submission_token']);

        $response = $this->post(route('contact.store', ['locale' => 'nl']), $payload);

        $response->assertSessionHas('success', 'contact_message_sent');
        Mail::assertSentTimes(ContactMessageMail::class, 1);
        Mail::assertSentTimes(ContactMessageConfirmationMail::class, 1);
    }

    public function test_successful_submission_is_stored_with_mail_sent_at(): void
    {
        $this->post(route('contact.store', ['locale' => 'nl']), $this->validPayload([
            'submission_token' => 'stored-token',
        ]));

        $this->assertDatabaseHas('contact_submissions', [
            'token' => 'stored-token',
            'name'  => 'Jan Janssens',
            'email' => 'jan@example.com',
        ]);

        $submission = \App\Models\ContactSubmission::where('token', 'stored-token')->first();
        $this->assertNotNull($submission->mail_sent_at);
    }

    public function test_duplicate_submission_does_not_consume_extra_rate_limit_quota(): void
    {
        $limit = (int) config('site.contact_daily_limit', 10);
        $payload = $this->validPayload(['submission_token' => 'quota-token']);

        // Submit the same token (limit - 1) + 1 extra duplicate times — if
        // duplicates consumed quota this would trip the limiter early.
        for ($i = 0; $i < $limit - 1; $i++) {
            $this->post(
                route('contact.store', ['locale' => 'nl']),
                $this->validPayload(['email' => "unique{$i}@example.com"])
            )->assertSessionHasNoErrors();
        }

        // Two duplicate posts of the same already-claimed token.
        $this->post(route('contact.store', ['locale' => 'nl']), $payload)->assertSessionHasNoErrors();
        $dup = $this->post(route('contact.store', ['locale' => 'nl']), $payload);

        $dup->assertSessionHasNoErrors();
        $dup->assertSessionDoesntHaveErrors('rate_limit');
    }

    public function test_admin_and_customer_subjects_are_clearly_different_even_for_the_same_inbox(): void
    {
        config(['site.contact_notification_email' => 'martin@mastechnics.be']);

        // Visitor enters Martin's own address as their contact email.
        $this->post(route('contact.store', ['locale' => 'nl']), $this->validPayload([
            'name'  => 'Jan Janssens',
            'email' => 'martin@mastechnics.be',
        ]));

        Mail::assertSentTimes(ContactMessageMail::class, 1);
        Mail::assertSentTimes(ContactMessageConfirmationMail::class, 1);

        Mail::assertSent(ContactMessageMail::class, function (ContactMessageMail $mail) {
            return $mail->hasTo('martin@mastechnics.be')
                && $mail->envelope()->subject === 'Nieuwe contactaanvraag via Mastechnics — Jan Janssens';
        });

        Mail::assertSent(ContactMessageConfirmationMail::class, function (ContactMessageConfirmationMail $mail) {
            return $mail->hasTo('martin@mastechnics.be')
                && $mail->envelope()->subject === 'We hebben uw bericht goed ontvangen — Mastechnics';
        });
    }

    public function test_neither_mailable_overrides_the_configured_sender(): void
    {
        // Regression for the "${Mastechnics}" sender-name bug: both
        // Mailables must rely on config('mail.from'), never set their own
        // envelope 'from', so a bad literal can't be introduced here again.
        $this->post(route('contact.store', ['locale' => 'nl']), $this->validPayload());

        Mail::assertSent(ContactMessageMail::class, function (ContactMessageMail $mail) {
            return $mail->envelope()->from === null;
        });

        Mail::assertSent(ContactMessageConfirmationMail::class, function (ContactMessageConfirmationMail $mail) {
            return $mail->envelope()->from === null;
        });
    }

    public function test_configured_mail_from_name_is_not_a_malformed_literal(): void
    {
        $fromName = config('mail.from.name');

        $this->assertIsString($fromName);
        $this->assertNotEmpty($fromName);
        $this->assertStringNotContainsString('${', $fromName);
    }

    /**
     * The new idempotency table must never become another instance of the
     * mail_logs bug: if contact_submissions is unavailable, the form still
     * has to work (mail sends once, just without dedup tracking) instead
     * of 500ing.
     */
    public function test_submission_still_succeeds_when_contact_submissions_table_is_missing(): void
    {
        Schema::dropIfExists('contact_submissions');

        $response = $this->post(route('contact.store', ['locale' => 'nl']), $this->validPayload());

        $response->assertStatus(302);
        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('success', 'contact_message_sent');

        Mail::assertSentTimes(ContactMessageMail::class, 1);
        Mail::assertSentTimes(ContactMessageConfirmationMail::class, 1);
    }
}
