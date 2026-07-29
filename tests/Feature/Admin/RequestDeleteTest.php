<?php

namespace Tests\Feature\Admin;

use App\Models\CustomerRequest;
use App\Models\Quote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RequestDeleteTest extends TestCase
{
    use RefreshDatabase;

    private function adminSession(): array
    {
        return ['admin_user_email' => 'admin@test.com', 'admin_user_name' => 'Admin'];
    }

    private function makeRequest(array $attrs = []): CustomerRequest
    {
        return CustomerRequest::create(array_merge([
            'locale' => 'nl',
            'service_slug' => 'airco',
            'request_type' => 'installation',
            'customer_name' => 'Test Klant',
            'customer_email' => 'klant@example.com',
            'description' => 'Test aanvraag',
            'status' => 'new',
        ], $attrs));
    }

    public function test_request_without_quote_can_be_deleted(): void
    {
        $request = $this->makeRequest();

        $response = $this->withSession($this->adminSession())
            ->delete(route('admin.requests.destroy', $request));

        $response->assertRedirect(route('admin.requests.index'))
            ->assertSessionHas('success', 'request_deleted');
        $this->assertDatabaseMissing('customer_requests', ['id' => $request->id]);
    }

    public function test_request_with_quote_cannot_be_deleted(): void
    {
        $request = $this->makeRequest();
        Quote::create(['customer_request_id' => $request->id, 'quote_status' => 'draft']);

        $this->withSession($this->adminSession())
            ->delete(route('admin.requests.destroy', $request))
            ->assertSessionHas('success', 'delete_blocked_quote');

        $this->assertDatabaseHas('customer_requests', ['id' => $request->id]);
        $this->assertDatabaseHas('quotes', ['customer_request_id' => $request->id]);
    }

    public function test_attachment_files_and_rows_are_removed_on_delete(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('customer-requests/a.pdf', 'pdf');
        Storage::disk('public')->put('customer-requests/b.png', 'png');

        $request = $this->makeRequest();
        $request->attachments()->create([
            'original_name' => 'a.pdf', 'path' => 'customer-requests/a.pdf',
            'mime_type' => 'application/pdf', 'size' => 3,
        ]);
        $request->attachments()->create([
            'original_name' => 'b.png', 'path' => 'customer-requests/b.png',
            'mime_type' => 'image/png', 'size' => 3,
        ]);

        $this->withSession($this->adminSession())
            ->delete(route('admin.requests.destroy', $request));

        $this->assertDatabaseMissing('customer_request_attachments', ['path' => 'customer-requests/a.pdf']);
        Storage::disk('public')->assertMissing('customer-requests/a.pdf');
        Storage::disk('public')->assertMissing('customer-requests/b.png');
    }

    public function test_attachment_path_outside_storage_directory_is_not_deleted(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('avatars/keep.png', 'png');

        $request = $this->makeRequest();
        $request->attachments()->create([
            'original_name' => 'evil.png', 'path' => 'avatars/keep.png',
            'mime_type' => 'image/png', 'size' => 3,
        ]);
        $request->attachments()->create([
            'original_name' => 'traversal.png', 'path' => 'customer-requests/../avatars/keep.png',
            'mime_type' => 'image/png', 'size' => 3,
        ]);

        $this->withSession($this->adminSession())
            ->delete(route('admin.requests.destroy', $request))
            ->assertSessionHas('success', 'request_deleted');

        $this->assertDatabaseMissing('customer_requests', ['id' => $request->id]);
        Storage::disk('public')->assertExists('avatars/keep.png');
    }

    public function test_unauthenticated_cannot_delete(): void
    {
        $request = $this->makeRequest();

        $this->delete(route('admin.requests.destroy', $request))
            ->assertRedirect(route('admin.login'));

        $this->assertDatabaseHas('customer_requests', ['id' => $request->id]);
    }

    public function test_delete_button_hidden_when_quote_exists(): void
    {
        $request = $this->makeRequest();
        Quote::create(['customer_request_id' => $request->id, 'quote_status' => 'draft']);

        $this->withSession($this->adminSession())
            ->get(route('admin.requests.show', $request))
            ->assertOk()
            ->assertDontSee('Aanvraag verwijderen')
            ->assertSee('Verwijderen is niet mogelijk zolang er een offerte');
    }
}
