<?php

namespace Tests\Feature;

use App\Http\Controllers\CustomerRequestController;
use App\Mail\CustomerRequestConfirmationMail;
use App\Mail\NewCustomerRequestMail;
use App\Models\CustomerRequest;
use App\Models\CustomerRequestAttachment;
use App\Services\MailDispatcher;
use Database\Seeders\PageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\Support\InteractsWithFormProtection;
use Tests\TestCase;

/**
 * Regression tests for the request wizard hardening: idempotent submit
 * token, honeypot, private attachment disk, config-derived room rules,
 * translated nested error attributes, control-character stripping and the
 * explicit success redirect.
 */
class RequestWizardHardeningTest extends TestCase
{
    use InteractsWithFormProtection;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PageSeeder::class);
        Mail::fake();
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge($this->protectionFields('request'), [
            'service_category' => 'sanitair',
            'customer_type' => 'residential',
            'urgency' => 'not_urgent',
            'description' => 'Lekkende kraan in de keuken.',
            'unknown_device_details' => '1',
            'street' => 'Voorbeeldstraat 12',
            'postal_code' => '1000',
            'city' => 'Brussel',
            'customer_name' => 'Jan Janssens',
            'customer_email' => 'jan@example.com',
            'privacy_consent' => '1',
        ], $overrides);
    }

    private function validRoom(array $overrides = []): array
    {
        return array_merge([
            'type' => 'slaapkamer',
            'width' => '4',
            'length' => '5',
            'height' => '2.6',
            'roof_type' => 'none',
            'windows' => 'large',
            'orientation' => 'south',
        ], $overrides);
    }

    private function aircoPayload(array $overrides = []): array
    {
        return array_merge($this->validPayload([
            'service_category' => 'airco_offerte',
            'airco_house_age' => 'yes',
            'insulation_level' => 'good',
            'airco_outdoor_unit_location' => 'facade',
            'airco_installation_timing' => 'asap',
            'rooms' => [$this->validRoom()],
        ]), $overrides);
    }

    private function requestPageUrl(string $locale = 'nl'): string
    {
        return route('pages.show', ['locale' => $locale, 'slug' => config("site.page_slugs.request.{$locale}")]);
    }

    public function test_request_page_renders_hidden_submission_token_and_honeypot(): void
    {
        $html = $this->get($this->requestPageUrl())->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, 'name="submission_token"'));
        $this->assertSame(1, substr_count($html, 'name="' . CustomerRequestController::HONEYPOT_FIELD . '"'));
        $this->assertStringContainsString('id="requestWizardForm"', $html);
    }

    public function test_double_submission_with_same_token_stores_one_request_and_mails_once(): void
    {
        $payload = $this->validPayload(['submission_token' => '8d4d3f7e-1111-4222-8333-444455556666']);

        $this->post(route('customer-requests.store', ['locale' => 'nl']), $payload)
            ->assertSessionHas('success', 'request_created');
        $this->post(route('customer-requests.store', ['locale' => 'nl']), $payload)
            ->assertSessionHas('success', 'request_created');
        $this->post(route('customer-requests.store', ['locale' => 'nl']), $payload)
            ->assertSessionHas('success', 'request_created');

        $this->assertDatabaseCount('customer_requests', 1);
        $this->assertSame('8d4d3f7e-1111-4222-8333-444455556666', CustomerRequest::first()->submission_token);
        Mail::assertSent(NewCustomerRequestMail::class, 1);
        Mail::assertSent(CustomerRequestConfirmationMail::class, 1);
    }

    public function test_different_tokens_create_separate_requests(): void
    {
        // Two genuinely different submissions (the fingerprint layer would
        // otherwise treat an identical e-mail + description as a repeat).
        $this->post(route('customer-requests.store', ['locale' => 'nl']), $this->validPayload([
            'submission_token' => 'token-a',
            'customer_email' => 'a@example.com',
            'description' => 'Aanvraag A: lekkende kraan in de keuken.',
        ]));
        $this->post(route('customer-requests.store', ['locale' => 'nl']), $this->validPayload([
            'submission_token' => 'token-b',
            'customer_email' => 'b@example.com',
            'description' => 'Aanvraag B: lekkende kraan in de badkamer.',
        ]));

        $this->assertDatabaseCount('customer_requests', 2);
    }

    public function test_submission_without_token_still_works(): void
    {
        $payload = $this->validPayload();
        $this->assertArrayNotHasKey('submission_token', $payload);

        $this->post(route('customer-requests.store', ['locale' => 'nl']), $payload)
            ->assertSessionHas('success', 'request_created');

        $this->assertDatabaseCount('customer_requests', 1);
        $this->assertNull(CustomerRequest::first()->submission_token);
    }

    public function test_duplicate_submission_does_not_consume_rate_limit_quota(): void
    {
        $limit = (int) config('form-protection.forms.request.daily_limit', 5);
        $payload = $this->validPayload(['submission_token' => 'quota-token']);

        for ($i = 0; $i <= $limit + 2; $i++) {
            $this->post(route('customer-requests.store', ['locale' => 'nl']), $payload);
        }

        // A new submission with different content (the same description
        // from the same client would be caught by the repeat fingerprint).
        $this->post(route('customer-requests.store', ['locale' => 'nl']), $this->validPayload([
            'submission_token' => 'fresh',
            'description' => 'Nieuwe aanvraag: de kraan in de badkamer lekt nu ook.',
        ]))->assertSessionHasNoErrors();

        $this->assertDatabaseCount('customer_requests', 2);
    }

    public function test_filled_honeypot_is_silently_dropped(): void
    {
        $this->post(route('customer-requests.store', ['locale' => 'nl']), $this->validPayload([
            CustomerRequestController::HONEYPOT_FIELD => 'http://spam.example',
        ]))->assertSessionHas('success', 'request_created');

        $this->assertDatabaseCount('customer_requests', 0);
        Mail::assertNothingSent();
    }

    public function test_uploaded_attachment_is_stored_on_the_private_disk_only(): void
    {
        Storage::fake(CustomerRequestAttachment::DISK);
        Storage::fake(CustomerRequestAttachment::LEGACY_DISK);

        $this->post(route('customer-requests.store', ['locale' => 'nl']), $this->validPayload([
            'attachments' => [UploadedFile::fake()->image('../../evil.php.jpg', 20, 20)],
        ]))->assertSessionHasNoErrors();

        $attachment = CustomerRequestAttachment::first();
        $this->assertNotNull($attachment);
        $this->assertStringStartsWith('customer-requests/', $attachment->path);
        $this->assertStringNotContainsString('..', $attachment->path);
        Storage::disk(CustomerRequestAttachment::DISK)->assertExists($attachment->path);
        Storage::disk(CustomerRequestAttachment::LEGACY_DISK)->assertMissing($attachment->path);

        // Admin download reads from the private disk.
        $this->withSession($this->adminSession())
            ->get(route('admin.requests.attachments.download', [$attachment->customer_request_id, $attachment]))
            ->assertOk();
    }

    public function test_html_disguised_as_image_is_rejected_and_nothing_is_stored(): void
    {
        Storage::fake(CustomerRequestAttachment::DISK);

        // A real file (not the testing fake, which derives the MIME from the
        // name) so the server's content sniffing is what gets exercised.
        $tmp = tempnam(sys_get_temp_dir(), 'upl');
        file_put_contents($tmp, '<html><script>alert(1)</script></html>');
        $file = new UploadedFile($tmp, 'page.jpg', 'image/jpeg', null, true);

        $this->post(route('customer-requests.store', ['locale' => 'nl']), $this->validPayload([
            'attachments' => [$file],
        ]))->assertSessionHasErrors('attachments.0');

        $this->assertDatabaseCount('customer_requests', 0);
        $this->assertSame([], Storage::disk(CustomerRequestAttachment::DISK)->allFiles());
    }

    public function test_room_roof_type_windows_and_orientation_are_required_as_configured(): void
    {
        $room = $this->validRoom();
        unset($room['roof_type'], $room['windows'], $room['orientation']);

        $this->post(route('customer-requests.store', ['locale' => 'nl']), $this->aircoPayload(['rooms' => [$room]]))
            ->assertSessionHasErrors(['rooms.0.roof_type', 'rooms.0.windows', 'rooms.0.orientation']);

        $this->assertDatabaseCount('customer_requests', 0);
    }

    public function test_room_type_outside_config_is_rejected(): void
    {
        $this->post(route('customer-requests.store', ['locale' => 'nl']), $this->aircoPayload([
            'rooms' => [$this->validRoom(['type' => 'garage'])],
        ]))->assertSessionHasErrors('rooms.0.type');
    }

    public function test_room_and_attachment_errors_use_translated_labels(): void
    {
        Storage::fake(CustomerRequestAttachment::DISK);

        $nl = $this->post(route('customer-requests.store', ['locale' => 'nl']), $this->aircoPayload([
            'rooms' => [$this->validRoom(['width' => '0', 'height' => ''])],
            'attachments' => [UploadedFile::fake()->create('doc.txt', 10, 'text/plain')],
        ]));
        $nlErrors = $nl->getSession()->get('errors')->all();
        $nlText = implode(' ', $nlErrors);

        $this->assertStringNotContainsString('rooms.0', $nlText);
        $this->assertStringNotContainsString('attachments.0', $nlText);
        $this->assertStringContainsString('breedte (m)', $nlText);
        $this->assertStringContainsString('hoogte (m)', $nlText);
        $this->assertStringContainsString('bijlage', $nlText);

        $fr = $this->post(route('customer-requests.store', ['locale' => 'fr']), $this->aircoPayload([
            'rooms' => [$this->validRoom(['width' => '0'])],
        ]));
        $frText = implode(' ', $fr->getSession()->get('errors')->all());

        $this->assertStringNotContainsString('rooms.0', $frText);
        $this->assertStringContainsString('largeur (m)', $frText);
    }

    public function test_line_breaks_are_stripped_from_single_line_fields_but_kept_in_description(): void
    {
        $this->post(route('customer-requests.store', ['locale' => 'nl']), $this->validPayload([
            'customer_name' => "Jan\r\nBcc: evil@example.com",
            'street' => "Straat 1\nBus 2",
            'description' => "Regel 1\nRegel 2",
        ]))->assertSessionHasNoErrors();

        $request = CustomerRequest::first();
        $this->assertSame('Jan  Bcc: evil@example.com', $request->customer_name);
        $this->assertSame('Straat 1 Bus 2', $request->metadata['answers']['street']);
        $this->assertSame("Regel 1\nRegel 2", $request->description);
    }

    public function test_success_redirect_targets_the_request_page_even_without_referer(): void
    {
        $this->post(route('customer-requests.store', ['locale' => 'fr']), $this->validPayload())
            ->assertRedirect($this->requestPageUrl('fr'))
            ->assertSessionHas('success', 'request_created');
    }

    public function test_mail_dispatcher_treats_missing_recipient_as_failed_send_not_type_error(): void
    {
        $customerRequest = CustomerRequest::create([
            'locale' => 'nl',
            'service_slug' => 'sanitair',
            'request_type' => 'repair',
            'customer_name' => 'Jan',
            'customer_email' => 'jan@example.com',
            'description' => 'x',
            'status' => 'new',
        ]);

        $this->assertFalse(MailDispatcher::send(null, new CustomerRequestConfirmationMail($customerRequest), $customerRequest));
        $this->assertFalse(MailDispatcher::send('', new CustomerRequestConfirmationMail($customerRequest), $customerRequest));
        $this->assertFalse(MailDispatcher::send('null', new CustomerRequestConfirmationMail($customerRequest), $customerRequest));

        Mail::assertNothingSent();
        $this->assertDatabaseHas('mail_logs', ['status' => 'failed', 'error' => 'No recipient configured']);
    }

    public function test_request_is_stored_and_confirmed_even_when_no_admin_recipient_is_configured(): void
    {
        config(['site.request_notification_email' => '', 'admin.notification_emails' => []]);

        $this->post(route('customer-requests.store', ['locale' => 'nl']), $this->validPayload())
            ->assertSessionHas('success', 'request_created');

        $this->assertDatabaseCount('customer_requests', 1);
        Mail::assertSent(CustomerRequestConfirmationMail::class, 1);
        Mail::assertNotSent(NewCustomerRequestMail::class);
    }

    public function test_oversized_post_redirects_back_to_the_wizard_with_a_message(): void
    {
        $response = $this->call('POST', '/fr/requests', [], [], [], [
            'CONTENT_LENGTH' => 99 * 1024 * 1024 * 1024,
        ]);

        $response->assertRedirect($this->requestPageUrl('fr') . '?upload_too_large=1');

        $this->get($this->requestPageUrl('fr') . '?upload_too_large=1')
            ->assertOk()
            ->assertSee('trop volumineuses');
    }
}
