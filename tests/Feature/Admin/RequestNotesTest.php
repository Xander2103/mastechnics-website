<?php

namespace Tests\Feature\Admin;

use App\Models\CustomerRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RequestNotesTest extends TestCase
{
    use RefreshDatabase;

    private function adminSession(string $email = 'admin@test.com'): array
    {
        return ['admin_user_email' => $email];
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

    public function test_admin_can_store_a_note(): void
    {
        $req = $this->makeRequest();

        $this->withSession($this->adminSession())
            ->post(route('admin.requests.notes.store', $req), ['body' => 'Klant gebeld.'])
            ->assertRedirect();

        $this->assertDatabaseHas('customer_request_notes', [
            'customer_request_id' => $req->id,
            'author_email'        => 'admin@test.com',
            'body'                => 'Klant gebeld.',
        ]);
    }

    public function test_author_can_update_their_own_note(): void
    {
        $req  = $this->makeRequest();
        $note = $req->notes()->create(['author_email' => 'admin@test.com', 'body' => 'Origineel.']);

        $this->withSession($this->adminSession('admin@test.com'))
            ->patch(route('admin.requests.notes.update', [$req, $note]), ['body' => 'Bijgewerkt.'])
            ->assertRedirect();

        $this->assertSame('Bijgewerkt.', $note->fresh()->body);
    }

    public function test_other_admin_cannot_update_someone_elses_note(): void
    {
        $req  = $this->makeRequest();
        $note = $req->notes()->create(['author_email' => 'martin@mastechnics.be', 'body' => 'Origineel.']);

        $this->withSession($this->adminSession('other-admin@test.com'))
            ->patch(route('admin.requests.notes.update', [$req, $note]), ['body' => 'Geknoei.'])
            ->assertForbidden();

        $this->assertSame('Origineel.', $note->fresh()->body);
    }

    public function test_author_can_delete_their_own_note(): void
    {
        $req  = $this->makeRequest();
        $note = $req->notes()->create(['author_email' => 'admin@test.com', 'body' => 'Te verwijderen.']);

        $this->withSession($this->adminSession('admin@test.com'))
            ->delete(route('admin.requests.notes.destroy', [$req, $note]))
            ->assertRedirect();

        $this->assertDatabaseMissing('customer_request_notes', ['id' => $note->id]);
    }

    public function test_other_admin_cannot_delete_someone_elses_note(): void
    {
        $req  = $this->makeRequest();
        $note = $req->notes()->create(['author_email' => 'martin@mastechnics.be', 'body' => 'Blijft staan.']);

        $this->withSession($this->adminSession('other-admin@test.com'))
            ->delete(route('admin.requests.notes.destroy', [$req, $note]))
            ->assertForbidden();

        $this->assertDatabaseHas('customer_request_notes', ['id' => $note->id]);
    }
}
