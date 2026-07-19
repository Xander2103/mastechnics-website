<?php

namespace Tests\Feature\Admin;

use App\Models\CustomerRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RequestExportTest extends TestCase
{
    use RefreshDatabase;

    private function adminSession(): array
    {
        return ['admin_user_email' => 'admin@test.com'];
    }

    public function test_csv_export_includes_reference_and_status_dates(): void
    {
        $req = CustomerRequest::create([
            'locale'         => 'nl',
            'service_slug'   => 'heating',
            'request_type'   => 'repair',
            'customer_name'  => 'Export Klant',
            'customer_email' => 'export@example.com',
            'description'    => 'Test',
            'status'         => 'won',
            'contacted_at'   => now(),
            'quote_sent_at'  => now(),
            'won_at'         => now(),
        ]);

        $response = $this->withSession($this->adminSession())
            ->get(route('admin.requests.export'));

        $response->assertOk();

        $content = $response->streamedContent();

        $this->assertStringContainsString($req->reference, $content);
        $this->assertStringContainsString('Export Klant', $content);
        $this->assertStringContainsString('Referentie', $content);
        $this->assertStringContainsString('Gecontacteerd op', $content);
        $this->assertStringContainsString('Offerte verstuurd op', $content);
        $this->assertStringContainsString('Gewonnen op', $content);
    }

    public function test_export_requires_admin_auth(): void
    {
        $this->get(route('admin.requests.export'))
            ->assertRedirect(route('admin.login'));
    }
}
