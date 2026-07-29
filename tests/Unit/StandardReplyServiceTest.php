<?php

namespace Tests\Unit;

use App\Models\CustomerRequest;
use App\Services\StandardReplyService;
use Tests\TestCase;

class StandardReplyServiceTest extends TestCase
{
    private function makeRequest(array $attrs = []): CustomerRequest
    {
        return new CustomerRequest(array_merge([
            'locale' => 'nl',
            'service_slug' => 'airco',
            'request_type' => 'installation',
            'customer_name' => 'Test Klant',
            'customer_email' => 'klant@example.com',
            'service_category' => 'airco_offerte',
        ], $attrs));
    }

    public function test_nl_message_contains_greeting_and_localized_category(): void
    {
        $message = StandardReplyService::message($this->makeRequest());

        $this->assertStringContainsString('Dag Test Klant,', $message);
        $this->assertStringContainsString('Airco laten plaatsen', $message);
        $this->assertStringContainsString('Bedankt voor uw aanvraag', $message);
        $this->assertStringContainsString(config('site.contact.phone_display'), $message);
    }

    public function test_fr_message_is_localized(): void
    {
        $message = StandardReplyService::message($this->makeRequest(['locale' => 'fr']));

        $this->assertStringContainsString('Bonjour Test Klant,', $message);
        $this->assertStringContainsString('Faire installer une climatisation', $message);
        $this->assertStringContainsString('Merci pour votre demande', $message);
    }

    public function test_en_message_is_localized(): void
    {
        $message = StandardReplyService::message($this->makeRequest(['locale' => 'en']));

        $this->assertStringContainsString('Hello Test Klant,', $message);
        $this->assertStringContainsString('Install air conditioning', $message);
        $this->assertStringContainsString('Thank you for your request', $message);
    }

    public function test_unknown_locale_falls_back_to_nl(): void
    {
        $message = StandardReplyService::message($this->makeRequest(['locale' => 'de']));

        $this->assertStringContainsString('Dag Test Klant,', $message);
        $this->assertStringContainsString('Bedankt voor uw aanvraag', $message);
    }

    public function test_unknown_category_omits_category_clause(): void
    {
        $message = StandardReplyService::message(
            $this->makeRequest(['service_category' => 'does_not_exist'])
        );

        $this->assertStringContainsString('Bedankt voor uw aanvraag via Mastechnics.', $message);
        $this->assertStringNotContainsString('does_not_exist', $message);
    }

    public function test_missing_category_omits_category_clause(): void
    {
        $message = StandardReplyService::message(
            $this->makeRequest(['service_category' => null])
        );

        $this->assertStringContainsString('Bedankt voor uw aanvraag via Mastechnics.', $message);
    }

    public function test_subject_is_localized_with_nl_fallback(): void
    {
        $this->assertNotSame(
            StandardReplyService::subject($this->makeRequest(['locale' => 'fr'])),
            StandardReplyService::subject($this->makeRequest(['locale' => 'nl']))
        );

        $this->assertSame(
            StandardReplyService::subject($this->makeRequest(['locale' => 'nl'])),
            StandardReplyService::subject($this->makeRequest(['locale' => 'xx']))
        );
    }

    public function test_whatsapp_url_embeds_exactly_the_same_message(): void
    {
        $request = $this->makeRequest(['customer_phone' => '0495 12 11 78']);

        $url = StandardReplyService::whatsappUrl($request);

        $this->assertNotNull($url);
        $this->assertStringStartsWith('https://wa.me/32495121178?text=', $url);
        $this->assertStringContainsString(
            rawurlencode(StandardReplyService::message($request)),
            $url
        );
    }

    public function test_whatsapp_url_is_null_without_usable_phone(): void
    {
        $this->assertNull(StandardReplyService::whatsappUrl($this->makeRequest(['customer_phone' => null])));
        $this->assertNull(StandardReplyService::whatsappUrl($this->makeRequest(['customer_phone' => '12'])));
    }

    public function test_phone_normalization_handles_international_prefixes(): void
    {
        $plus = StandardReplyService::whatsappUrl($this->makeRequest(['customer_phone' => '+32 495/12.11.78']));
        $zeros = StandardReplyService::whatsappUrl($this->makeRequest(['customer_phone' => '0032495121178']));

        $this->assertStringStartsWith('https://wa.me/32495121178?text=', $plus);
        $this->assertStringStartsWith('https://wa.me/32495121178?text=', $zeros);
    }
}
