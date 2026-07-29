# Standard Reply, Request Deletion, Account Settings & VAT Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a localized, idempotent standard-reply email + WhatsApp message to the admin request page; safe hard-deletion of requests (blocked when a quote exists); admin account email/password change; and the VAT number in footer and contact page.

**Architecture:** All mail goes through the existing `App\Services\MailDispatcher` (never throws, returns bool, logs to `mail_logs`). A new static-method service `StandardReplyService` is the single source of truth for the reply text (email, preview, WhatsApp). Idempotency uses an atomic `WHERE standard_reply_sent_at IS NULL` claim on new `customer_requests` columns. Deletion re-checks the quote guard inside a DB transaction and deletes files only after commit.

**Tech Stack:** Laravel 12, PHP 8.2, PHPUnit 11 (`php artisan test`), SQLite `:memory:` tests, Blade views, session-flag admin auth (`session('admin_user_email')`), plain-CSS frontend (no admin JS framework).

**Spec:** `docs/superpowers/specs/2026-07-29-standard-reply-request-management-design.md`

## Global Constraints

- Project root: `C:\Users\duisb\Documents\AWebsiteBuildingBusiness\website-martin`. Git branch `main`, tree clean at start.
- **PowerShell syntax only** (CLAUDE.md): chain with `A; if ($?) { B }`, never `&&`.
- Every **customer-facing** string needs nl/fr/en with `nl` fallback (inline locale arrays — the house idiom; there are no lang files for UI copy). Admin UI copy is Dutch-only (existing convention).
- Never break (CLAUDE.md): `CustomerRequest` storage, uploads via `CustomerRequestAttachment`, request flow routes, `/admin/requests`, `NewCustomerRequestMail`, `CustomerRequestConfirmationMail`, all existing routes in `routes/web.php`.
- Admin auth stays `AdminUser` + `Hash::check()`. No Laravel guard, no `current_password` validation rule (it needs a guard).
- No WhatsApp API — `wa.me` deep links only.
- All new admin routes go **inside** the existing `Route::middleware('admin')->prefix('admin')->name('admin.')` group in `routes/web.php` (lines 26–74).
- Flash convention: `->with('success', '<key>')` where the view maps keys to Dutch text (`.form-success` / `.form-error-list` divs). Even error keys travel via `'success'` (house style, see `quote_email_failed`).
- Feature tests: `use RefreshDatabase;`, admin session via `->withSession(['admin_user_email' => 'admin@test.com'])`, requests built with `CustomerRequest::create()` (no factory exists).
- VAT number rendered exactly as `BE 0760.768.228`.
- Commit after each task. Do not push or deploy.

---

### Task 0: Baseline

- [ ] **Step 1: Verify clean tree and green suite**

Run (PowerShell, from project root):
```powershell
git status --short
php artisan test
```
Expected: empty status; all tests pass. If any test fails at baseline, STOP and report — do not fix unrelated tests silently.

---

### Task 1: VAT number in config, footer, and contact page

**Files:**
- Modify: `config/site.php` (add key after `'name'`, line 4)
- Modify: `resources/views/layouts/app.blade.php` (navLabels arrays lines 7–42; footer contact list lines 371–373)
- Modify: `resources/views/pages/partials/contact-page.blade.php` (labels arrays lines 5–81; contact list lines 96–123)
- Test: `tests/Feature/VatNumberTest.php` (create)

**Interfaces:**
- Produces: `config('site.vat_number')` → `'BE 0760.768.228'` (string). Later tasks/pages may read this key.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/VatNumberTest.php`:

```php
<?php

namespace Tests\Feature;

