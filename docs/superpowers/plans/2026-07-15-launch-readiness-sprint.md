# Launch Readiness Sprint Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make the Mastechnics Laravel site safe to go live: correct request-notification email recipient, add rate limiting, tighten admin/security posture, and document remaining launch blockers — without touching the request-flow config, quote system, or existing UI.

**Architecture:** Small, additive changes only. Config-driven values in `config/site.php`. A custom in-controller rate limiter (checked before, incremented only after a successful submission) instead of blanket `throttle` middleware, so failed validation attempts don't consume the daily quota. A new global `SecurityHeaders` middleware. Admin login gets Laravel's named `RateLimiter` + `throttle` middleware. No CSP (would break existing inline `<style>`/JS in `request-page.blade.php`) — documented instead.

**Tech Stack:** Laravel 12, PHPUnit, `Illuminate\Support\Facades\RateLimiter`, `Illuminate\Support\Facades\Mail`, `Illuminate\Support\Facades\Log`.

## Global Constraints

- Windows/PowerShell only for shell commands — no bash syntax.
- nl/fr/en translations required for every new user-facing string; `nl` is the fallback.
- Never break: `CustomerRequest` storage, file uploads, the 3 request-flow routes, `/admin/requests`, `NewCustomerRequestMail`/`CustomerRequestConfirmationMail`, any route in `routes/web.php`.
- Do not hardcode step logic in the controller/blade — `config/request-flow.php` stays the source of truth (not touched by this plan).
- Admin auth stays `AdminUser` + `Hash::check()` — no plaintext passwords.
- File uploads: `mimes:jpg,jpeg,png,webp,pdf`, `max:5120` — already correct, do not loosen.
- No `.env` edits — only `.env.example`.
- Work task-by-task, one logical commit per task, do not bleed changes across tasks.
- Do not push/deploy.

---

### Task 1: Fix notification recipient bug + safe mail sending

