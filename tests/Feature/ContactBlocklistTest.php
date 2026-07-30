<?php

namespace Tests\Feature;

use App\Models\BlockedEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ContactBlocklistTest extends TestCase
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
            'email'   => 'spammer@example.com',
            'phone'   => '+32 495 12 34 56',
            'subject' => 'Vraag',
            'message' => 'Bericht',
        ], $overrides);
    }

    private function block(array $attrs = []): BlockedEmail
    {
        return BlockedEmail::create(array_merge([
            'email' => 'spammer@example.com',
            'reason' => 'Herhaalde spam',
            'blocked_by' => 'admin@test.com',
            'is_active' => true,
            'expires_at' => null,
        ], $attrs));
    }

    public function test_blocked_email_sends_no_mail_and_stores_nothing(): void
    {
        $this->block();

        $response = $this->post(route('contact.store', ['locale' => 'nl']), $this->validPayload());

        $response->assertRedirect()
            ->assertSessionHasErrors('blocked')
            ->assertSessionMissing('success');

        Mail::assertNothingSent();
        $this->assertDatabaseCount('contact_submissions', 0);
    }

    public function test_blocked_message_is_neutral_and_localized(): void
    {
        $this->block();

        $expected = [
            'nl' => 'Uw bericht kon niet worden verwerkt. Neem bij een dringende vraag telefonisch contact met ons op.',
            'fr' => "Votre message n'a pas pu être traité. Pour une demande urgente, contactez-nous par téléphone.",
            'en' => 'Your message could not be processed. For urgent enquiries, please contact us by phone.',
        ];

        foreach ($expected as $locale => $message) {
            $response = $this->post(route('contact.store', ['locale' => $locale]), $this->validPayload());

            $errors = session('errors')->get('blocked');
            $this->assertSame($message, $errors[0], "Neutral blocked message for locale {$locale}");
            $this->assertStringNotContainsString('blok', mb_strtolower($errors[0]), 'Message must not reveal the block');
        }
    }

    public function test_email_matching_is_case_insensitive_and_trimmed(): void
    {
        $this->block(['email' => BlockedEmail::normalizeEmail('  SPAMMER@Example.COM  ')]);

        $this->post(route('contact.store', ['locale' => 'nl']), $this->validPayload([
            'email' => 'Spammer@example.com',
        ]))->assertSessionHasErrors('blocked');

        Mail::assertNothingSent();
    }

    public function test_inactive_block_does_not_reject(): void
    {
        $this->block(['is_active' => false]);

        $this->post(route('contact.store', ['locale' => 'nl']), $this->validPayload())
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'contact_message_sent');
    }

    public function test_expired_block_does_not_reject(): void
    {
        $this->block(['expires_at' => now()->subMinute()]);

        $this->post(route('contact.store', ['locale' => 'nl']), $this->validPayload())
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'contact_message_sent');
    }

    public function test_active_block_with_future_expiry_rejects(): void
    {
        $this->block(['expires_at' => now()->addDay()]);

        $this->post(route('contact.store', ['locale' => 'nl']), $this->validPayload())
            ->assertSessionHasErrors('blocked');

        Mail::assertNothingSent();
    }

    public function test_blocked_submission_does_not_consume_rate_limit_quota(): void
    {
        $this->block();

        $this->post(route('contact.store', ['locale' => 'nl']), $this->validPayload());

        $this->assertSame(0, RateLimiter::attempts('contact-form-daily:127.0.0.1'));
        $this->assertSame(0, RateLimiter::attempts('contact-form-burst:127.0.0.1'));
    }

    public function test_smart_request_form_is_not_affected_by_blocklist(): void
    {
        $this->block(['email' => 'klant@example.com']);

        $response = $this->post(route('customer-requests.store', ['locale' => 'nl']), [
            'service_category'      => 'sanitair',
            'customer_type'          => 'residential',
            'urgency'                => 'not_urgent',
            'description'            => 'Lekkende kraan in de keuken.',
            'unknown_device_details' => '1',
            'street'                 => 'Voorbeeldstraat 12',
            'postal_code'            => '1000',
            'city'                   => 'Brussel',
            'customer_name'          => 'Klant',
            'customer_email'         => 'klant@example.com',
            'privacy_consent'        => '1',
        ]);

        $response->assertSessionDoesntHaveErrors('blocked');
        $this->assertDatabaseHas('customer_requests', ['customer_email' => 'klant@example.com']);
    }

    public function test_missing_blocklist_table_degrades_to_not_blocked(): void
    {
        Schema::drop('blocked_emails');

        $this->post(route('contact.store', ['locale' => 'nl']), $this->validPayload())
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'contact_message_sent');
    }
}