use Database\Seeders\PageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VatNumberTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PageSeeder::class);
    }

    public function test_config_exposes_vat_number(): void
    {
        $this->assertSame('BE 0760.768.228', config('site.vat_number'));
    }

    public function test_footer_shows_vat_number_on_homepage(): void
    {
        $this->get(route('pages.home', ['locale' => 'nl']))
            ->assertOk()
            ->assertSee('BE 0760.768.228');
    }

    public function test_contact_page_shows_vat_number_in_all_locales(): void
    {
        $this->get(route('pages.show', ['locale' => 'nl', 'slug' => 'contact']))
            ->assertOk()
            ->assertSee('BE 0760.768.228')
            ->assertSee('BTW-nummer');

        $this->get(route('pages.show', ['locale' => 'fr', 'slug' => 'contact']))
            ->assertOk()
            ->assertSee('BE 0760.768.228')
            ->assertSee('Numéro de TVA');

        $this->get(route('pages.show', ['locale' => 'en', 'slug' => 'contact']))
            ->assertOk()
            ->assertSee('BE 0760.768.228')
            ->assertSee('VAT number');
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=VatNumberTest`
Expected: FAIL (config key null, pages don't contain the number).

- [ ] **Step 3: Implement**

In `config/site.php`, directly after `'name' => 'Mastechnics',`:

```php
    'vat_number' => 'BE 0760.768.228',
```

In `resources/views/layouts/app.blade.php`, add to each of the three `$navLabels` locale arrays (after the `'designed_by'` entry):

```php
            'vat_label' => 'BTW',      // nl array
            'vat_label' => 'TVA',      // fr array
            'vat_label' => 'VAT',      // en array
```

In the footer contact `<ul class="footer-list">`, after the existing `company_number` block (lines 371–373):

```blade
                    @if (!empty(config('site.vat_number')))
                    <li>{{ $nav['vat_label'] }} {{ config('site.vat_number') }}</li>
                    @endif
```

In `resources/views/pages/partials/contact-page.blade.php`, add to each `$labels` locale array (after `'messenger'`):

```php
            'vat' => 'BTW-nummer',       // nl
            'vat' => 'Numéro de TVA',    // fr
            'vat' => 'VAT number',       // en
```

In the `<div class="contact-list">`, after the Messenger `contact-item` (line 122's closing `</div>`):

```blade
                    <div class="contact-item">
                        <span>{{ $text['vat'] }}</span>
                        <span>{{ config('site.vat_number') }}</span>
                    </div>
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=VatNumberTest`
Expected: PASS (3 tests).

- [ ] **Step 5: Run full suite, then commit**

```powershell
php artisan test; if ($?) { git add config/site.php resources/views/layouts/app.blade.php resources/views/pages/partials/contact-page.blade.php tests/Feature/VatNumberTest.php; git commit -m "feat(site): show VAT number in footer and contact page" }
```

---

### Task 2: StandardReplyService (single source of truth for reply text)

**Files:**
- Create: `app/Services/StandardReplyService.php`
- Test: `tests/Unit/StandardReplyServiceTest.php` (create)

**Interfaces:**
- Consumes: `config('request-flow.service_categories')` (list of `['value' => ..., 'labels' => ['nl'=>..,'fr'=>..,'en'=>..]]`), `config('site.contact.phone_display')`.
- Produces (used by Tasks 3 and 4):
  - `StandardReplyService::locale(CustomerRequest $customerRequest): string` — `'nl'|'fr'|'en'`
  - `StandardReplyService::subject(CustomerRequest $customerRequest): string`
  - `StandardReplyService::message(CustomerRequest $customerRequest): string` — multi-line plain text
  - `StandardReplyService::whatsappUrl(CustomerRequest $customerRequest): ?string`

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/StandardReplyServiceTest.php`:

```php
<?php

namespace Tests\Unit;

use App\Models\CustomerRequest;
use App\Services\StandardReplyService;
use Tests\TestCase;

class StandardReplyServiceTest extends TestCase
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

    public function test_nl_message_contains_greeting_and_localized_category(): void
    {
        $message = StandardReplyService::message($this->makeRequest());

        $this->assertStringContainsString('Dag Test Klant,', $message);
        $this->assertStringContainsString('Airco laten plaatsen', $message);
        $this->assertStringContainsString('Bedankt voor uw aanvraag', $message);
        $this->assertStringContainsString(config('site.contact.phone_display'), $message);
    }

    public function test_fr_message_is_localized(): void
    {
        $message = StandardReplyService::message($this->makeRequest(['locale' => 'fr']));

        $this->assertStringContainsString('Bonjour Test Klant,', $message);
        $this->assertStringContainsString('Faire installer une climatisation', $message);
        $this->assertStringContainsString('Merci pour votre demande', $message);
    }

    public function test_en_message_is_localized(): void
    {
        $message = StandardReplyService::message($this->makeRequest(['locale' => 'en']));

        $this->assertStringContainsString('Hello Test Klant,', $message);
        $this->assertStringContainsString('Install air conditioning', $message);
        $this->assertStringContainsString('Thank you for your request', $message);
    }

    public function test_unknown_locale_falls_back_to_nl(): void
    {
        $message = StandardReplyService::message($this->makeRequest(['locale' => 'de']));

        $this->assertStringContainsString('Dag Test Klant,', $message);
        $this->assertStringContainsString('Bedankt voor uw aanvraag', $message);
    }

    public function test_unknown_category_omits_category_clause(): void
    {
        $message = StandardReplyService::message(
            $this->makeRequest(['service_category' => 'does_not_exist'])
        );

        $this->assertStringContainsString('Bedankt voor uw aanvraag via Mastechnics.', $message);
        $this->assertStringNotContainsString('does_not_exist', $message);
    }

    public function test_missing_category_omits_category_clause(): void
    {
        $message = StandardReplyService::message(
            $this->makeRequest(['service_category' => null])
        );

        $this->assertStringContainsString('Bedankt voor uw aanvraag via Mastechnics.', $message);
    }

    public function test_subject_is_localized_with_nl_fallback(): void
    {
        $this->assertNotSame(
            StandardReplyService::subject($this->makeRequest(['locale' => 'fr'])),
            StandardReplyService::subject($this->makeRequest(['locale' => 'nl']))
        );

        $this->assertSame(
            StandardReplyService::subject($this->makeRequest(['locale' => 'nl'])),
            StandardReplyService::subject($this->makeRequest(['locale' => 'xx']))
        );
    }

    public function test_whatsapp_url_embeds_exactly_the_same_message(): void
    {
        $request = $this->makeRequest(['customer_phone' => '0495 12 11 78']);

        $url = StandardReplyService::whatsappUrl($request);

        $this->assertNotNull($url);
        $this->assertStringStartsWith('https://wa.me/32495121178?text=', $url);
        $this->assertStringContainsString(
            rawurlencode(StandardReplyService::message($request)),
            $url
        );
    }

    public function test_whatsapp_url_is_null_without_usable_phone(): void
    {
        $this->assertNull(StandardReplyService::whatsappUrl($this->makeRequest(['customer_phone' => null])));
        $this->assertNull(StandardReplyService::whatsappUrl($this->makeRequest(['customer_phone' => '12'])));
    }

    public function test_phone_normalization_handles_international_prefixes(): void
    {
        $plus = StandardReplyService::whatsappUrl($this->makeRequest(['customer_phone' => '+32 495/12.11.78']));
        $zeros = StandardReplyService::whatsappUrl($this->makeRequest(['customer_phone' => '0032495121178']));

        $this->assertStringStartsWith('https://wa.me/32495121178?text=', $plus);
        $this->assertStringStartsWith('https://wa.me/32495121178?text=', $zeros);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=StandardReplyServiceTest`
Expected: FAIL — `Class "App\Services\StandardReplyService" not found`.

- [ ] **Step 3: Implement the service**

Create `app/Services/StandardReplyService.php`:

```php
<?php

namespace App\Services;

use App\Models\CustomerRequest;

class StandardReplyService
{
    private const SUPPORTED_LOCALES = ['nl', 'fr', 'en'];

    private const TEXTS = [
        'nl' => [
            'subject' => 'Uw aanvraag bij Mastechnics — wij nemen binnenkort contact op',
            'greeting' => 'Dag :name,',
            'intro_with_category' => 'Bedankt voor uw aanvraag (:category) via Mastechnics.',
            'intro_without_category' => 'Bedankt voor uw aanvraag via Mastechnics.',
            'body' => 'We hebben uw aanvraag goed ontvangen en nemen zo snel mogelijk contact met u op om de details te bespreken.',
            'questions' => 'Heeft u intussen vragen? U kan ons bereiken op :phone.',
            'signoff' => "Met vriendelijke groeten\nMastechnics",
        ],
        'fr' => [
            'subject' => 'Votre demande chez Mastechnics — nous vous contactons bientôt',
            'greeting' => 'Bonjour :name,',
            'intro_with_category' => 'Merci pour votre demande (:category) via Mastechnics.',
            'intro_without_category' => 'Merci pour votre demande via Mastechnics.',
            'body' => 'Nous avons bien reçu votre demande et nous vous contacterons au plus vite pour discuter des détails.',
            'questions' => 'Vous avez des questions entre-temps ? Vous pouvez nous joindre au :phone.',
            'signoff' => "Cordialement\nMastechnics",
        ],
        'en' => [
            'subject' => 'Your request at Mastechnics — we will contact you soon',
            'greeting' => 'Hello :name,',
            'intro_with_category' => 'Thank you for your request (:category) via Mastechnics.',
            'intro_without_category' => 'Thank you for your request via Mastechnics.',
            'body' => 'We have received your request and will contact you as soon as possible to discuss the details.',
            'questions' => 'Any questions in the meantime? You can reach us at :phone.',
            'signoff' => "Kind regards\nMastechnics",
        ],
    ];

    public static function locale(CustomerRequest $customerRequest): string
    {
        $locale = $customerRequest->locale ?? 'nl';

        return in_array($locale, self::SUPPORTED_LOCALES, true) ? $locale : 'nl';
    }

    public static function subject(CustomerRequest $customerRequest): string
    {
        return self::TEXTS[self::locale($customerRequest)]['subject'];
    }

    public static function message(CustomerRequest $customerRequest): string
    {
        $locale = self::locale($customerRequest);
        $text = self::TEXTS[$locale];

        $category = self::categoryLabel($customerRequest, $locale);

        $intro = $category !== null
            ? str_replace(':category', $category, $text['intro_with_category'])
            : $text['intro_without_category'];

        $greeting = str_replace(':name', (string) $customerRequest->customer_name, $text['greeting']);
        $questions = str_replace(':phone', (string) config('site.contact.phone_display'), $text['questions']);

        return $greeting . "\n\n"
            . $intro . ' ' . $text['body'] . "\n\n"
            . $questions . "\n\n"
            . $text['signoff'];
    }

    public static function whatsappUrl(CustomerRequest $customerRequest): ?string
    {
        $number = self::normalizePhone($customerRequest->customer_phone);

        if ($number === null) {
            return null;
        }

        return 'https://wa.me/' . $number . '?text=' . rawurlencode(self::message($customerRequest));
    }

    private static function categoryLabel(CustomerRequest $customerRequest, string $locale): ?string
    {
        if (! $customerRequest->service_category) {
            return null;
        }

        foreach (config('request-flow.service_categories', []) as $category) {
            if (($category['value'] ?? null) === $customerRequest->service_category) {
                return $category['labels'][$locale] ?? $category['labels']['nl'] ?? null;
            }
        }

        return null;
    }

    private static function normalizePhone(?string $phone): ?string
    {
        if ($phone === null || trim($phone) === '') {
            return null;
        }

        $number = preg_replace('/[\s\.\-\/\(\)]/', '', trim($phone));

        if (str_starts_with($number, '+')) {
            $number = substr($number, 1);
        } elseif (str_starts_with($number, '00')) {
            $number = substr($number, 2);
        } elseif (str_starts_with($number, '0')) {
            $number = '32' . substr($number, 1);
        }

        $number = preg_replace('/\D/', '', $number);

        return strlen($number) > 5 ? $number : null;
    }
}
```

Note: the phone normalization is intentionally identical to the logic currently inlined in `resources/views/admin/requests/show.blade.php:116-134` (it moves to PHP here; the Blade copy is removed in Task 4).

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=StandardReplyServiceTest`
Expected: PASS (10 tests).

- [ ] **Step 5: Commit**

```powershell
git add app/Services/StandardReplyService.php tests/Unit/StandardReplyServiceTest.php; git commit -m "feat(admin): add StandardReplyService as single source of reply text"
```

---

### Task 3: Tracking columns, StandardReplyMail, email view

**Files:**
- Create: migration `add_standard_reply_fields_to_customer_requests_table` (via artisan)
- Modify: `app/Models/CustomerRequest.php` (`$fillable` lines 11–39, `$casts` lines 41–51)
- Create: `app/Mail/StandardReplyMail.php`
- Create: `resources/views/emails/customer/standard-reply.blade.php`
- Test: `tests/Unit/StandardReplyMailTest.php` (create)

**Interfaces:**
- Consumes: `StandardReplyService::{locale,subject,message}` from Task 2.
- Produces (used by Task 4):
  - Columns `customer_requests.standard_reply_sent_at` (nullable timestamp, datetime cast) and `customer_requests.standard_reply_sent_by` (nullable string), both fillable.
  - `new StandardReplyMail(CustomerRequest $customerRequest)` — envelope subject `StandardReplyService::subject()`, view `emails.customer.standard-reply`.

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/StandardReplyMailTest.php`:

```php
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
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=StandardReplyMailTest`
Expected: FAIL — `Class "App\Mail\StandardReplyMail" not found`.

- [ ] **Step 3: Create the migration**

```powershell
php artisan make:migration add_standard_reply_fields_to_customer_requests_table
```

Fill the generated file in `database/migrations/`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_requests', function (Blueprint $table): void {
            $table->timestamp('standard_reply_sent_at')->nullable()->after('viewed_at');
            $table->string('standard_reply_sent_by')->nullable()->after('standard_reply_sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('customer_requests', function (Blueprint $table): void {
            $table->dropColumn(['standard_reply_sent_at', 'standard_reply_sent_by']);
        });
    }
};
```

- [ ] **Step 4: Update the model**

In `app/Models/CustomerRequest.php` add to `$fillable` (after `'viewed_at',`):

```php
        'standard_reply_sent_at',
        'standard_reply_sent_by',