**Context:** `config/admin.php` currently defaults `ADMIN_NOTIFICATION_EMAIL` to `duisburg2103@gmail.com` (a developer's personal address) — every real customer request is CC'd there today. The mailables (`NewCustomerRequestMail`, `CustomerRequestConfirmationMail`) and their Blade views already contain everything required (name, email, phone, service, extra answers table incl. urgency/timing, description, admin link, attachment count) — no new mailable needed. Mail sending is currently un-guarded: an SMTP exception would bubble up as a 500 after the request row is already saved.

**Files:**
- Modify: `config/site.php`
- Modify: `config/admin.php`
- Modify: `app/Http/Controllers/CustomerRequestController.php:1-13` (imports) and `:178-188` (mail sending block)
- Modify: `.env.example`
- Test: `tests/Feature/CustomerRequestSubmissionTest.php`

**Interfaces:**
- Produces: `config('site.request_notification_email')` — string, primary recipient. `config('admin.notification_emails')` — array, optional extra CC recipients (defaults to empty).

- [ ] **Step 1: Add the config key**

In `config/site.php`, add after the `contact` key:

```php
    'request_notification_email' => env('REQUEST_NOTIFICATION_EMAIL', 'martin@mastechnics.be'),

    'request_daily_limit' => (int) env('REQUEST_DAILY_LIMIT', 5),
    'request_burst_limit_per_hour' => (int) env('REQUEST_BURST_LIMIT_PER_HOUR', 10),
```

(The two rate-limit keys are added here now so Task 2 doesn't need to touch this file again.)

- [ ] **Step 2: Remove the personal-email default**

Replace the full contents of `config/admin.php` with:

```php
<?php

return [
    // Optional extra recipients CC'd on every new customer request,
    // in addition to config('site.request_notification_email').
    // Comma-separate multiple addresses in ADMIN_NOTIFICATION_EMAIL if needed.
    'notification_emails' => array_filter(
        array_map('trim', explode(',', (string) env('ADMIN_NOTIFICATION_EMAIL', '')))
    ),
];
```

- [ ] **Step 3: Update `.env.example`**

In `.env.example`, after the `MAIL_FROM_NAME="${APP_NAME}"` line, add:

```
REQUEST_NOTIFICATION_EMAIL=martin@mastechnics.be
ADMIN_NOTIFICATION_EMAIL=
REQUEST_DAILY_LIMIT=5
REQUEST_BURST_LIMIT_PER_HOUR=10
```

- [ ] **Step 4: Wire the controller to the new config + guard against mail failure**

In `app/Http/Controllers/CustomerRequestController.php`, add to the `use` block at the top:

```php
use Illuminate\Support\Facades\Log;
```

Replace lines 180-188 (the notification-sending block) with:

```php
        $notificationEmails = collect(config('admin.notification_emails', []))
            ->push(config('site.request_notification_email'))
            ->filter()
            ->unique()
            ->values();

        foreach ($notificationEmails as $email) {
            try {
                Mail::to($email)->send(new NewCustomerRequestMail($customerRequest));
            } catch (\Throwable $e) {
                Log::error('Failed to send new-customer-request notification email', [
                    'email' => $email,
                    'customer_request_id' => $customerRequest->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        try {
            Mail::to($customerRequest->customer_email)->send(
                new CustomerRequestConfirmationMail($customerRequest)
            );
        } catch (\Throwable $e) {
            Log::error('Failed to send customer confirmation email', [
                'customer_request_id' => $customerRequest->id,
                'error' => $e->getMessage(),
            ]);
        }
```

This keeps the request stored (already done above this block) even if mail delivery throws.

- [ ] **Step 5: Write the tests**

Add to `tests/Feature/CustomerRequestSubmissionTest.php` (add these imports at the top alongside the existing ones):

```php
use App\Mail\CustomerRequestConfirmationMail;
use App\Mail\NewCustomerRequestMail;
```

Add these test methods to the class:

```php
    public function test_successful_submission_sends_notification_email_to_martin(): void
    {
        $this->post(route('customer-requests.store', ['locale' => 'nl']), $this->validPayload());

        Mail::assertSent(NewCustomerRequestMail::class, function (NewCustomerRequestMail $mail) {
            return $mail->hasTo(config('site.request_notification_email'));
        });
    }

    public function test_successful_submission_sends_confirmation_email_to_customer(): void
    {
        $this->post(
            route('customer-requests.store', ['locale' => 'nl']),
            $this->validPayload(['customer_email' => 'klant@example.com'])
        );

        Mail::assertSent(CustomerRequestConfirmationMail::class, function (CustomerRequestConfirmationMail $mail) {
            return $mail->hasTo('klant@example.com');
        });
    }

    public function test_mail_failure_does_not_prevent_request_from_being_stored(): void
    {
        Mail::shouldReceive('to')->andReturnSelf();
        Mail::shouldReceive('send')->andThrow(new \RuntimeException('SMTP unavailable'));

        $response = $this->post(route('customer-requests.store', ['locale' => 'nl']), $this->validPayload());

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();
        $this->assertDatabaseCount('customer_requests', 1);
    }
```

- [ ] **Step 6: Run the tests**

Run: `php artisan test --filter=CustomerRequestSubmissionTest`
Expected: all tests PASS (existing 5 + 3 new = 8 tests).

- [ ] **Step 7: Commit**

```bash
git add config/site.php config/admin.php .env.example app/Http/Controllers/CustomerRequestController.php tests/Feature/CustomerRequestSubmissionTest.php
git commit -m "feat(request): notify Martin on new request"
```

---

### Task 2: Daily + hourly rate limiting on request submissions

**Context:** No rate limiting exists today on `POST /{locale}/requests`. Requirement: max 5 successful submissions/day/IP (config-driven, already added to `config/site.php` in Task 1 as `request_daily_limit` and `request_burst_limit_per_hour`), with a friendly localized error, without penalizing users who merely fail validation. The route is POST-only, so GET page loads are never counted — no extra guard needed for that.

**Files:**
- Modify: `app/Http/Controllers/CustomerRequestController.php`
- Test: `tests/Feature/CustomerRequestSubmissionTest.php`

**Interfaces:**
- Consumes: `config('site.request_daily_limit')`, `config('site.request_burst_limit_per_hour')` (from Task 1).
- Produces: a `rate_limit` session error key when either limiter trips, containing the localized message.

- [ ] **Step 1: Add the import**

In `app/Http/Controllers/CustomerRequestController.php`, add to the `use` block:

```php
use Illuminate\Support\Facades\RateLimiter;
```

- [ ] **Step 2: Check limiters before validation**

Right after `app()->setLocale($locale);` in `store()`, insert:

```php
        $ip = $request->ip();
        $dailyKey = "request-form-daily:{$ip}";
        $burstKey = "request-form-burst:{$ip}";
        $dailyLimit = (int) config('site.request_daily_limit', 5);
        $burstLimit = (int) config('site.request_burst_limit_per_hour', 10);

        if (RateLimiter::tooManyAttempts($dailyKey, $dailyLimit)
            || RateLimiter::tooManyAttempts($burstKey, $burstLimit)
        ) {
            return back()
                ->withErrors(['rate_limit' => $this->rateLimitMessage($locale)])
                ->withInput();
        }
```

- [ ] **Step 3: Increment the limiters only after a successful save**

Immediately after the `$customerRequest = CustomerRequest::create([...]);` block (right before the `if ($request->hasFile('attachments'))` line), insert:

```php
        RateLimiter::hit($dailyKey, 86400);
        RateLimiter::hit($burstKey, 3600);
```

This means a submission that fails validation never consumes the quota — only real, stored submissions count.

- [ ] **Step 4: Add the localized message helper**

Add this private method to the class (near `buildValidationAttributes`):

```php
    private function rateLimitMessage(string $locale): string
    {
        $messages = [
            'nl' => 'U heeft vandaag al meerdere aanvragen verstuurd. Probeer later opnieuw of neem rechtstreeks contact op.',
            'fr' => "Vous avez déjà envoyé plusieurs demandes aujourd'hui. Veuillez réessayer plus tard ou nous contacter directement.",
            'en' => 'You have already sent several requests today. Please try again later or contact us directly.',
        ];

        return $messages[$locale] ?? $messages['nl'];
    }
```

- [ ] **Step 5: Write the tests**

Add to `tests/Feature/CustomerRequestSubmissionTest.php`:

```php
    public function test_first_five_requests_per_day_are_allowed_and_sixth_is_blocked(): void
    {
        $limit = (int) config('site.request_daily_limit', 5);

        for ($i = 1; $i <= $limit; $i++) {
            $response = $this->post(
                route('customer-requests.store', ['locale' => 'nl']),
                $this->validPayload(['customer_email' => "jan{$i}@example.com"])
            );

            $response->assertSessionHasNoErrors();
        }

        $this->assertDatabaseCount('customer_requests', $limit);

        $blocked = $this->post(
            route('customer-requests.store', ['locale' => 'nl']),
            $this->validPayload(['customer_email' => 'jan-extra@example.com'])
        );

        $blocked->assertSessionHasErrors('rate_limit');
        $this->assertSame(
            'U heeft vandaag al meerdere aanvragen verstuurd. Probeer later opnieuw of neem rechtstreeks contact op.',
            $blocked->getSession()->get('errors')->first('rate_limit')
        );
        $this->assertDatabaseCount('customer_requests', $limit);
    }

    public function test_rate_limit_message_is_localized_per_locale(): void
    {
        $limit = (int) config('site.request_daily_limit', 5);

        for ($i = 1; $i <= $limit; $i++) {
            $this->post(
                route('customer-requests.store', ['locale' => 'fr']),
                $this->validPayload(['customer_email' => "marie{$i}@example.com"])
            );
        }

        $frResponse = $this->post(
            route('customer-requests.store', ['locale' => 'fr']),
            $this->validPayload(['customer_email' => 'marie-extra@example.com'])
        );

        $this->assertSame(
            "Vous avez déjà envoyé plusieurs demandes aujourd'hui. Veuillez réessayer plus tard ou nous contacter directement.",
            $frResponse->getSession()->get('errors')->first('rate_limit')
        );

        for ($i = 1; $i <= $limit; $i++) {
            $this->post(
                route('customer-requests.store', ['locale' => 'en']),
                $this->validPayload(['customer_email' => "john{$i}@example.com"])
            );
        }

        $enResponse = $this->post(
            route('customer-requests.store', ['locale' => 'en']),
            $this->validPayload(['customer_email' => 'john-extra@example.com'])
        );

        $this->assertSame(
            'You have already sent several requests today. Please try again later or contact us directly.',
            $enResponse->getSession()->get('errors')->first('rate_limit')
        );
    }

    public function test_failed_validation_does_not_consume_daily_quota(): void
    {
        $payload = $this->validPayload();
        unset($payload['privacy_consent']);

        for ($i = 0; $i < 10; $i++) {
            $this->post(route('customer-requests.store', ['locale' => 'nl']), $payload);
        }

        $this->assertDatabaseCount('customer_requests', 0);

        $response = $this->post(route('customer-requests.store', ['locale' => 'nl']), $this->validPayload());
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseCount('customer_requests', 1);
    }
```

Note: the FR and EN halves of `test_rate_limit_message_is_localized_per_locale` share the same test-client IP, and the limiter is keyed by IP only (not IP+locale). The FR loop already exhausts the shared per-IP daily quota, so the EN loop's requests are blocked by that same already-tripped counter rather than a fresh EN-specific quota. This test is really verifying message localization per-locale on an already-rate-limited IP — which correctly reflects real-world behavior (the limiter is per-IP, not per-locale).

- [ ] **Step 6: Run the tests**

Run: `php artisan test --filter=CustomerRequestSubmissionTest`
Expected: all tests PASS (11 total: 5 original + 3 from Task 1 + 3 from this task).

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/CustomerRequestController.php tests/Feature/CustomerRequestSubmissionTest.php
git commit -m "feat(request): add daily and hourly submission rate limits"
```

---

### Task 3: Admin login throttle

**Context:** `POST /admin/login` has no throttle — an attacker can brute-force `AdminUser` passwords. Add Laravel's named `RateLimiter` + `throttle` middleware, keyed by email+IP so one bad actor can't lock out a legitimate admin from a different IP.

**Files:**
- Modify: `app/Providers/AppServiceProvider.php`
- Modify: `routes/web.php:18-19`
- Test: `tests/Feature/Admin/AuthTest.php` (new file)

- [ ] **Step 1: Register the named limiter**

Replace the contents of `app/Providers/AppServiceProvider.php` with:

```php
<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('admin-login', function (Request $request) {
            $key = Str::lower((string) $request->input('email')) . '|' . $request->ip();

            return Limit::perMinute(5)->by($key);
        });
    }
}
```

- [ ] **Step 2: Apply the throttle to the login route**

In `routes/web.php`, replace:

```php
Route::post('/admin/login', [AdminAuthController::class, 'login'])
    ->name('admin.login.submit');
