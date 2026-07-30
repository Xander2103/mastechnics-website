<?php

namespace Tests\Unit;

use App\Models\CustomerRequest;
use App\Services\QuoteEmailTextService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuoteEmailTextServiceTest extends TestCase
{
    use RefreshDatabase;

    private function makeRequest(string $locale): CustomerRequest
    {
        return CustomerRequest::create([
            'locale' => $locale,
            'service_slug' => 'airco',
            'request_type' => 'installation',
            'customer_name' => 'Jan Janssens',
            'customer_email' => 'jan@example.com',
            'description' => 'Test',
            'status' => 'new',
        ]);
    }

    public function test_subject_per_locale(): void
    {
        $this->assertSame('Uw offerte van Mastechnics', QuoteEmailTextService::subject($this->makeRequest('nl')));
        $this->assertSame('Votre devis de Mastechnics', QuoteEmailTextService::subject($this->makeRequest('fr')));
        $this->assertSame('Your quotation from Mastechnics', QuoteEmailTextService::subject($this->makeRequest('en')));
    }

    public function test_body_contains_localized_greeting_and_core_sentence(): void
    {
        $nl = QuoteEmailTextService::body($this->makeRequest('nl'));
        $this->assertStringContainsString('Beste Jan Janssens,', $nl);
        $this->assertStringContainsString('Zoals besproken vindt u in bijlage onze offerte.', $nl);

        $fr = QuoteEmailTextService::body($this->makeRequest('fr'));
        $this->assertStringContainsString('Bonjour Jan Janssens,', $fr);
        $this->assertStringContainsString('Comme convenu, vous trouverez notre devis en pièce jointe.', $fr);

        $en = QuoteEmailTextService::body($this->makeRequest('en'));
        $this->assertStringContainsString('Dear Jan Janssens,', $en);
        $this->assertStringContainsString('As discussed, please find our quotation attached.', $en);
    }

    public function test_unknown_locale_falls_back_to_dutch(): void
    {
        $request = $this->makeRequest('de');

        $this->assertSame('nl', QuoteEmailTextService::locale($request));
        $this->assertSame('Uw offerte van Mastechnics', QuoteEmailTextService::subject($request));
        $this->assertStringContainsString('Beste Jan Janssens,', QuoteEmailTextService::body($request));
    }

    public function test_labels_expose_email_chrome_per_locale(): void
    {
        $this->assertSame('Geldig tot', QuoteEmailTextService::labels($this->makeRequest('nl'))['valid_until']);
        $this->assertSame('Numéro de devis', QuoteEmailTextService::labels($this->makeRequest('fr'))['quote_number']);
        $this->assertSame('Your quote is ready', QuoteEmailTextService::labels($this->makeRequest('en'))['title']);
    }
}
