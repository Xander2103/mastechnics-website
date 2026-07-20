<?php

namespace Tests\Feature\Admin;

use App\Models\CustomerRequest;
use App\Models\Quote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression coverage for the admin request detail page ("Bekijken"),
 * which 500'd in production. AdminRequestController::show() eager-loads
 * attachments/notes/quote/mailLogs/appointments, all of which must render
 * safely whether or not each relation has any rows.
 */
class RequestDetailTest extends TestCase
{
    use RefreshDatabase;

    private function adminSession(): array
    {
        return ['admin_user_email' => 'admin@test.com'];
    }

    private function makeRequest(array $attrs = []): CustomerRequest
    {
        return CustomerRequest::create(array_merge([
            'locale'         => 'nl',
            'service_slug'   => 'heating',
            'request_type'   => 'repair',
            'customer_name'  => 'Test Klant',
            'customer_email' => 'test@example.com',
            'description'    => 'Test aanvraag',
            'status'         => 'new',
        ], $attrs));
    }

    public function test_request_without_quote_opens_successfully(): void
    {
        $request = $this->makeRequest();

        $this->withSession($this->adminSession())
            ->get(route('admin.requests.show', $request))
            ->assertOk()
            ->assertSee('Nog geen offerte aangemaakt voor deze aanvraag.');
    }

    public function test_request_with_quote_opens_successfully(): void
    {
        $request = $this->makeRequest();
        Quote::create([
            'customer_request_id' => $request->id,
            'quote_status'        => 'draft',
            'amount_excl_vat'     => 1000,
            'vat_rate'            => 21,
            'amount_vat'          => 210,
            'amount_incl_vat'     => 1210,
        ]);

        $this->withSession($this->adminSession())
            ->get(route('admin.requests.show', $request))
            ->assertOk()
            ->assertSee('Offerte aanmaken')
            ->assertDontSee('Nog geen offerte aangemaakt voor deze aanvraag.');
    }

    public function test_request_without_notes_or_activity_opens_successfully(): void
    {
        $request = $this->makeRequest();

        $this->withSession($this->adminSession())
            ->get(route('admin.requests.show', $request))
            ->assertOk()
            ->assertSee('Er zijn nog geen notities voor deze aanvraag.');
    }

    public function test_request_with_notes_and_activity_opens_successfully(): void
    {
        $request = $this->makeRequest();
        $request->notes()->create([
            'author_email' => 'admin@test.com',
            'body'         => 'Klant gebeld, offerte volgt.',
        ]);
        $request->update([
            'status'       => 'contacted',
            'contacted_at' => now(),
        ]);

        $this->withSession($this->adminSession())
            ->get(route('admin.requests.show', $request))
            ->assertOk()
            ->assertSee('Klant gebeld, offerte volgt.')
            ->assertSee('Gecontacteerd');
    }

    public function test_request_detail_requires_admin_auth(): void
    {
        $request = $this->makeRequest();

        $this->get(route('admin.requests.show', $request))
            ->assertRedirect(route('admin.login'));
    }
}