```

with:

```php
Route::post('/admin/login', [AdminAuthController::class, 'login'])
    ->middleware('throttle:admin-login')
    ->name('admin.login.submit');
```

- [ ] **Step 3: Write the test**

Create `tests/Feature/Admin/AuthTest.php`:

```php
<?php

namespace Tests\Feature\Admin;

use App\Models\AdminUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_correct_credentials_log_the_admin_in(): void
    {
        AdminUser::create([
            'name' => 'Martin',
            'email' => 'martin@mastechnics.be',
            'password' => Hash::make('correct-password'),
        ]);

        $response = $this->post(route('admin.login.submit'), [
            'email' => 'martin@mastechnics.be',
            'password' => 'correct-password',
        ]);

        $response->assertRedirect(route('admin.requests.index'));
        $this->assertTrue(session()->has('admin_user_email'));
    }

    public function test_sixth_failed_login_attempt_in_a_minute_is_throttled(): void
    {
        AdminUser::create([
            'name' => 'Martin',
            'email' => 'martin@mastechnics.be',
            'password' => Hash::make('correct-password'),
        ]);

        for ($i = 0; $i < 5; $i++) {
            $response = $this->post(route('admin.login.submit'), [
                'email' => 'martin@mastechnics.be',
                'password' => 'wrong-password',
            ]);

            $response->assertSessionHasErrors('email');
        }

        $sixth = $this->post(route('admin.login.submit'), [
            'email' => 'martin@mastechnics.be',
            'password' => 'wrong-password',
        ]);

        $sixth->assertStatus(429);
    }
}
```

- [ ] **Step 4: Run the tests**

Run: `php artisan test --filter=Admin`
Expected: `AuthTest` PASSES along with existing `RequestFollowupTest` and `QuoteTest`.

- [ ] **Step 5: Commit**

```bash
git add app/Providers/AppServiceProvider.php routes/web.php tests/Feature/Admin/AuthTest.php
git commit -m "feat(admin): throttle login attempts"
```

---

### Task 4: Security response headers

**Context:** No security headers are set today. Add a small global middleware for the safe defaults. Skip CSP — `resources/views/pages/partials/request-page.blade.php` and other views rely on inline `<style>`/inline JS, so a CSP would break the form; this is documented as a follow-up instead of enforced now.

**Files:**
- Create: `app/Http/Middleware/SecurityHeaders.php`
- Modify: `bootstrap/app.php`
- Test: `tests/Feature/SecurityHeadersTest.php` (new file)

- [ ] **Step 1: Create the middleware**

Create `app/Http/Middleware/SecurityHeaders.php`:

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set(
            'Permissions-Policy',
            'geolocation=(), microphone=(), camera=(), payment=(), usb=()'
        );

        return $response;
    }
}
```

