<?php

namespace Tests\Unit;

use App\Mail\StandardReplyMail;
use App\Models\CustomerRequest;
use App\Services\StandardReplyService;
use Tests\TestCase;

class StandardReplyMailTest extends TestCase
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

    public function test_envelope_subject_matches_service_subject_per_locale(): void
    {
        foreach (['nl', 'fr', 'en'] as $locale) {
            $request = $this->makeRequest(['locale' => $locale]);

            $this->assertSame(
                StandardReplyService::subject($request),
                (new StandardReplyMail($request))->envelope()->subject
            );
        }
    }

    public function test_rendered_body_contains_generated_message(): void
    {
        $request = $this->makeRequest();

        $html = (new StandardReplyMail($request))->render();

        $this->assertStringContainsString('Dag Test Klant,', $html);
        $this->assertStringContainsString('Airco laten plaatsen', $html);
    }

    public function test_rendered_body_escapes_html_in_user_input(): void
    {
        $request = $this->makeRequest(['customer_name' => '<script>alert(1)</script>']);

        $html = (new StandardReplyMail($request))->render();

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function test_fr_email_uses_fr_lang_attribute(): void
    {
        $html = (new StandardReplyMail($this->makeRequest(['locale' => 'fr'])))->render();

        $this->assertStringContainsString('lang="fr"', $html);
        $this->assertStringContainsString('Bonjour Test Klant,', $html);
    }
}