```

Add to `$casts` (after the `'viewed_at' => 'datetime',` entry):

```php
        'standard_reply_sent_at' => 'datetime',
```

- [ ] **Step 5: Create the Mailable**

Create `app/Mail/StandardReplyMail.php` (mirrors `CustomerRequestConfirmationMail`):

```php
<?php

namespace App\Mail;

use App\Models\CustomerRequest;
use App\Services\StandardReplyService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class StandardReplyMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public CustomerRequest $customerRequest
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: StandardReplyService::subject($this->customerRequest)
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.customer.standard-reply',
            with: [
                'customerRequest' => $this->customerRequest,
                'messageText' => StandardReplyService::message($this->customerRequest),
                'emailLocale' => StandardReplyService::locale($this->customerRequest),
            ],
        );
    }
}
```

- [ ] **Step 6: Create the email view**

Create `resources/views/emails/customer/standard-reply.blade.php`:

```blade
@php
    $headings = [
        'nl' => 'We nemen binnenkort contact op',
        'fr' => 'Nous vous contactons bientôt',
        'en' => 'We will contact you soon',
    ];
    $heading = $headings[$emailLocale] ?? $headings['nl'];
@endphp

@extends('emails.layout', ['emailLocale' => $emailLocale])

@section('subject', $heading)
@section('heading', $heading)

@section('content')
    <div style="font-size: 16px; line-height: 1.6; color: #405163; white-space: pre-line;">{{ $messageText }}</div>
