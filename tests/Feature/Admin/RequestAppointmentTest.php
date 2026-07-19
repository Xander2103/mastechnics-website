<?php

namespace Tests\Feature\Admin;

use App\Models\CustomerRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RequestAppointmentTest extends TestCase
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

    public function test_admin_can_create_appointment(): void
    {
        $req = $this->makeRequest();

        $this->withSession($this->adminSession())
            ->post(route('admin.requests.appointments.store', $req), [
                'date'       => '2026-08-01',
                'time'       => '14:30',
                'technician' => 'Martin',
                'location'   => 'Antwerpen',
                'notes'      => 'Meebrengen: ladder',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('customer_request_appointments', [
            'customer_request_id' => $req->id,
            'technician'          => 'Martin',
            'location'            => 'Antwerpen',
        ]);
    }

    public function test_appointment_requires_a_date(): void
    {
        $req = $this->makeRequest();

        $this->withSession($this->adminSession())
            ->post(route('admin.requests.appointments.store', $req), ['date' => ''])
            ->assertSessionHasErrors('date');
    }

    public function test_request_detail_page_shows_timeline(): void
    {
        $req = $this->makeRequest();
        $req->notes()->create(['author_email' => 'admin@test.com', 'body' => 'Klant gebeld']);

        $this->withSession($this->adminSession())
            ->get(route('admin.requests.show', $req))
            ->assertOk()
            ->assertSee('Tijdlijn')
            ->assertSee('Aanvraag aangemaakt')
            ->assertSee('Interne notitie')
            ->assertSee('Klant gebeld');
    }
}
