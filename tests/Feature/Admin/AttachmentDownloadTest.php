<?php

namespace Tests\Feature\Admin;

use App\Models\CustomerRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AttachmentDownloadTest extends TestCase
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

    public function test_unauthenticated_cannot_download_attachment(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('customer-requests/foto.png', 'png');

        $request = $this->makeRequest();
        $attachment = $request->attachments()->create([
            'original_name' => 'foto.png', 'path' => 'customer-requests/foto.png',
            'mime_type' => 'image/png', 'size' => 3,
        ]);

        $this->get(route('admin.requests.attachments.download', [$request, $attachment]))
            ->assertRedirect(route('admin.login'));
    }

    public function test_admin_can_download_own_request_attachment(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('customer-requests/offerte.pdf', '%PDF-1.4 test');

        $request = $this->makeRequest();
        $attachment = $request->attachments()->create([
            'original_name' => 'offerte.pdf', 'path' => 'customer-requests/offerte.pdf',
            'mime_type' => 'application/pdf', 'size' => 13,
        ]);

        $response = $this->withSession($this->adminSession())
            ->get(route('admin.requests.attachments.download', [$request, $attachment]));

        $response->assertOk();
        $this->assertStringContainsString('attachment', $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('offerte.pdf', $response->headers->get('Content-Disposition'));
    }

    public function test_images_are_served_inline_for_thumbnails(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('customer-requests/foto.png', 'png');

        $request = $this->makeRequest();
        $attachment = $request->attachments()->create([
            'original_name' => 'foto.png', 'path' => 'customer-requests/foto.png',
            'mime_type' => 'image/png', 'size' => 3,
        ]);

        $response = $this->withSession($this->adminSession())
            ->get(route('admin.requests.attachments.download', [$request, $attachment]));

        $response->assertOk();
        $this->assertStringContainsString('inline', $response->headers->get('Content-Disposition'));
    }

    public function test_attachment_of_other_request_returns_404(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('customer-requests/foto.png', 'png');

        $requestA = $this->makeRequest();
        $requestB = $this->makeRequest(['customer_email' => 'ander@example.com']);
        $attachmentB = $requestB->attachments()->create([
            'original_name' => 'foto.png', 'path' => 'customer-requests/foto.png',
            'mime_type' => 'image/png', 'size' => 3,
        ]);

        $this->withSession($this->adminSession())
            ->get(route('admin.requests.attachments.download', [$requestA, $attachmentB]))
            ->assertNotFound();
    }

    public function test_missing_file_returns_404_and_detail_page_shows_message(): void
    {
        Storage::fake('public');

        $request = $this->makeRequest();
        $attachment = $request->attachments()->create([
            'original_name' => 'weg.png', 'path' => 'customer-requests/weg.png',
            'mime_type' => 'image/png', 'size' => 3,
        ]);

        $this->withSession($this->adminSession())
            ->get(route('admin.requests.attachments.download', [$request, $attachment]))
            ->assertNotFound();

        $this->withSession($this->adminSession())
            ->get(route('admin.requests.show', $request))
            ->assertOk()
            ->assertSee('Bestand niet meer beschikbaar.');
    }

    public function test_path_outside_attachment_directory_returns_404(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('avatars/secret.png', 'png');

        $request = $this->makeRequest();
        $attachment = $request->attachments()->create([
            'original_name' => 'secret.png', 'path' => 'avatars/secret.png',
            'mime_type' => 'image/png', 'size' => 3,
        ]);

        $this->withSession($this->adminSession())
            ->get(route('admin.requests.attachments.download', [$request, $attachment]))
            ->assertNotFound();
    }

    public function test_detail_page_no_longer_links_direct_storage_paths(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('customer-requests/foto.png', 'png');

        $request = $this->makeRequest();
        $request->attachments()->create([
            'original_name' => 'foto.png', 'path' => 'customer-requests/foto.png',
            'mime_type' => 'image/png', 'size' => 2048,
        ]);

        $html = $this->withSession($this->adminSession())
            ->get(route('admin.requests.show', $request))
            ->assertOk()
            ->assertSee('foto.png')
            ->assertSee('Afbeelding')
            ->assertSee('2 kB')
            ->getContent();

        $this->assertStringNotContainsString('storage/customer-requests', $html);
    }
}