@endsection
```

The message already opens with the localized greeting — do not add a separate "Beste …" line. `{{ }}` escaping is mandatory (no `{!! !!}` anywhere).

- [ ] **Step 7: Run tests, migrate, verify**

```powershell
php artisan test --filter=StandardReplyMailTest
php artisan migrate
```
Expected: 4 tests PASS; migration runs cleanly on the local dev DB.

- [ ] **Step 8: Run full suite, then commit**

```powershell
php artisan test; if ($?) { git add app/Mail/StandardReplyMail.php app/Models/CustomerRequest.php resources/views/emails/customer/standard-reply.blade.php database/migrations tests/Unit/StandardReplyMailTest.php; git commit -m "feat(admin): add standard reply mailable and tracking columns" }
```

---

### Task 4: Send/resend actions, routes, and admin UI

**Files:**
- Modify: `app/Http/Controllers/Admin/RequestController.php` (add two actions after `performAction`, line ~354; add imports)
- Modify: `routes/web.php` (inside admin group, after the `requests.action` route, line ~55)
- Modify: `resources/views/admin/requests/show.blade.php` (`@php` block lines 116–134; flash blocks after line 206; "Snel bericht" block lines 274–292)
- Test: `tests/Feature/Admin/StandardReplyTest.php` (create)

**Interfaces:**
- Consumes: `StandardReplyMail`, `StandardReplyService::{message,whatsappUrl}`, `MailDispatcher::send(string $recipient, Mailable $mailable, ?CustomerRequest $customerRequest): bool`, `applyMarkContacted(CustomerRequest): void` (existing private, line 379), columns from Task 3.
- Produces: routes `admin.requests.standard-reply.send` and `admin.requests.standard-reply.resend` (both POST); flash keys `standard_reply_sent`, `standard_reply_resent`, `standard_reply_already_sent`, `standard_reply_not_sent_yet`, `standard_reply_in_progress`, `standard_reply_failed`.

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/Admin/StandardReplyTest.php`:

```php
<?php

namespace Tests\Feature\Admin;

use App\Mail\StandardReplyMail;
use App\Models\CustomerRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class StandardReplyTest extends TestCase
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

    public function test_send_sends_mail_marks_sent_and_sets_contacted(): void
    {
        Mail::fake();
        $request = $this->makeRequest();

        $response = $this->withSession($this->adminSession())
            ->post(route('admin.requests.standard-reply.send', $request));

        $response->assertRedirect()->assertSessionHas('success', 'standard_reply_sent');
        Mail::assertSent(StandardReplyMail::class, 1);

        $fresh = $request->fresh();
        $this->assertNotNull($fresh->standard_reply_sent_at);
        $this->assertSame('admin@test.com', $fresh->standard_reply_sent_by);
        $this->assertSame('contacted', $fresh->status);
        $this->assertNotNull($fresh->contacted_at);

        $this->assertDatabaseHas('mail_logs', [
            'customer_request_id' => $request->id,
            'mailable' => 'StandardReplyMail',
            'recipient' => 'klant@example.com',
            'status' => 'sent',
        ]);
    }

    public function test_duplicate_post_sends_exactly_once(): void
    {
        Mail::fake();
        $request = $this->makeRequest();

        $this->withSession($this->adminSession())
            ->post(route('admin.requests.standard-reply.send', $request));
        $this->withSession($this->adminSession())
            ->post(route('admin.requests.standard-reply.send', $request))
            ->assertSessionHas('success', 'standard_reply_already_sent');

        Mail::assertSent(StandardReplyMail::class, 1);
    }

    public function test_send_route_never_resends_when_already_sent(): void
    {
        Mail::fake();
        $sentAt = now()->subDay();
        $request = $this->makeRequest([
            'standard_reply_sent_at' => $sentAt,
            'standard_reply_sent_by' => 'old@test.com',
        ]);

        $this->withSession($this->adminSession())
            ->post(route('admin.requests.standard-reply.send', $request))
            ->assertSessionHas('success', 'standard_reply_already_sent');

        Mail::assertNotSent(StandardReplyMail::class);
        $this->assertSame('old@test.com', $request->fresh()->standard_reply_sent_by);
    }

    public function test_resend_requires_prior_send(): void
    {
        Mail::fake();
        $request = $this->makeRequest();

        $this->withSession($this->adminSession())
            ->post(route('admin.requests.standard-reply.resend', $request))
            ->assertSessionHas('success', 'standard_reply_not_sent_yet');

        Mail::assertNotSent(StandardReplyMail::class);
    }

    public function test_resend_sends_again_and_updates_stamp(): void
    {
        Mail::fake();
        $request = $this->makeRequest([
            'standard_reply_sent_at' => now()->subDay(),
            'standard_reply_sent_by' => 'old@test.com',
            'status' => 'contacted',
            'contacted_at' => now()->subDay(),
        ]);

        $this->withSession($this->adminSession())
            ->post(route('admin.requests.standard-reply.resend', $request))
            ->assertSessionHas('success', 'standard_reply_resent');

        Mail::assertSent(StandardReplyMail::class, 1);

        $fresh = $request->fresh();
        $this->assertTrue($fresh->standard_reply_sent_at->greaterThan(now()->subHour()));
        $this->assertSame('admin@test.com', $fresh->standard_reply_sent_by);
    }

    public function test_failed_mail_does_not_mark_sent_or_contacted(): void
    {
        Mail::shouldReceive('to->send')->andThrow(new \RuntimeException('SMTP down'));
        $request = $this->makeRequest();

        $this->withSession($this->adminSession())
            ->post(route('admin.requests.standard-reply.send', $request))
            ->assertSessionHas('success', 'standard_reply_failed');

        $fresh = $request->fresh();
        $this->assertNull($fresh->standard_reply_sent_at);
        $this->assertNull($fresh->standard_reply_sent_by);
        $this->assertSame('new', $fresh->status);
        $this->assertNull($fresh->contacted_at);

        $this->assertDatabaseHas('mail_logs', [
            'customer_request_id' => $request->id,
            'status' => 'failed',
        ]);
    }

    public function test_mail_log_failure_does_not_cause_second_send(): void
    {
        Mail::fake();
        $request = $this->makeRequest();
        Schema::dropIfExists('mail_logs');

        $this->withSession($this->adminSession())
            ->post(route('admin.requests.standard-reply.send', $request))
            ->assertSessionHas('success', 'standard_reply_sent');

        Mail::assertSent(StandardReplyMail::class, 1);
        $this->assertNotNull($request->fresh()->standard_reply_sent_at);
    }

    public function test_send_does_not_regress_status_beyond_contacted(): void
    {
        Mail::fake();
        $request = $this->makeRequest(['status' => 'won', 'won_at' => now()]);

        $this->withSession($this->adminSession())
            ->post(route('admin.requests.standard-reply.send', $request));

        $fresh = $request->fresh();
        $this->assertSame('won', $fresh->status);
        $this->assertNotNull($fresh->contacted_at);
        $this->assertNotNull($fresh->standard_reply_sent_at);
    }

    public function test_unauthenticated_cannot_send_or_resend(): void
    {
        Mail::fake();
        $request = $this->makeRequest();

        $this->post(route('admin.requests.standard-reply.send', $request))
            ->assertRedirect(route('admin.login'));
        $this->post(route('admin.requests.standard-reply.resend', $request))
            ->assertRedirect(route('admin.login'));

        Mail::assertNothingSent();
        $this->assertNull($request->fresh()->standard_reply_sent_at);
    }

    public function test_show_page_renders_standard_reply_block(): void
    {
        $request = $this->makeRequest();

        $this->withSession($this->adminSession())
            ->get(route('admin.requests.show', $request))
            ->assertOk()
            ->assertSee('Standaardantwoord')
            ->assertSee('Verstuur per e-mail')
            ->assertSee('Dag Test Klant,');
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=StandardReplyTest`
Expected: FAIL — route `admin.requests.standard-reply.send` not defined.