- [ ] **Step 2: Register it globally**

In `bootstrap/app.php`, change the `withMiddleware` closure to:

```php
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin' => \App\Http\Middleware\EnsureAdminIsAuthenticated::class,
        ]);

        $middleware->append(\App\Http\Middleware\SecurityHeaders::class);
    })
```

- [ ] **Step 3: Write the test**

Create `tests/Feature/SecurityHeadersTest.php`:

```php
<?php

namespace Tests\Feature;

use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    public function test_public_pages_have_security_headers(): void
    {
        $response = $this->get('/nl');

        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->assertHeader(
            'Permissions-Policy',
            'geolocation=(), microphone=(), camera=(), payment=(), usb=()'
        );
    }
}
```

- [ ] **Step 4: Run the tests, then the full suite to confirm nothing else broke**

Run: `php artisan test --filter=SecurityHeadersTest`
Expected: PASS.

Run: `php artisan test`
Expected: all tests PASS (headers middleware must not interfere with redirects, session cookies, or the PDF response's `Content-Type`).

- [ ] **Step 5: Commit**

```bash
git add app/Http/Middleware/SecurityHeaders.php bootstrap/app.php tests/Feature/SecurityHeadersTest.php
git commit -m "chore(security): add default security response headers"
```

---

### Task 5: Production config safety + admin seeder guard

**Context:** `.env.example` doesn't document production-safe values. `database/seeders/AdminUserSeeder.php` falls back to `admin@example.com` / `password` if `ADMIN_EMAIL`/`ADMIN_PASSWORD` aren't set — safe for local dev, dangerous if ever run against a production database without those env vars set.

**Files:**
- Modify: `.env.example`
- Modify: `database/seeders/AdminUserSeeder.php`
- Test: `tests/Unit/AdminUserSeederTest.php` (new file)

- [ ] **Step 1: Document production values in `.env.example`**

At the very top of `.env.example`, above `APP_NAME=Laravel`, add:

```
# ── Production deployment notes ──────────────────────────────────────────
# APP_ENV=production
# APP_DEBUG=false
# LOG_LEVEL=error
# SESSION_SECURE_COOKIE=true   (requires HTTPS)
# SESSION_SAME_SITE=lax
# See "SESSION_HTTP_ONLY" below (already true by default).
# ──────────────────────────────────────────────────────────────────────────

```

Then, below the existing `SESSION_DOMAIN=null` line, add:

```
SESSION_SECURE_COOKIE=false
SESSION_SAME_SITE=lax
```

(Left `false`/`lax` as the local-dev-safe default; the comment block above documents the production override.)

- [ ] **Step 2: Guard the seeder against weak production defaults**

Replace `database/seeders/AdminUserSeeder.php` with:

```php
<?php

namespace Database\Seeders;

use App\Models\AdminUser;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('ADMIN_EMAIL');
        $password = env('ADMIN_PASSWORD');

        if (app()->environment('production') && (empty($email) || empty($password))) {
            throw new \RuntimeException(
                'Refusing to seed an admin user in production without ADMIN_EMAIL and ADMIN_PASSWORD set in the environment.'
            );
        }

        AdminUser::updateOrCreate(
            [
                'email' => $email ?: 'admin@example.com',
            ],
            [
                'name' => env('ADMIN_NAME', 'Admin'),
                'password' => Hash::make($password ?: 'password'),
            ]
        );
    }
}
```

- [ ] **Step 3: Write the test**

Create `tests/Unit/AdminUserSeederTest.php`:

```php
<?php

namespace Tests\Unit;

use Database\Seeders\AdminUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminUserSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_refuses_to_run_in_production_without_credentials(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        $this->expectException(\RuntimeException::class);

        (new AdminUserSeeder())->run();
    }
}
```

- [ ] **Step 4: Run the tests**

Run: `php artisan test --filter=AdminUserSeederTest`
Expected: PASS.

Run: `php artisan test`
Expected: all tests still PASS (existing seeders/tests that call `AdminUserSeeder` in the `testing` environment are unaffected — the guard only fires under `production`).

- [ ] **Step 5: Commit**

```bash
git add .env.example database/seeders/AdminUserSeeder.php tests/Unit/AdminUserSeederTest.php
git commit -m "chore(security): harden launch configuration and admin seeder"
```

---

### Task 6: Legal/company info placeholders

**Context:** No VAT/company number or physical address appears anywhere on the site or the quote PDF. Do not invent these — add them as clearly-labeled, env-driven, nullable config so Martin can fill them in before launch, and only render them if present (so nothing looks broken today).

**Files:**
- Modify: `config/site.php`
- Modify: `.env.example`
- Modify: `resources/views/layouts/app.blade.php` (footer)
- Modify: `resources/views/admin/quotes/pdf.blade.php`

- [ ] **Step 1: Add config keys**

In `config/site.php`, inside the `contact` array (after `'messenger' => 'mastechnics',`), add:

```php
        // TODO(Martin): provide real company number/VAT and legal address before launch.
        'company_number' => env('COMPANY_NUMBER'),
        'address' => env('COMPANY_ADDRESS'),
```

- [ ] **Step 2: Document the env vars**

In `.env.example`, near the new `REQUEST_NOTIFICATION_EMAIL` block from Task 1, add:

```
# TODO(Martin): required before launch for legal footer/quote compliance.
COMPANY_NUMBER=
COMPANY_ADDRESS=
```

- [ ] **Step 3: Render conditionally in the footer**

In `resources/views/layouts/app.blade.php`, inside the "Contact" `<ul class="footer-list">` block (after the Messenger `<li>`, before `</ul>`), add:

```blade
                    @if (!empty($siteContact['address']))
                    <li>{{ $siteContact['address'] }}</li>
                    @endif

                    @if (!empty($siteContact['company_number']))
                    <li>{{ $siteContact['company_number'] }}</li>
                    @endif
```

- [ ] **Step 4: Render conditionally on the quote PDF**

In `resources/views/admin/quotes/pdf.blade.php`, find the closing line near "Deze offerte is geldig tot de vermelde datum..." (around line 489) and add directly after it:

```blade
            @if (!empty(config('site.contact.company_number')))
                <p>{{ config('site.name') }} — {{ config('site.contact.company_number') }}</p>
            @endif
```

- [ ] **Step 5: Manually verify nothing renders when the config is empty**

Run: `php artisan tinker --execute="echo config('site.contact.company_number') === null ? 'ok: null by default' : 'unexpected value';"`
Expected output: `ok: null by default`

- [ ] **Step 6: Commit**

```bash
git add config/site.php .env.example resources/views/layouts/app.blade.php resources/views/admin/quotes/pdf.blade.php
git commit -m "chore(legal): add optional company number/address placeholders"
```

---

### Task 7: Final verification pass

**Context:** Run the full checklist from the sprint brief before reporting status.

- [ ] **Step 1: Full test suite**

Run: `php artisan test`
Expected: all tests PASS, 0 failures.

- [ ] **Step 2: Frontend build**

Run: `npm run build`
Expected: build succeeds with no errors.

- [ ] **Step 3: Lint changed PHP files**

Run (adjust file list to whatever actually changed):
```powershell
php -l app\Http\Controllers\CustomerRequestController.php
php -l app\Http\Middleware\SecurityHeaders.php
php -l app\Providers\AppServiceProvider.php
php -l database\seeders\AdminUserSeeder.php
```
Expected: `No syntax errors detected` for each.

- [ ] **Step 4: Composer validate**

Run: `composer validate`
Expected: `./composer.json is valid`

- [ ] **Step 5: Route list for touched areas**

Run: `php artisan route:list --path=requests`
Run: `php artisan route:list --path=admin`
Expected: `customer-requests.store`, all `admin.*` routes, and `admin.login.submit` (with throttle) all present.

- [ ] **Step 6: No commit needed — this task only verifies.**
