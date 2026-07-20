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

    public function test_martin_mail_subject_includes_site_name_and_subject(): void
    {
        $this->post(
            route('contact.store', ['locale' => 'nl']),
            $this->validPayload(['subject' => 'Offerte airco'])
        );

        Mail::assertSent(ContactMessageMail::class, function (ContactMessageMail $mail) {
            return str_contains($mail->envelope()->subject, 'Mastechnics')
                && str_contains($mail->envelope()->subject, 'Offerte airco');
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

        Mail::assertSent(ContactMessageMail::class, function (ContactMessageMail $mail) {
            return str_contains($mail->envelope()->subject, 'Contactaanvraag via website');
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
}