- [ ] **Step 3: Add routes**

In `routes/web.php`, inside the admin group directly after the `requests.action` route (line 54–55):

```php
        Route::post('/requests/{customerRequest}/standard-reply', [AdminRequestController::class, 'sendStandardReply'])
            ->name('requests.standard-reply.send');

        Route::post('/requests/{customerRequest}/standard-reply/resend', [AdminRequestController::class, 'resendStandardReply'])
            ->name('requests.standard-reply.resend');
```

- [ ] **Step 4: Add controller actions**

In `app/Http/Controllers/Admin/RequestController.php`, add imports:

```php
use App\Mail\StandardReplyMail;
use App\Services\MailDispatcher;
use Illuminate\Support\Facades\Cache;
```

Add after `performAction()` (before `updateInternalNotes`):

```php
    public function sendStandardReply(CustomerRequest $customerRequest): RedirectResponse
    {
        if (! $customerRequest->customer_email) {
            return back()->with('success', 'standard_reply_failed');
        }

        // Atomic claim: only one request may pass while the column is NULL,
        // so rapid duplicate POSTs can never trigger a second send.
        $claimed = CustomerRequest::whereKey($customerRequest->id)
            ->whereNull('standard_reply_sent_at')
            ->update([
                'standard_reply_sent_at' => now(),
                'standard_reply_sent_by' => session('admin_user_email'),
            ]);

        if ($claimed === 0) {
            return back()->with('success', 'standard_reply_already_sent');
        }

        $customerRequest->refresh();

        $sent = MailDispatcher::send(
            $customerRequest->customer_email,
            new StandardReplyMail($customerRequest),
            $customerRequest
        );

        if (! $sent) {
            // Release the claim so the reply is not marked sent and can be retried.
            $customerRequest->update([
                'standard_reply_sent_at' => null,
                'standard_reply_sent_by' => null,
            ]);

            return back()->with('success', 'standard_reply_failed');
        }

        if (in_array($customerRequest->status, ['new', 'viewed'], true)) {
            $this->applyMarkContacted($customerRequest);
        } elseif ($customerRequest->contacted_at === null) {
            $customerRequest->update(['contacted_at' => now()]);
        }

        return back()->with('success', 'standard_reply_sent');
    }

    public function resendStandardReply(CustomerRequest $customerRequest): RedirectResponse
    {
        if ($customerRequest->standard_reply_sent_at === null) {
            return back()->with('success', 'standard_reply_not_sent_yet');
        }

        $lock = Cache::lock('standard-reply-resend-' . $customerRequest->id, 15);

        if (! $lock->get()) {
            return back()->with('success', 'standard_reply_in_progress');
        }

        try {
            $sent = MailDispatcher::send(
                $customerRequest->customer_email,
                new StandardReplyMail($customerRequest),
                $customerRequest
            );

            if (! $sent) {
                return back()->with('success', 'standard_reply_failed');
            }

            $customerRequest->update([
                'standard_reply_sent_at' => now(),
                'standard_reply_sent_by' => session('admin_user_email'),
            ]);

            return back()->with('success', 'standard_reply_resent');
        } finally {
            $lock->release();
        }
    }
```

Note: `MailDispatcher::send()` never throws and already isolates `mail_logs` write failures from the send outcome — a logging failure returns `true` when the mail went out, so the claim stays and no second send happens.

- [ ] **Step 5: Update the show view**

In `resources/views/admin/requests/show.blade.php`:

**(a)** Replace the entire `// WhatsApp URL` block in the `@php` section (lines 116–134, from `$waUrl = null;` through the closing `}` before `@endphp`) with:

```php
        // Standard reply: one generated text shared by email, preview and WhatsApp.
        $standardReplyMessage = \App\Services\StandardReplyService::message($customerRequest);
        $waUrl = \App\Services\StandardReplyService::whatsappUrl($customerRequest);
```

**(b)** After the `appointment_created` flash block (line ~206), add:

```blade
            @if (session('success') === 'standard_reply_sent')
                <div class="form-success">Standaardantwoord werd verstuurd naar de klant. De status staat nu op "Gecontacteerd".</div>
            @endif

            @if (session('success') === 'standard_reply_resent')
                <div class="form-success">Standaardantwoord werd opnieuw verstuurd naar de klant.</div>
            @endif

            @if (session('success') === 'standard_reply_already_sent')
                <div class="form-error-list">Het standaardantwoord werd eerder al verstuurd. Gebruik "Opnieuw versturen" om het nogmaals te versturen.</div>
            @endif

            @if (session('success') === 'standard_reply_not_sent_yet')
                <div class="form-error-list">Het standaardantwoord werd nog niet verstuurd. Gebruik de knop "Verstuur per e-mail".</div>
            @endif

            @if (session('success') === 'standard_reply_in_progress')
                <div class="form-error-list">Er is al een verzending bezig. Probeer zo dadelijk opnieuw.</div>
            @endif

            @if (session('success') === 'standard_reply_failed')
                <div class="form-error-list">Het standaardantwoord kon niet verstuurd worden. Controleer het e-mailadres of probeer later opnieuw.</div>
            @endif
```

