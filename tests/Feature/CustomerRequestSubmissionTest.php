<?php

namespace Tests\Feature;

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
}
