<?php

namespace Tests\Feature;

use App\Mail\NewCustomerRequestMail;
use App\Models\AdminUser;
use Database\Seeders\PageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Regression tests for the proxy-header hardening. With trustProxies('*')
 * every client could rewrite its own IP (X-Forwarded-For) and the host every
 * generated URL was built from (X-Forwarded-Host). Proxies are now only
 * trusted when listed in TRUSTED_PROXIES, and absolute URLs are pinned to
 * APP_URL.
 */
class ProxyHeaderHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PageSeeder::class);
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
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

    public function test_x_forwarded_for_does_not_reset_the_request_form_rate_limit(): void
    {
        Mail::fake();
        $limit = (int) config('site.request_daily_limit', 5);

        for ($i = 1; $i <= $limit; $i++) {
            $this->withHeaders(['X-Forwarded-For' => "203.0.113.{$i}"])
                ->post(route('customer-requests.store', ['locale' => 'nl']), $this->validPayload([
                    'customer_email' => "jan{$i}@example.com",
                ]))
                ->assertSessionHasNoErrors();
        }

        $this->withHeaders(['X-Forwarded-For' => '203.0.113.250'])
            ->post(route('customer-requests.store', ['locale' => 'nl']), $this->validPayload())
            ->assertSessionHasErrors('rate_limit');

        $this->assertDatabaseCount('customer_requests', $limit);
    }

    public function test_x_forwarded_for_does_not_bypass_the_login_throttle(): void
    {
        AdminUser::create([
            'name' => 'Martin',
            'email' => 'martin@test.com',
            'password' => Hash::make('CorrectHorse123'),
        ]);

        for ($i = 1; $i <= 5; $i++) {
            $this->withHeaders(['X-Forwarded-For' => "198.51.100.{$i}"])
                ->post(route('admin.login.submit'), ['email' => 'martin@test.com', 'password' => 'wrong'])
                ->assertSessionHasErrors('email');
        }

        $this->withHeaders(['X-Forwarded-For' => '198.51.100.99'])
            ->post(route('admin.login.submit'), ['email' => 'martin@test.com', 'password' => 'wrong'])
            ->assertStatus(429);
    }

    public function test_login_throttle_is_per_account_so_another_account_can_still_try(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->post(route('admin.login.submit'), ['email' => 'martin@test.com', 'password' => 'wrong'])
                ->assertSessionHasErrors('email');
        }

        $this->post(route('admin.login.submit'), ['email' => 'martin@test.com', 'password' => 'wrong'])
            ->assertStatus(429);

        // A different account is still allowed to try from the same client.
        $this->post(route('admin.login.submit'), ['email' => 'other@test.com', 'password' => 'wrong'])
            ->assertSessionHasErrors('email');
    }

    public function test_canonical_and_hreflang_follow_app_url_not_forwarded_host(): void
    {
        $response = $this->withHeaders([
            'X-Forwarded-Host' => 'evil.example',
            'X-Forwarded-Proto' => 'http',
            'Host' => 'evil.example',
        ])->get('/nl');

        $response->assertOk();
        $response->assertSee('<link rel="canonical" href="' . config('app.url') . '/nl">', false);
        $response->assertDontSee('evil.example');
    }

    public function test_sitemap_locations_follow_app_url_not_forwarded_host(): void
    {
        $response = $this->withHeaders(['X-Forwarded-Host' => 'evil.example', 'Host' => 'evil.example'])
            ->get('/sitemap.xml');

        $response->assertOk();
        $response->assertSee('<loc>' . config('app.url') . '/nl</loc>', false);
        $response->assertDontSee('evil.example');
    }

    public function test_admin_notification_link_uses_app_url_regardless_of_forwarded_host(): void
    {
        Mail::fake();

        $this->withHeaders(['X-Forwarded-Host' => 'evil.example', 'X-Forwarded-Proto' => 'https'])
            ->post(route('customer-requests.store', ['locale' => 'nl']), $this->validPayload())
            ->assertSessionHasNoErrors();

        Mail::assertSent(NewCustomerRequestMail::class, function (NewCustomerRequestMail $mail): bool {
            $html = $mail->render();

            return str_contains($html, config('app.url') . '/admin/requests/')
                && ! str_contains($html, 'evil.example');
        });
    }
}