**(c)** Replace the "Snel bericht" block — the `@php` at lines 274–278 **and** the `<div class="admin-snel-bericht">…</div>` at lines 280–292 — with:

```blade
                        <div class="admin-snel-bericht">
                            <h3>Standaardantwoord</h3>
                            <p id="admin-snel-bericht-text" class="admin-snel-bericht-content" style="white-space: pre-line;">{{ $standardReplyMessage }}</p>
                            <button
                                type="button"
                                class="button button-secondary admin-copy-btn"
                                data-copy-target="admin-snel-bericht-text"
                                aria-label="Bericht kopiëren"
                            >
                                Kopiëren
                            </button>
                            <span class="admin-copy-feedback" aria-live="polite"></span>

                            @if ($customerRequest->standard_reply_sent_at === null)
                                <form method="POST" action="{{ route('admin.requests.standard-reply.send', $customerRequest) }}"
                                    style="margin-top: 12px;"
                                    onsubmit="this.querySelector('button[type=submit]').disabled = true; return true;">
                                    @csrf
                                    <button type="submit" class="button button-primary">Verstuur per e-mail</button>
                                </form>
                            @else
                                <p style="margin-top: 12px; font-size: 13px; color: #6b7c8f;">
                                    Verstuurd op {{ $customerRequest->standard_reply_sent_at->format('d/m/Y H:i') }}
                                    @if ($customerRequest->standard_reply_sent_by)
                                        door {{ $customerRequest->standard_reply_sent_by }}
                                    @endif
                                </p>
                                <form method="POST" action="{{ route('admin.requests.standard-reply.resend', $customerRequest) }}"
                                    onsubmit="if (!confirm('Dit standaardantwoord werd al verstuurd. Opnieuw versturen?')) { return false; } this.querySelector('button[type=submit]').disabled = true; return true;">
                                    @csrf
                                    <button type="submit" class="button button-secondary">Opnieuw versturen</button>
                                </form>
                            @endif
                        </div>
```

All output uses `{{ }}` escaping; the WhatsApp quick link at lines 219–224 keeps working unchanged (it now carries the full standard reply text via `$waUrl`).

- [ ] **Step 6: Run tests to verify they pass**

Run: `php artisan test --filter=StandardReplyTest`
Expected: PASS (10 tests).

- [ ] **Step 7: Run full suite, then commit**

```powershell
php artisan test; if ($?) { git add app/Http/Controllers/Admin/RequestController.php routes/web.php resources/views/admin/requests/show.blade.php tests/Feature/Admin/StandardReplyTest.php; git commit -m "feat(admin): add idempotent standard reply send and resend actions" }
```

---

### Task 5: Safe request deletion

**Files:**
- Modify: `app/Http/Controllers/Admin/RequestController.php` (add `destroy` after `resendStandardReply`; add imports)
- Modify: `routes/web.php` (inside admin group)
- Modify: `resources/views/admin/requests/show.blade.php` (delete button in quick-actions card; `delete_blocked_quote` flash)
- Modify: `resources/views/admin/requests/index.blade.php` (flash block after line 252 `<div class="container">`)
- Test: `tests/Feature/Admin/RequestDeleteTest.php` (create)

**Interfaces:**
- Consumes: `CustomerRequest::quote(): HasOne`, `CustomerRequest::attachments(): HasMany` (paths stored like `customer-requests/<hash>.<ext>` on disk `public`), DB cascades (`customer_request_attachments`/`notes`/`appointments` cascade; `mail_logs` nullOnDelete).
- Produces: route `admin.requests.destroy` (DELETE `/admin/requests/{customerRequest}`); flash keys `request_deleted` (index) and `delete_blocked_quote` (show).

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/Admin/RequestDeleteTest.php`:

```php
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
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=RequestDeleteTest`
Expected: FAIL — route `admin.requests.destroy` not defined.

- [ ] **Step 3: Add the route**

In `routes/web.php`, inside the admin group after the standard-reply routes from Task 4:

```php
        Route::delete('/requests/{customerRequest}', [AdminRequestController::class, 'destroy'])
            ->name('requests.destroy');
