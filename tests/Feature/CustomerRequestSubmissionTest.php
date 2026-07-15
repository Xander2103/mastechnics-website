<?php

namespace Tests\Feature;

use App\Mail\CustomerRequestConfirmationMail;
use App\Mail\NewCustomerRequestMail;
use App\Models\CustomerRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class CustomerRequestSubmissionTest extends TestCase
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
            'service_category'        => 'sanitair',
            'customer_type'            => 'residential',
            'urgency'                  => 'not_urgent',
            'description'              => 'Lekkende kraan in de keuken.',
            'unknown_device_details'   => '1',
            'street'                   => 'Voorbeeldstraat 12',
            'postal_code'              => '1000',
            'city'                     => 'Brussel',
            'customer_name'            => 'Jan Janssens',
            'customer_email'           => 'jan@example.com',
            'privacy_consent'          => '1',
        ], $overrides);
    }

    public function test_customer_type_is_accepted_when_selected(): void
    {
        $response = $this->post(route('customer-requests.store', ['locale' => 'nl']), $this->validPayload());

        $response->assertSessionDoesntHaveErrors('customer_type');
        $response->assertRedirect();

        $request = CustomerRequest::first();
        $this->assertNotNull($request);
        $this->assertSame('residential', $request->metadata['answers']['customer_type']);
    }

    public function test_missing_customer_type_returns_localized_dutch_error(): void
    {
        $payload = $this->validPayload();
        unset($payload['customer_type']);

        $response = $this->post(route('customer-requests.store', ['locale' => 'nl']), $payload);

        $response->assertSessionHasErrors('customer_type');
        $this->assertSame(
            'Het klanttype is verplicht.',
            $response->getSession()->get('errors')->first('customer_type')
        );
    }

    public function test_missing_customer_type_returns_localized_french_error(): void
    {
        $payload = $this->validPayload();
        unset($payload['customer_type']);

        $response = $this->post(route('customer-requests.store', ['locale' => 'fr']), $payload);

        $response->assertSessionHasErrors('customer_type');
        $this->assertSame(
            'Le type de client est obligatoire.',
            $response->getSession()->get('errors')->first('customer_type')
        );
    }

    public function test_missing_customer_type_returns_localized_english_error(): void
    {
        $payload = $this->validPayload();
        unset($payload['customer_type']);

        $response = $this->post(route('customer-requests.store', ['locale' => 'en']), $payload);

        $response->assertSessionHasErrors('customer_type');
        $this->assertSame(
            'The customer type field is required.',
            $response->getSession()->get('errors')->first('customer_type')
        );
    }

    public function test_missing_consent_returns_localized_error(): void
    {
        $payload = $this->validPayload();
        unset($payload['privacy_consent']);

        $response = $this->post(route('customer-requests.store', ['locale' => 'nl']), $payload);

        $response->assertSessionHasErrors('privacy_consent');
        $this->assertSame(
            'U moet akkoord gaan met de privacyverklaring voor u de aanvraag kunt verzenden.',
            $response->getSession()->get('errors')->first('privacy_consent')
        );

        $this->assertDatabaseCount('customer_requests', 0);
    }

    public function test_valid_request_with_consent_submits_successfully(): void
    {
        $response = $this->post(route('customer-requests.store', ['locale' => 'nl']), $this->validPayload());

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $request = CustomerRequest::first();
        $this->assertNotNull($request);
        $this->assertTrue($request->privacy_consent);
    }

    public function test_successful_submission_sends_notification_email_to_martin(): void
    {
        $this->post(route('customer-requests.store', ['locale' => 'nl']), $this->validPayload());

        Mail::assertSent(NewCustomerRequestMail::class, function (NewCustomerRequestMail $mail) {
            return $mail->hasTo(config('site.request_notification_email'));
        });
    }

    public function test_successful_submission_sends_confirmation_email_to_customer(): void
    {
        $this->post(
            route('customer-requests.store', ['locale' => 'nl']),
            $this->validPayload(['customer_email' => 'klant@example.com'])
        );

        Mail::assertSent(CustomerRequestConfirmationMail::class, function (CustomerRequestConfirmationMail $mail) {
            return $mail->hasTo('klant@example.com');
        });
    }

    public function test_mail_failure_does_not_prevent_request_from_being_stored(): void
    {
        Mail::shouldReceive('to')->andReturnSelf();
        Mail::shouldReceive('send')->andThrow(new \RuntimeException('SMTP unavailable'));

        $response = $this->post(route('customer-requests.store', ['locale' => 'nl']), $this->validPayload());

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();
        $this->assertDatabaseCount('customer_requests', 1);
    }

    public function test_first_five_requests_per_day_are_allowed_and_sixth_is_blocked(): void
    {
        $limit = (int) config('site.request_daily_limit', 5);

        for ($i = 1; $i <= $limit; $i++) {
            $response = $this->post(
                route('customer-requests.store', ['locale' => 'nl']),
                $this->validPayload(['customer_email' => "jan{$i}@example.com"])
            );

            $response->assertSessionHasNoErrors();
        }

        $this->assertDatabaseCount('customer_requests', $limit);

        $blocked = $this->post(
            route('customer-requests.store', ['locale' => 'nl']),
            $this->validPayload(['customer_email' => 'jan-extra@example.com'])
        );

        $blocked->assertSessionHasErrors('rate_limit');
        $this->assertSame(
            'U heeft vandaag al meerdere aanvragen verstuurd. Probeer later opnieuw of neem rechtstreeks contact op.',
            $blocked->getSession()->get('errors')->first('rate_limit')
        );
        $this->assertDatabaseCount('customer_requests', $limit);
    }

    // This test verifies that the rate-limit error message is localized per locale on an
    // already-rate-limited IP — it does NOT exercise independent quotas per locale. The
    // daily/burst limiter is keyed by IP address only (not IP+locale), so the FR loop below
    // already exhausts the shared per-IP daily quota; the EN loop's requests are then blocked
    // by that same already-tripped counter, not by a fresh EN-specific quota. That IP-only
    // scoping is correct real-world behavior (a visitor switching locale shouldn't reset
    // their quota), and this test confirms the blocked response still returns the right
    // localized message for whichever locale made the request.
    public function test_rate_limit_message_is_localized_per_locale(): void
    {
        $limit = (int) config('site.request_daily_limit', 5);

        for ($i = 1; $i <= $limit; $i++) {
            $this->post(
                route('customer-requests.store', ['locale' => 'fr']),
                $this->validPayload(['customer_email' => "marie{$i}@example.com"])
            );
        }

        $frResponse = $this->post(
            route('customer-requests.store', ['locale' => 'fr']),
            $this->validPayload(['customer_email' => 'marie-extra@example.com'])
        );

        $this->assertSame(
            "Vous avez déjà envoyé plusieurs demandes aujourd'hui. Veuillez réessayer plus tard ou nous contacter directement.",
            $frResponse->getSession()->get('errors')->first('rate_limit')
        );

        for ($i = 1; $i <= $limit; $i++) {
            $this->post(
                route('customer-requests.store', ['locale' => 'en']),
                $this->validPayload(['customer_email' => "john{$i}@example.com"])
            );
        }

        $enResponse = $this->post(
            route('customer-requests.store', ['locale' => 'en']),
            $this->validPayload(['customer_email' => 'john-extra@example.com'])
        );

        $this->assertSame(
            'You have already sent several requests today. Please try again later or contact us directly.',
            $enResponse->getSession()->get('errors')->first('rate_limit')
        );
    }

    public function test_failed_validation_does_not_consume_daily_quota(): void
    {
        $payload = $this->validPayload();
        unset($payload['privacy_consent']);

        for ($i = 0; $i < 10; $i++) {
            $this->post(route('customer-requests.store', ['locale' => 'nl']), $payload);
        }

        $this->assertDatabaseCount('customer_requests', 0);

        $response = $this->post(route('customer-requests.store', ['locale' => 'nl']), $this->validPayload());
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseCount('customer_requests', 1);
    }
}