```

- [ ] **Step 4: Add the controller action**

In `app/Http/Controllers/Admin/RequestController.php`, add imports:

```php
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
```

(Check the existing `use` block first — add only what is missing.)

Add after `resendStandardReply()`:

```php
    public function destroy(CustomerRequest $customerRequest): RedirectResponse
    {
        // Collect file paths before any DB row is removed.
        $attachmentPaths = $customerRequest->attachments()->pluck('path')->all();

        $deleted = DB::transaction(function () use ($customerRequest): bool {
            // Guard inside the transaction: the quotes FK cascades on delete,
            // so a quote created after page load must still block deletion.
            if ($customerRequest->quote()->exists()) {
                return false;
            }

            $customerRequest->delete();

            return true;
        });

        if (! $deleted) {
            return back()->with('success', 'delete_blocked_quote');
        }

        // Files are removed only after the DB commit; failures leave orphan
        // files (logged) but never a half-deleted request.
        foreach ($attachmentPaths as $path) {
            if (! is_string($path)
                || $path === ''
                || str_contains($path, '..')
                || ! str_starts_with($path, 'customer-requests/')) {
                Log::warning('Attachment path outside expected directory skipped during delete', [
                    'path' => $path,
                ]);

                continue;
            }

            try {
                Storage::disk('public')->delete($path);
            } catch (\Throwable $e) {
                Log::error('Attachment file delete failed', [
                    'path' => $path,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return redirect()
            ->route('admin.requests.index')
            ->with('success', 'request_deleted');
    }
```

- [ ] **Step 5: Add the UI**

In `resources/views/admin/requests/show.blade.php`:

**(a)** At the end of the quick-actions card, directly after the closing `</div>` of the `admin-snel-bericht` block (before the card's closing `</div>` at what was line 293), add:

```blade
                        @if ($customerRequest->quote === null)
                            <form method="POST" action="{{ route('admin.requests.destroy', $customerRequest) }}"
                                style="margin-top: 16px;"
                                onsubmit="if (!confirm('Deze aanvraag definitief verwijderen? Bijlagen worden mee verwijderd. Dit kan niet ongedaan gemaakt worden.')) { return false; } this.querySelector('button[type=submit]').disabled = true; return true;">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="admin-quick-action-btn admin-quick-action-lost">Aanvraag verwijderen</button>
                            </form>
                        @else
                            <p style="margin-top: 16px; font-size: 13px; color: #6b7c8f;">
                                Verwijderen is niet mogelijk zolang er een offerte aan deze aanvraag gekoppeld is.
                            </p>
                        @endif
```

**(b)** Add to the flash blocks (next to the standard-reply flashes from Task 4):

```blade
            @if (session('success') === 'delete_blocked_quote')
                <div class="form-error-list">Deze aanvraag kan niet verwijderd worden zolang er een offerte aan gekoppeld is. Verwijder eerst de offerte.</div>
            @endif
```

In `resources/views/admin/requests/index.blade.php`, directly after `<div class="container">` (line 252):

```blade
            @if (session('success') === 'request_deleted')
                <div class="form-success">Aanvraag werd definitief verwijderd.</div>
            @endif
```

- [ ] **Step 6: Run tests to verify they pass**

Run: `php artisan test --filter=RequestDeleteTest`
Expected: PASS (6 tests).

- [ ] **Step 7: Run full suite, then commit**

```powershell
php artisan test; if ($?) { git add app/Http/Controllers/Admin/RequestController.php routes/web.php resources/views/admin/requests/show.blade.php resources/views/admin/requests/index.blade.php tests/Feature/Admin/RequestDeleteTest.php; git commit -m "feat(admin): add safe request deletion with quote guard and file cleanup" }
```

---

### Task 6: Admin account settings (email + password change)

**Files:**
- Create: `app/Http/Controllers/Admin/AccountController.php`
- Modify: `routes/web.php` (inside admin group; add `use App\Http\Controllers\Admin\AccountController as AdminAccountController;` import)
- Create: `resources/views/admin/account/edit.blade.php`
- Modify: `resources/views/layouts/app.blade.php` (footer admin actions, lines 410–423)
- Test: `tests/Feature/Admin/AccountSettingsTest.php` (create)

**Interfaces:**
- Consumes: `AdminUser` model (`$fillable = ['name','email','password']`, **no** hashed cast — explicit `Hash::make`), `session('admin_user_email')`, `Hash::check()`.
- Produces: routes `admin.account.edit` (GET `/admin/account`), `admin.account.email.update` (PATCH `/admin/account/email`), `admin.account.password.update` (PATCH `/admin/account/password`); flash keys `account_email_updated`, `account_password_updated`.

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/Admin/AccountSettingsTest.php`:

```php
<?php

namespace Tests\Feature\Admin;

use App\Models\AdminUser;
use App\Models\CustomerRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AccountSettingsTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(): AdminUser
    {
        return AdminUser::create([
            'name' => 'Admin',
            'email' => 'admin@test.com',
            'password' => Hash::make('CorrectHorse123!'),
        ]);
    }

    private function adminSession(): array
    {
        return ['admin_user_email' => 'admin@test.com', 'admin_user_name' => 'Admin'];
    }

    public function test_account_page_renders(): void
    {
        $this->makeAdmin();

        $this->withSession($this->adminSession())
            ->get(route('admin.account.edit'))
            ->assertOk()
            ->assertSee('E-mailadres wijzigen')
            ->assertSee('Wachtwoord wijzigen');
    }

    public function test_email_change_updates_database_and_session(): void
    {
        $admin = $this->makeAdmin();

        $response = $this->withSession($this->adminSession())
            ->patch(route('admin.account.email.update'), [
                'email' => 'nieuw@test.com',
                'current_password' => 'CorrectHorse123!',
            ]);

        $response->assertRedirect()->assertSessionHas('success', 'account_email_updated');
        $this->assertSame('nieuw@test.com', $admin->fresh()->email);
        $this->assertSame('nieuw@test.com', session('admin_user_email'));
    }

    public function test_email_change_rejects_wrong_current_password(): void
    {
        $admin = $this->makeAdmin();

        $this->withSession($this->adminSession())
            ->patch(route('admin.account.email.update'), [
                'email' => 'nieuw@test.com',
                'current_password' => 'wrong-password',
            ])
            ->assertSessionHasErrors('current_password');

        $this->assertSame('admin@test.com', $admin->fresh()->email);
        $this->assertSame('admin@test.com', session('admin_user_email'));
    }

    public function test_email_change_preserves_historical_standard_reply_sender(): void
    {
        $this->makeAdmin();
        $request = CustomerRequest::create([
            'locale' => 'nl',
            'service_slug' => 'airco',
            'request_type' => 'installation',
            'customer_name' => 'Test Klant',
            'customer_email' => 'klant@example.com',
            'description' => 'Test aanvraag',
            'status' => 'contacted',
            'standard_reply_sent_at' => now()->subDay(),
            'standard_reply_sent_by' => 'admin@test.com',
        ]);

        $this->withSession($this->adminSession())
            ->patch(route('admin.account.email.update'), [
                'email' => 'nieuw@test.com',
                'current_password' => 'CorrectHorse123!',
            ]);

        $this->assertSame('admin@test.com', $request->fresh()->standard_reply_sent_by);
    }

    public function test_email_change_rejects_duplicate_email(): void
    {
        $this->makeAdmin();
        AdminUser::create([
            'name' => 'Other',
            'email' => 'other@test.com',
            'password' => Hash::make('SomethingElse123!'),
        ]);

        $this->withSession($this->adminSession())
            ->patch(route('admin.account.email.update'), [
                'email' => 'other@test.com',
                'current_password' => 'CorrectHorse123!',
            ])
            ->assertSessionHasErrors('email');
    }

    public function test_password_change_stores_hashed_password(): void
    {
        $admin = $this->makeAdmin();

        $response = $this->withSession($this->adminSession())
            ->patch(route('admin.account.password.update'), [
                'current_password' => 'CorrectHorse123!',
                'password' => 'NewSecurePass456!',
                'password_confirmation' => 'NewSecurePass456!',
            ]);

        $response->assertRedirect()->assertSessionHas('success', 'account_password_updated');

        $fresh = $admin->fresh();
        $this->assertNotSame('NewSecurePass456!', $fresh->password);
        $this->assertTrue(Hash::check('NewSecurePass456!', $fresh->password));
    }

    public function test_password_change_rejects_wrong_current_password(): void
    {
        $admin = $this->makeAdmin();

        $this->withSession($this->adminSession())
            ->patch(route('admin.account.password.update'), [
                'current_password' => 'wrong-password',
                'password' => 'NewSecurePass456!',
                'password_confirmation' => 'NewSecurePass456!',
            ])
            ->assertSessionHasErrors('current_password');

        $this->assertTrue(Hash::check('CorrectHorse123!', $admin->fresh()->password));
    }

    public function test_password_change_requires_confirmation_and_min_length(): void
    {
        $this->makeAdmin();

        $this->withSession($this->adminSession())
            ->patch(route('admin.account.password.update'), [
                'current_password' => 'CorrectHorse123!',
                'password' => 'short',
                'password_confirmation' => 'mismatch',
            ])
            ->assertSessionHasErrors('password');
    }

    public function test_unauthenticated_cannot_access_account_routes(): void
    {
        $this->makeAdmin();

        $this->get(route('admin.account.edit'))->assertRedirect(route('admin.login'));
        $this->patch(route('admin.account.email.update'), [])->assertRedirect(route('admin.login'));
        $this->patch(route('admin.account.password.update'), [])->assertRedirect(route('admin.login'));
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=AccountSettingsTest`
Expected: FAIL — route `admin.account.edit` not defined.

- [ ] **Step 3: Add routes**

In `routes/web.php`, add the import at the top with the other Admin imports:

```php
use App\Http\Controllers\Admin\AccountController as AdminAccountController;
```

Inside the admin group (after the quote routes, before the group's closing `});`):

```php
        Route::get('/account', [AdminAccountController::class, 'edit'])
            ->name('account.edit');

        Route::patch('/account/email', [AdminAccountController::class, 'updateEmail'])
            ->name('account.email.update');

        Route::patch('/account/password', [AdminAccountController::class, 'updatePassword'])
            ->name('account.password.update');
```

- [ ] **Step 4: Create the controller**

Create `app/Http/Controllers/Admin/AccountController.php`:

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AccountController extends Controller
{
    public function edit(): View
    {
        return view('admin.account.edit');
    }

    public function updateEmail(Request $request): RedirectResponse
    {
        $adminUser = $this->currentAdmin();

        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('admin_users', 'email')->ignore($adminUser->id),
            ],
        ]);

        if (! Hash::check($validated['current_password'], $adminUser->password)) {
            return back()
                ->withErrors(['current_password' => 'Het huidige wachtwoord is niet correct.'])
                ->onlyInput('email');
        }

        $adminUser->update(['email' => $validated['email']]);

        // Keep the active session working; historical records such as
        // standard_reply_sent_by and note author_email keep the old address.
        session(['admin_user_email' => $adminUser->email]);

        return back()->with('success', 'account_email_updated');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $adminUser = $this->currentAdmin();

        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:12', 'confirmed'],
        ]);

        if (! Hash::check($validated['current_password'], $adminUser->password)) {
            return back()->withErrors(['current_password' => 'Het huidige wachtwoord is niet correct.']);
        }

        $adminUser->update(['password' => Hash::make($validated['password'])]);

        $request->session()->regenerate();

        return back()->with('success', 'account_password_updated');
    }

    private function currentAdmin(): AdminUser
    {
        $adminUser = AdminUser::where('email', session('admin_user_email'))->first();

        abort_if($adminUser === null, 403);

        return $adminUser;
    }
}
```

- [ ] **Step 5: Create the view**

Create `resources/views/admin/account/edit.blade.php` (match the label/input markup of `resources/views/admin/auth/login.blade.php`):

```blade
@extends('layouts.app')

@section('title', 'Admin | Account')

@section('content')
    <section class="admin-hero">
        <div class="container">
            <span class="eyebrow">Admin</span>
            <h1>Account</h1>
            <p>Beheer het e-mailadres en wachtwoord van je adminaccount.</p>
        </div>
    </section>

    <section class="section section-white">
        <div class="container">

            @if (session('success') === 'account_email_updated')
                <div class="form-success">E-mailadres werd bijgewerkt. Je blijft ingelogd met het nieuwe adres.</div>
            @endif

            @if (session('success') === 'account_password_updated')
                <div class="form-success">Wachtwoord werd bijgewerkt.</div>
            @endif

            @if ($errors->any())
                <div class="form-error-list">
                    <ul>
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="admin-detail-layout">
                <div class="admin-detail-card">
                    <h2>E-mailadres wijzigen</h2>

                    <p>Huidig e-mailadres: <strong>{{ session('admin_user_email') }}</strong></p>

                    <form method="POST" action="{{ route('admin.account.email.update') }}">
                        @csrf
                        @method('PATCH')

                        <label>
                            <span>Nieuw e-mailadres</span>
                            <input type="email" name="email" value="{{ old('email') }}" required autocomplete="email">
                        </label>

                        <label>
                            <span>Huidig wachtwoord</span>
                            <input type="password" name="current_password" required autocomplete="current-password">
                        </label>

                        <button type="submit" class="button button-primary">Opslaan</button>
                    </form>

                    <p style="margin-top: 12px; font-size: 13px; color: #6b7c8f;">
                        Historische vermeldingen (zoals wie een standaardantwoord verstuurde of een notitie schreef) behouden bewust het oude e-mailadres.
                    </p>
                </div>

                <div class="admin-detail-card">
                    <h2>Wachtwoord wijzigen</h2>

                    <form method="POST" action="{{ route('admin.account.password.update') }}">
                        @csrf
                        @method('PATCH')

                        <label>
                            <span>Huidig wachtwoord</span>
                            <input type="password" name="current_password" required autocomplete="current-password">
                        </label>

                        <label>
                            <span>Nieuw wachtwoord (minstens 12 tekens)</span>
                            <input type="password" name="password" required minlength="12" autocomplete="new-password">
                        </label>

                        <label>
                            <span>Bevestig nieuw wachtwoord</span>
                            <input type="password" name="password_confirmation" required minlength="12" autocomplete="new-password">
                        </label>

                        <button type="submit" class="button button-primary">Opslaan</button>
                    </form>
                </div>
            </div>
        </div>
    </section>
@endsection
```

- [ ] **Step 6: Add the footer nav link**

In `resources/views/layouts/app.blade.php`, inside `<div class="footer-admin-actions">` (line 411), after the "Admin panel" link:

```blade
                        <a class="footer-admin-link" href="{{ route('admin.account.edit') }}">
                            Account
                        </a>
```

- [ ] **Step 7: Run tests to verify they pass**

Run: `php artisan test --filter=AccountSettingsTest`
Expected: PASS (9 tests).

- [ ] **Step 8: Run full suite, then commit**

```powershell
php artisan test; if ($?) { git add app/Http/Controllers/Admin/AccountController.php routes/web.php resources/views/admin/account/edit.blade.php resources/views/layouts/app.blade.php tests/Feature/Admin/AccountSettingsTest.php; git commit -m "feat(admin): add account settings for email and password change" }
```

---

## Final verification

- [ ] Run the complete suite: `php artisan test` — everything green.
- [ ] `git log --oneline -8` shows the six feature commits on `main`.
- [ ] Do **not** push or deploy.
