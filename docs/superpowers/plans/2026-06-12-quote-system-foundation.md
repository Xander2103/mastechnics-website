# Quote System Foundation — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use `superpowers:subagent-driven-development` (recommended) or `superpowers:executing-plans` to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a `quotes` table, `Quote` model, `QuoteController`, quote card on the request detail page, and a separate quote edit form so Martin can create, manage, and track the status of a quote linked to a customer request.

**Architecture:** A new `quotes` table (1:1 with `customer_requests` via a unique FK) holds all quote data. A dedicated `QuoteController` handles create/update (with server-side VAT calculation and quote number generation) and status actions that sync back to the parent request's status. The request detail view gains a read-only quote card in the main column; editing happens on a separate page. All logic stays in `QuoteController` — `RequestController` is not touched except to eager-load the quote relation.

**Tech Stack:** Laravel 12, Blade, PHP 8.x, MySQL, PHPUnit, Vite/npm

**Spec:** `docs/superpowers/specs/2026-06-12-quote-system-foundation-design.md`

---

## File Map

| File | Action | Responsibility |
|---|---|---|
| `database/migrations/[ts]_create_quotes_table.php` | **Create** | quotes schema with unique FK, decimal columns, timestamps |
| `app/Models/Quote.php` | **Create** | fillable, casts, belongsTo CustomerRequest |
| `app/Models/CustomerRequest.php` | **Modify** | add `hasOne(Quote::class)` |
| `app/Http/Controllers/Admin/QuoteController.php` | **Create** | `edit`, `store`, `performAction`, private helpers |
| `tests/Feature/Admin/QuoteTest.php` | **Create** | 16 PHPUnit feature tests (TDD) |
| `routes/web.php` | **Modify** | add 3 quote routes + QuoteController import |
| `resources/css/pages/admin.css` | **Modify** | append quote card, badges, form, mobile CSS |
| `resources/views/admin/requests/show.blade.php` | **Modify** | quote card + flash messages; eager-load quote in RequestController@show |
| `app/Http/Controllers/Admin/RequestController.php` | **Modify** | add `'quote'` to `load()` in `show()` |
| `resources/views/admin/quotes/edit.blade.php` | **Create** | standalone quote edit form with live JS VAT preview |

---

## Task 1: Migration — Create quotes table

**Files:**
- Create: `database/migrations/[generated-timestamp]_create_quotes_table.php`

- [ ] **Step 1: Generate migration**

```powershell
php artisan make:migration create_quotes_table
```

Expected output: `Created Migration: database/migrations/[timestamp]_create_quotes_table.php`

- [ ] **Step 2: Replace migration body**

Open the generated file. Replace the entire `up()` and `down()` with:

```php
public function up(): void
{
    Schema::create('quotes', function (Blueprint $table) {
        $table->id();
        $table->foreignId('customer_request_id')
              ->unique()
              ->constrained('customer_requests')
              ->cascadeOnDelete();
        $table->string('quote_number', 20)->unique()->nullable();
        $table->string('quote_status', 20)->default('draft');
        $table->string('title', 200)->nullable();
        $table->text('description')->nullable();
        $table->decimal('amount_excl_vat', 10, 2)->nullable();
        $table->decimal('vat_rate', 5, 2)->default(21.00);
        $table->decimal('amount_vat', 10, 2)->nullable();
        $table->decimal('amount_incl_vat', 10, 2)->nullable();
        $table->date('valid_until')->nullable();
        $table->timestamp('sent_at')->nullable();
        $table->timestamp('accepted_at')->nullable();
        $table->timestamp('rejected_at')->nullable();
        $table->timestamps();
    });
}

public function down(): void
{
    Schema::dropIfExists('quotes');
}
```

- [ ] **Step 3: Run migration**

```powershell
php artisan migrate
```

Expected: `Running migrations ... [timestamp]_create_quotes_table ...DONE`

- [ ] **Step 4: Verify table and unique constraint**

```powershell
php artisan tinker --execute="print_r(Schema::getColumnListing('quotes'));"
```

Expected output includes: `customer_request_id`, `quote_number`, `quote_status`, `amount_excl_vat`, `vat_rate`, `amount_vat`, `amount_incl_vat`, `valid_until`, `sent_at`, `accepted_at`, `rejected_at`

- [ ] **Step 5: Commit**

```powershell
git add database/migrations/
git commit -m "feat(quotes): create quotes table with unique FK and decimal columns"
```

---

## Task 2: Quote Model + CustomerRequest hasOne

**Files:**
- Create: `app/Models/Quote.php`
- Modify: `app/Models/CustomerRequest.php`

- [ ] **Step 1: Create `app/Models/Quote.php`**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Quote extends Model
{
    protected $fillable = [
        'customer_request_id',
        'quote_number',
        'quote_status',
        'title',
        'description',
        'amount_excl_vat',
        'vat_rate',
        'amount_vat',
        'amount_incl_vat',
        'valid_until',
        'sent_at',
        'accepted_at',
        'rejected_at',
    ];

    protected $casts = [
        'amount_excl_vat'  => 'decimal:2',
        'vat_rate'         => 'decimal:2',
        'amount_vat'       => 'decimal:2',
        'amount_incl_vat'  => 'decimal:2',
        'valid_until'      => 'date',
        'sent_at'          => 'datetime',
        'accepted_at'      => 'datetime',
        'rejected_at'      => 'datetime',
    ];

    public function customerRequest(): BelongsTo
    {
        return $this->belongsTo(CustomerRequest::class);
    }
}
```

- [ ] **Step 2: Add `hasOne` to `CustomerRequest`**

Open `app/Models/CustomerRequest.php`. Add `HasOne` to the `use` imports at the top:

```php
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
```

Then add this method after the `notes()` method:

```php
public function quote(): HasOne
{
    return $this->hasOne(Quote::class);
}
```

- [ ] **Step 3: Syntax check**

```powershell
php -l app/Models/Quote.php
php -l app/Models/CustomerRequest.php
```

Expected: `No syntax errors detected` for both.

- [ ] **Step 4: Commit**

```powershell
git add app/Models/Quote.php app/Models/CustomerRequest.php
git commit -m "feat(quotes): add Quote model and CustomerRequest hasOne relationship"
```

---

## Task 3: Routes — Add 3 new admin quote routes

**Files:**
- Modify: `routes/web.php`

- [ ] **Step 1: Add QuoteController import**

Open `routes/web.php`. After the existing import lines at the top, add:

```php
use App\Http\Controllers\Admin\QuoteController as AdminQuoteController;
```

The top of the file should now include:

```php
use App\Http\Controllers\Admin\AuthController as AdminAuthController;
use App\Http\Controllers\Admin\QuoteController as AdminQuoteController;
use App\Http\Controllers\Admin\RequestController as AdminRequestController;
use App\Http\Controllers\CustomerRequestController;
use App\Http\Controllers\PageController;
use Illuminate\Support\Facades\Route;
```

- [ ] **Step 2: Add 3 quote routes inside the admin middleware group**

Inside the `Route::middleware('admin')->prefix('admin')->name('admin.')->group(...)` block, add these 3 routes **after** the existing `requests.internal-notes.update` route (currently the last route in the group):

```php
Route::get('/requests/{customerRequest}/quote/edit', [AdminQuoteController::class, 'edit'])
    ->name('requests.quote.edit');

Route::post('/requests/{customerRequest}/quote', [AdminQuoteController::class, 'store'])
    ->name('requests.quote.store');

Route::post('/requests/{customerRequest}/quote/action', [AdminQuoteController::class, 'performAction'])
    ->name('requests.quote.action');
```

- [ ] **Step 3: Verify routes registered**

```powershell
php artisan route:list --name=admin.requests.quote
```

Expected: 3 routes — `admin.requests.quote.edit` (GET), `admin.requests.quote.store` (POST), `admin.requests.quote.action` (POST).

- [ ] **Step 4: Commit**

```powershell
git add routes/web.php
git commit -m "feat(quotes): add quote edit, store, and action routes"
```

---

## Task 4: TDD — QuoteController `store` (create/update/VAT/quote number)

**Files:**
- Create: `tests/Feature/Admin/QuoteTest.php`
- Create: `app/Http/Controllers/Admin/QuoteController.php`

### Step 1: Write the failing tests

- [ ] **Step 1a: Create `tests/Feature/Admin/QuoteTest.php`**

```php
<?php

namespace Tests\Feature\Admin;

use App\Models\CustomerRequest;
use App\Models\Quote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuoteTest extends TestCase
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
            'service_slug'   => 'airco',
            'request_type'   => 'install',
            'customer_name'  => 'Test Klant',
            'customer_email' => 'test@example.com',
            'description'    => 'Test aanvraag',
            'status'         => 'new',
        ], $attrs));
    }

    private function makeQuote(CustomerRequest $req, array $attrs = []): Quote
    {
        return Quote::create(array_merge([
            'customer_request_id' => $req->id,
            'quote_status'        => 'draft',
        ], $attrs));
    }

    // --- store: VAT calculation ---

    public function test_store_creates_quote_with_vat_calculation(): void
    {
        $req = $this->makeRequest();

        $this->withSession($this->adminSession())
            ->post(route('admin.requests.quote.store', $req), [
                'amount_excl_vat' => '1000.00',
                'vat_rate'        => '21',
            ])
            ->assertRedirect(route('admin.requests.show', $req));

        $quote = $req->fresh()->quote;
        $this->assertNotNull($quote);
        $this->assertSame('210.00', $quote->amount_vat);
        $this->assertSame('1210.00', $quote->amount_incl_vat);
    }

    public function test_store_updates_existing_quote_without_duplicate(): void
    {
        $req = $this->makeRequest();
        $this->makeQuote($req, ['title' => 'Oud']);

        $this->withSession($this->adminSession())
            ->post(route('admin.requests.quote.store', $req), [
                'title' => 'Nieuw',
            ]);

        $this->assertSame(1, Quote::count());
        $this->assertSame('Nieuw', $req->fresh()->quote->title);
    }

    public function test_store_generates_quote_number_on_first_save(): void
    {
        $req = $this->makeRequest();

        $this->withSession($this->adminSession())
            ->post(route('admin.requests.quote.store', $req), []);

        $quote = $req->fresh()->quote;
        $this->assertNotNull($quote->quote_number);
        $this->assertMatchesRegularExpression('/^OFF-\d{4}-\d{4}$/', $quote->quote_number);
    }

    public function test_store_does_not_regenerate_quote_number_on_update(): void
    {
        $req = $this->makeRequest();
        $this->makeQuote($req, ['quote_number' => 'OFF-2026-0001']);

        $this->withSession($this->adminSession())
            ->post(route('admin.requests.quote.store', $req), ['title' => 'Update']);

        $this->assertSame('OFF-2026-0001', $req->fresh()->quote->quote_number);
    }

    public function test_quote_number_increments_for_each_new_quote(): void
    {
        $req1 = $this->makeRequest();
        $req2 = $this->makeRequest();

        $this->withSession($this->adminSession())
            ->post(route('admin.requests.quote.store', $req1), []);

        $this->withSession($this->adminSession())
            ->post(route('admin.requests.quote.store', $req2), []);

        $num1 = $req1->fresh()->quote->quote_number;
        $num2 = $req2->fresh()->quote->quote_number;

        $this->assertNotSame($num1, $num2);
        $suffix1 = (int) substr($num1, -4);
        $suffix2 = (int) substr($num2, -4);
        $this->assertSame(1, $suffix2 - $suffix1);
    }

    // --- store: validation ---

    public function test_store_validates_amount_must_be_numeric(): void
    {
        $req = $this->makeRequest();

        $this->withSession($this->adminSession())
            ->post(route('admin.requests.quote.store', $req), [
                'amount_excl_vat' => 'niet-numeriek',
            ])
            ->assertSessionHasErrors('amount_excl_vat');
    }

    public function test_store_validates_amount_must_be_non_negative(): void
    {
        $req = $this->makeRequest();

        $this->withSession($this->adminSession())
            ->post(route('admin.requests.quote.store', $req), [
                'amount_excl_vat' => '-10',
            ])
            ->assertSessionHasErrors('amount_excl_vat');
    }

    public function test_store_validates_vat_rate_must_be_non_negative(): void
    {
        $req = $this->makeRequest();

        $this->withSession($this->adminSession())
            ->post(route('admin.requests.quote.store', $req), [
                'vat_rate' => '-5',
            ])
            ->assertSessionHasErrors('vat_rate');
    }

    // --- performAction: mark_sent ---

    public function test_mark_sent_sets_quote_status_and_request_status(): void
    {
        $req   = $this->makeRequest(['status' => 'new']);
        $quote = $this->makeQuote($req);

        $this->withSession($this->adminSession())
            ->post(route('admin.requests.quote.action', $req), ['action' => 'mark_sent']);

        $this->assertSame('sent', $quote->fresh()->quote_status);
        $this->assertSame('quote_sent', $req->fresh()->status);
        $this->assertNotNull($quote->fresh()->sent_at);
    }

    public function test_mark_sent_does_not_overwrite_existing_sent_at(): void
    {
        $original = now()->subDay()->startOfMinute();
        $req      = $this->makeRequest();
        $quote    = $this->makeQuote($req, ['sent_at' => $original]);

        $this->withSession($this->adminSession())
            ->post(route('admin.requests.quote.action', $req), ['action' => 'mark_sent']);

        $this->assertSame($original->timestamp, $quote->fresh()->sent_at->timestamp);
    }

    // --- performAction: mark_accepted ---

    public function test_mark_accepted_sets_quote_status_and_request_status(): void
    {
        $req   = $this->makeRequest(['status' => 'quote_sent']);
        $quote = $this->makeQuote($req, ['quote_status' => 'sent']);

        $this->withSession($this->adminSession())
            ->post(route('admin.requests.quote.action', $req), ['action' => 'mark_accepted']);

        $this->assertSame('accepted', $quote->fresh()->quote_status);
        $this->assertSame('won', $req->fresh()->status);
        $this->assertNotNull($quote->fresh()->accepted_at);
    }

    public function test_mark_accepted_does_not_overwrite_existing_accepted_at(): void
    {
        $original = now()->subHour()->startOfMinute();
        $req      = $this->makeRequest();
        $quote    = $this->makeQuote($req, ['accepted_at' => $original]);

        $this->withSession($this->adminSession())
            ->post(route('admin.requests.quote.action', $req), ['action' => 'mark_accepted']);

        $this->assertSame($original->timestamp, $quote->fresh()->accepted_at->timestamp);
    }

    // --- performAction: mark_rejected ---

    public function test_mark_rejected_sets_quote_status_and_request_status(): void
    {
        $req   = $this->makeRequest(['status' => 'quote_sent']);
        $quote = $this->makeQuote($req, ['quote_status' => 'sent']);

        $this->withSession($this->adminSession())
            ->post(route('admin.requests.quote.action', $req), ['action' => 'mark_rejected']);

        $this->assertSame('rejected', $quote->fresh()->quote_status);
        $this->assertSame('lost', $req->fresh()->status);
        $this->assertNotNull($quote->fresh()->rejected_at);
    }

    public function test_mark_rejected_does_not_overwrite_existing_rejected_at(): void
    {
        $original = now()->subHour()->startOfMinute();
        $req      = $this->makeRequest();
        $quote    = $this->makeQuote($req, ['rejected_at' => $original]);

        $this->withSession($this->adminSession())
            ->post(route('admin.requests.quote.action', $req), ['action' => 'mark_rejected']);

        $this->assertSame($original->timestamp, $quote->fresh()->rejected_at->timestamp);
    }

    // --- validation / auth ---

    public function test_invalid_action_returns_validation_error(): void
    {
        $req   = $this->makeRequest();
        $quote = $this->makeQuote($req);

        $this->withSession($this->adminSession())
            ->post(route('admin.requests.quote.action', $req), ['action' => 'invalid_action'])
            ->assertSessionHasErrors('action');
    }

    public function test_unauthenticated_store_redirects_to_login(): void
    {
        $req = $this->makeRequest();

        $this->post(route('admin.requests.quote.store', $req), [])
            ->assertRedirect(route('admin.login'));
    }
}
```

- [ ] **Step 1b: Run tests — expect failures (controller not yet created)**

```powershell
php artisan test tests/Feature/Admin/QuoteTest.php 2>&1
```

Expected: multiple failures with 404, method not found, or class not found.

### Step 2: Implement QuoteController

- [ ] **Step 2a: Create `app/Http/Controllers/Admin/QuoteController.php`**

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CustomerRequest;
use App\Models\Quote;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class QuoteController extends Controller
{
    public function edit(CustomerRequest $customerRequest): View
    {
        return view('admin.quotes.edit', [
            'customerRequest' => $customerRequest,
            'quote'           => $customerRequest->quote,
        ]);
    }

    public function store(Request $request, CustomerRequest $customerRequest): RedirectResponse
    {
        $validated = $request->validate([
            'title'           => ['nullable', 'string', 'max:200'],
            'description'     => ['nullable', 'string'],
            'amount_excl_vat' => ['nullable', 'numeric', 'min:0'],
            'vat_rate'        => ['nullable', 'numeric', 'min:0'],
            'valid_until'     => ['nullable', 'date'],
        ]);

        $amountExclVat = isset($validated['amount_excl_vat']) ? (float) $validated['amount_excl_vat'] : null;
        $vatRate       = isset($validated['vat_rate']) ? (float) $validated['vat_rate'] : 21.0;
        $amountVat     = null;
        $amountInclVat = null;

        if ($amountExclVat !== null) {
            $amountVat     = round($amountExclVat * ($vatRate / 100), 2);
            $amountInclVat = round($amountExclVat + $amountVat, 2);
        }

        $existingQuote = $customerRequest->quote;
        $quoteNumber   = $existingQuote?->quote_number ?? $this->generateQuoteNumber();

        Quote::updateOrCreate(
            ['customer_request_id' => $customerRequest->id],
            [
                'quote_number'    => $quoteNumber,
                'title'           => $validated['title'] ?? null,
                'description'     => $validated['description'] ?? null,
                'amount_excl_vat' => $amountExclVat,
                'vat_rate'        => $vatRate,
                'amount_vat'      => $amountVat,
                'amount_incl_vat' => $amountInclVat,
                'valid_until'     => $validated['valid_until'] ?? null,
            ]
        );

        return redirect()->route('admin.requests.show', $customerRequest)
            ->with('success', 'quote_saved');
    }

    public function performAction(Request $request, CustomerRequest $customerRequest): RedirectResponse
    {
        $validated = $request->validate([
            'action' => ['required', 'string', 'in:mark_sent,mark_accepted,mark_rejected'],
        ]);

        $quote = $customerRequest->quote;

        if (! $quote) {
            return back()->withErrors(['action' => 'Geen offerte gevonden voor deze aanvraag.']);
        }

        match ($validated['action']) {
            'mark_sent'     => $this->applyMarkSent($quote, $customerRequest),
            'mark_accepted' => $this->applyMarkAccepted($quote, $customerRequest),
            'mark_rejected' => $this->applyMarkRejected($quote, $customerRequest),
        };

        return back()->with('success', 'quote_action_applied');
    }

    private function applyMarkSent(Quote $quote, CustomerRequest $customerRequest): void
    {
        $quote->update([
            'quote_status' => 'sent',
            'sent_at'      => $quote->sent_at ?? now(),
        ]);

        $customerRequest->update([
            'status'        => 'quote_sent',
            'quote_sent_at' => $customerRequest->quote_sent_at ?? now(),
        ]);
    }

    private function applyMarkAccepted(Quote $quote, CustomerRequest $customerRequest): void
    {
        $quote->update([
            'quote_status' => 'accepted',
            'accepted_at'  => $quote->accepted_at ?? now(),
        ]);

        $customerRequest->update([
            'status' => 'won',
            'won_at' => $customerRequest->won_at ?? now(),
        ]);
    }

    private function applyMarkRejected(Quote $quote, CustomerRequest $customerRequest): void
    {
        $quote->update([
            'quote_status' => 'rejected',
            'rejected_at'  => $quote->rejected_at ?? now(),
        ]);

        $customerRequest->update([
            'status'  => 'lost',
            'lost_at' => $customerRequest->lost_at ?? now(),
        ]);
    }

    private function generateQuoteNumber(): string
    {
        $year = now()->year;
        $max  = Quote::where('quote_number', 'LIKE', "OFF-{$year}-%")->max('quote_number');
        $next = $max ? ((int) substr($max, -4)) + 1 : 1;

        return 'OFF-' . $year . '-' . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }
}
```

- [ ] **Step 2b: Run all quote tests — all must pass**

```powershell
php artisan test tests/Feature/Admin/QuoteTest.php 2>&1
```

Expected: **16 tests pass**. If any fail, debug and fix before continuing.

- [ ] **Step 2c: Run full suite — no regressions**

```powershell
php artisan test 2>&1
```

Expected: all 19 existing + 16 new = **35 tests pass**, 0 failures.

- [ ] **Step 2d: Syntax check**

```powershell
php -l app/Http/Controllers/Admin/QuoteController.php
```

Expected: `No syntax errors detected`

- [ ] **Step 2e: Commit**

```powershell
git add tests/Feature/Admin/QuoteTest.php app/Http/Controllers/Admin/QuoteController.php
git commit -m "feat(quotes): add QuoteController with store/performAction (TDD, 16 tests)"
```

---

## Task 5: Eager-load quote in RequestController@show

**Files:**
- Modify: `app/Http/Controllers/Admin/RequestController.php`

The `show()` method currently calls:
```php
$customerRequest->load(['attachments', 'notes']);
```

- [ ] **Step 1: Add `'quote'` to the load call**

In `app/Http/Controllers/Admin/RequestController.php`, find the `show()` method and change:

```php
$customerRequest->load(['attachments', 'notes']);
```

to:

```php
$customerRequest->load(['attachments', 'notes', 'quote']);
```

- [ ] **Step 2: Syntax check**

```powershell
php -l app/Http/Controllers/Admin/RequestController.php
```

Expected: `No syntax errors detected`

- [ ] **Step 3: Commit**

```powershell
git add app/Http/Controllers/Admin/RequestController.php
git commit -m "feat(quotes): eager-load quote relation in RequestController@show"
```

---

## Task 6: CSS — Quote card, badges, edit form

**Files:**
- Modify: `resources/css/pages/admin.css`

The file currently ends with `}` closing the `@media (max-width: 680px)` block. Append-only changes below.

- [ ] **Step 1: Append quote card CSS**

Open `resources/css/pages/admin.css`. After the **last line** of the file (the closing `}` of the media query), append:

```css

/* ================================
   Quote card
================================ */

.admin-quote-card-header {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 16px;
}

.admin-quote-card-header h2 {
    margin-bottom: 0;
}

.admin-quote-meta-row {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 12px;
    margin-bottom: 10px;
    font-size: 0.88rem;
}

.admin-quote-number {
    font-weight: 900;
    color: var(--color-primary-dark);
}

.admin-quote-valid {
    color: var(--color-muted);
    font-size: 0.82rem;
}

.admin-quote-title {
    font-weight: 800;
    color: var(--color-primary-dark);
    margin-bottom: 4px;
}

.admin-quote-description {
    color: var(--color-muted);
    font-size: 0.92rem;
    margin-bottom: 14px;
    white-space: pre-wrap;
}

.admin-quote-amounts {
    display: grid;
    gap: 6px;
    padding: 14px 0;
    border-top: 1px solid var(--color-border);
    border-bottom: 1px solid var(--color-border);
    margin: 14px 0;
}

.admin-quote-amount-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 0.92rem;
    color: var(--color-muted);
}

.admin-quote-amount-total {
    font-weight: 900;
    font-size: 1rem;
    color: var(--color-primary-dark);
    padding-top: 8px;
    border-top: 1px solid var(--color-border);
}

.admin-quote-timestamps {
    display: grid;
    gap: 4px;
    margin-bottom: 16px;
    font-size: 0.82rem;
    color: var(--color-muted);
}

.admin-quote-timestamps span {
    font-weight: 700;
    color: var(--color-primary-dark);
}

.admin-quote-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    padding-top: 16px;
    border-top: 1px solid var(--color-border);
    margin-top: 4px;
}

/* ================================
   Quote status badges
================================ */

.admin-quote-status {
    display: inline-flex;
    align-items: center;
    padding: 3px 10px;
    border-radius: 999px;
    font-size: 0.74rem;
    font-weight: 900;
    text-transform: uppercase;
    white-space: nowrap;
}

.admin-quote-status-draft {
    background: #f1f5f9;
    color: #475569;
}

.admin-quote-status-sent {
    background: #fef3c7;
    color: #92400e;
}

.admin-quote-status-accepted {
    background: #dcfce7;
    color: #166534;
}

.admin-quote-status-rejected {
    background: rgba(229, 71, 63, 0.10);
    color: #b52a24;
}

/* ================================
   Quote edit form
================================ */

.admin-quote-form-card h2 {
    margin-bottom: 20px;
}

.admin-quote-form {
    display: grid;
    gap: 18px;
}

.admin-quote-form label {
    display: grid;
    gap: 7px;
    color: var(--color-primary-dark);
    font-size: 0.88rem;
    font-weight: 900;
}

.admin-quote-form input[type="text"],
.admin-quote-form input[type="number"],
.admin-quote-form input[type="date"],
.admin-quote-form textarea {
    width: 100%;
    border: 1px solid var(--color-border);
    border-radius: 14px;
    padding: 12px 14px;
    background: var(--color-white);
    color: var(--color-text);
    font: inherit;
}

.admin-quote-form input:focus,
.admin-quote-form textarea:focus {
    outline: none;
    border-color: rgba(15, 102, 194, 0.55);
    box-shadow: 0 0 0 4px rgba(15, 102, 194, 0.1);
}

.admin-quote-amounts-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 14px;
}

.admin-quote-preview {
    background: #f8fbff;
    border: 1px solid #dbeafe;
    border-radius: 14px;
    padding: 14px 16px;
    display: grid;
    gap: 6px;
}

@media (max-width: 680px) {
    .admin-quote-amounts-row {
        grid-template-columns: 1fr;
    }

    .admin-quote-actions {
        flex-direction: column;
    }
}
```

- [ ] **Step 2: Verify CSS file has no syntax issues by running build**

```powershell
npm run build 2>&1
```

Expected: build completes without errors.

- [ ] **Step 3: Commit**

```powershell
git add resources/css/pages/admin.css
git commit -m "style(quotes): add quote card, status badges, and edit form CSS"
```

---

## Task 7: show.blade.php — Quote card + flash messages

**Files:**
- Modify: `resources/views/admin/requests/show.blade.php`

- [ ] **Step 1: Add flash messages for quote actions**

Open `resources/views/admin/requests/show.blade.php`. Find the existing flash message block (the `@if (session('success') === 'internal_notes_updated')` block is currently the last one). Add these two messages **after** it and **before** `<div class="admin-detail-layout">`:

```blade
@if (session('success') === 'quote_saved')
    <div class="form-success">
        Offerte werd opgeslagen.
    </div>
@endif

@if (session('success') === 'quote_action_applied')
    <div class="form-success">
        Offerte-status werd bijgewerkt.
    </div>
@endif
```

- [ ] **Step 2: Add quote card in the main column after summary block**

Find the comment `{{-- Ontbrekende informatie — only render if there are missing items --}}` (it comes directly after the summary block). Insert the quote card block **before** that comment:

```blade
{{-- Quote card --}}
@php $quote = $customerRequest->quote; @endphp
<div class="admin-detail-card admin-quote-card">
    <div class="admin-quote-card-header">
        <h2>Offerte</h2>
        @if ($quote)
            @php
                $quoteStatusLabels = [
                    'draft'    => 'Concept',
                    'sent'     => 'Verstuurd',
                    'accepted' => 'Aanvaard',
                    'rejected' => 'Afgewezen',
                ];
            @endphp
            <span class="admin-quote-status admin-quote-status-{{ $quote->quote_status }}">
                {{ $quoteStatusLabels[$quote->quote_status] ?? $quote->quote_status }}
            </span>
        @endif
    </div>

    @if (! $quote)
        <p class="admin-muted-text">Nog geen offerte aangemaakt voor deze aanvraag.</p>
        <div style="margin-top: 14px;">
            <a class="button button-primary"
               href="{{ route('admin.requests.quote.edit', $customerRequest) }}">
                + Offerte aanmaken
            </a>
        </div>
    @else
        {{-- Meta: number + valid until --}}
        <div class="admin-quote-meta-row">
            @if ($quote->quote_number)
                <span class="admin-quote-number">{{ $quote->quote_number }}</span>
            @endif
            @if ($quote->valid_until)
                <span class="admin-quote-valid">Geldig t/m {{ $quote->valid_until->format('d/m/Y') }}</span>
            @endif
        </div>

        @if ($quote->title)
            <p class="admin-quote-title">{{ $quote->title }}</p>
        @endif

        @if ($quote->description)
            <p class="admin-quote-description">{{ $quote->description }}</p>
        @endif

        {{-- Amounts --}}
        @if ($quote->amount_excl_vat !== null)
            <div class="admin-quote-amounts">
                <div class="admin-quote-amount-row">
                    <span>Excl. BTW</span>
                    <span>€&nbsp;{{ number_format((float) $quote->amount_excl_vat, 2, ',', '.') }}</span>
                </div>
                <div class="admin-quote-amount-row">
                    <span>BTW ({{ $quote->vat_rate }}%)</span>
                    <span>€&nbsp;{{ number_format((float) $quote->amount_vat, 2, ',', '.') }}</span>
                </div>
                <div class="admin-quote-amount-row admin-quote-amount-total">
                    <span>Incl. BTW</span>
                    <span>€&nbsp;{{ number_format((float) $quote->amount_incl_vat, 2, ',', '.') }}</span>
                </div>
            </div>
        @endif

        {{-- Timestamps --}}
        @if ($quote->sent_at || $quote->accepted_at || $quote->rejected_at)
            <div class="admin-quote-timestamps">
                @if ($quote->sent_at)
                    <div><span>Verstuurd:</span> {{ $quote->sent_at->format('d/m/Y H:i') }}</div>
                @endif
                @if ($quote->accepted_at)
                    <div><span>Aanvaard:</span> {{ $quote->accepted_at->format('d/m/Y H:i') }}</div>
                @endif
                @if ($quote->rejected_at)
                    <div><span>Afgewezen:</span> {{ $quote->rejected_at->format('d/m/Y H:i') }}</div>
                @endif
            </div>
        @endif

        {{-- Actions --}}
        <div class="admin-quote-actions">
            <a class="button button-secondary"
               href="{{ route('admin.requests.quote.edit', $customerRequest) }}">
                ✏ Bewerken
            </a>

            @if ($quote->quote_status === 'draft')
                <form method="POST" action="{{ route('admin.requests.quote.action', $customerRequest) }}">
                    @csrf
                    <input type="hidden" name="action" value="mark_sent">
                    <button type="submit" class="admin-quick-action-btn">
                        Verstuurd ▸
                    </button>
                </form>
            @endif

            @if ($quote->quote_status === 'sent')
                <form method="POST" action="{{ route('admin.requests.quote.action', $customerRequest) }}">
                    @csrf
                    <input type="hidden" name="action" value="mark_accepted">
                    <button type="submit" class="admin-quick-action-btn admin-quick-action-won">
                        Gewonnen ▸
                    </button>
                </form>

                <form method="POST" action="{{ route('admin.requests.quote.action', $customerRequest) }}">
                    @csrf
                    <input type="hidden" name="action" value="mark_rejected">
                    <button type="submit" class="admin-quick-action-btn admin-quick-action-lost">
                        Verloren ▸
                    </button>
                </form>
            @endif
        </div>
    @endif
</div>
```

- [ ] **Step 3: Commit**

```powershell
git add resources/views/admin/requests/show.blade.php
git commit -m "feat(quotes): add quote card to request detail page"
```

---

## Task 8: Quote edit page (`admin/quotes/edit.blade.php`)

**Files:**
- Create: `resources/views/admin/quotes/edit.blade.php`

- [ ] **Step 1: Create the views directory**

```powershell
New-Item -ItemType Directory -Force "resources/views/admin/quotes"
```

- [ ] **Step 2: Create `resources/views/admin/quotes/edit.blade.php`**

```blade
@extends('layouts.app')

@section('title', 'Admin | Offerte bewerken')

@section('content')
    <section class="admin-hero">
        <div class="container">
            <span class="eyebrow">Admin</span>
            <h1>{{ $quote ? 'Offerte bewerken' : 'Offerte aanmaken' }}</h1>
            <p>
                {{ $quote
                    ? 'Offertegegevens voor ' . $customerRequest->customer_name . ' aanpassen.'
                    : 'Nieuwe offerte aanmaken voor ' . $customerRequest->customer_name . '.' }}
            </p>
        </div>
    </section>

    <section class="section section-white">
        <div class="container">

            <div class="admin-back-row">
                <a class="button button-secondary admin-back-button"
                   href="{{ route('admin.requests.show', $customerRequest) }}">
                    ← Terug naar aanvraag
                </a>
            </div>

            @if (session('success') === 'quote_saved')
                <div class="form-success">
                    Offerte werd opgeslagen.
                </div>
            @endif

            <div class="admin-detail-card admin-quote-form-card">
                <h2>Offertegegevens</h2>

                <form class="admin-quote-form" method="POST"
                    action="{{ route('admin.requests.quote.store', $customerRequest) }}">
                    @csrf

                    <label>
                        <span>Titel</span>
                        <input type="text" name="title" maxlength="200"
                            value="{{ old('title', $quote?->title) }}"
                            placeholder="Bijv. Airco-installatie 3 kamers">
                        @error('title')
                            <p class="field-error-text">{{ $message }}</p>
                        @enderror
                    </label>

                    <label>
                        <span>Omschrijving</span>
                        <textarea name="description" rows="4"
                            placeholder="Verdere beschrijving van de offerte...">{{ old('description', $quote?->description) }}</textarea>
                        @error('description')
                            <p class="field-error-text">{{ $message }}</p>
                        @enderror
                    </label>

                    <div class="admin-quote-amounts-row">
                        <label>
                            <span>Bedrag excl. BTW (€)</span>
                            <input type="number" name="amount_excl_vat" id="amount_excl_vat"
                                step="0.01" min="0"
                                value="{{ old('amount_excl_vat', $quote?->amount_excl_vat) }}"
                                placeholder="0.00">
                            @error('amount_excl_vat')
                                <p class="field-error-text">{{ $message }}</p>
                            @enderror
                        </label>

                        <label>
                            <span>BTW-tarief (%)</span>
                            <input type="number" name="vat_rate" id="vat_rate"
                                step="0.01" min="0"
                                value="{{ old('vat_rate', $quote?->vat_rate ?? '21') }}"
                                placeholder="21">
                            @error('vat_rate')
                                <p class="field-error-text">{{ $message }}</p>
                            @enderror
                        </label>
                    </div>

                    {{-- Live preview (client-side, read-only) --}}
                    <div class="admin-quote-preview" id="quote-preview"
                        style="{{ old('amount_excl_vat', $quote?->amount_excl_vat) !== null ? '' : 'display:none' }}">
                        <div class="admin-quote-amount-row">
                            <span>BTW</span>
                            <span id="preview-vat">
                                €&nbsp;{{ $quote?->amount_vat !== null ? number_format((float) $quote->amount_vat, 2, ',', '.') : '0,00' }}
                            </span>
                        </div>
                        <div class="admin-quote-amount-row admin-quote-amount-total">
                            <span>Incl. BTW</span>
                            <span id="preview-incl">
                                €&nbsp;{{ $quote?->amount_incl_vat !== null ? number_format((float) $quote->amount_incl_vat, 2, ',', '.') : '0,00' }}
                            </span>
                        </div>
                    </div>

                    <label>
                        <span>Geldig tot</span>
                        <input type="date" name="valid_until"
                            value="{{ old('valid_until', $quote?->valid_until?->format('Y-m-d')) }}">
                        @error('valid_until')
                            <p class="field-error-text">{{ $message }}</p>
                        @enderror
                    </label>

                    @if ($quote?->quote_number)
                        <p class="admin-muted-text" style="font-size: 0.82rem;">
                            Offertenummer: {{ $quote->quote_number }}
                        </p>
                    @endif

                    <div>
                        <button class="button button-primary" type="submit">
                            Offerte opslaan
                        </button>
                    </div>
                </form>
            </div>

        </div>
    </section>

    <script>
    (function () {
        var exclInput = document.getElementById('amount_excl_vat');
        var vatInput  = document.getElementById('vat_rate');
        var preview   = document.getElementById('quote-preview');
        var vatEl     = document.getElementById('preview-vat');
        var inclEl    = document.getElementById('preview-incl');

        if (! exclInput || ! vatInput) return;

        function fmt(n) {
            return '€ ' + n.toFixed(2).replace('.', ',');
        }

        function update() {
            var excl = parseFloat(exclInput.value);
            var rate = parseFloat(vatInput.value);
            if (isNaN(excl) || isNaN(rate) || excl < 0) {
                preview.style.display = 'none';
                return;
            }
            var vat  = Math.round(excl * (rate / 100) * 100) / 100;
            var incl = Math.round((excl + vat) * 100) / 100;
            vatEl.textContent  = fmt(vat);
            inclEl.textContent = fmt(incl);
            preview.style.display = '';
        }

        exclInput.addEventListener('input', update);
        vatInput.addEventListener('input', update);
    }());
    </script>
@endsection
```

- [ ] **Step 3: Commit**

```powershell
git add resources/views/admin/quotes/
git commit -m "feat(quotes): add standalone quote edit page with live VAT preview"
```

---

## Task 9: Final Verification

- [ ] **Step 1: Run full test suite**

```powershell
php artisan test 2>&1
```

Expected: **35 tests pass** (19 existing + 16 quote tests), 0 failures, 0 errors.

- [ ] **Step 2: Build frontend assets**

```powershell
npm run build 2>&1
```

Expected: build completes without errors. CSS output ~66–70 kB.

- [ ] **Step 3: Verify PHP syntax on all changed files**

```powershell
php -l app/Models/Quote.php; php -l app/Models/CustomerRequest.php; php -l app/Http/Controllers/Admin/QuoteController.php; php -l app/Http/Controllers/Admin/RequestController.php
```

Expected: `No syntax errors detected` for all four.

- [ ] **Step 4: Verify routes registered**

```powershell
php artisan route:list --name=admin.requests.quote 2>&1
```

Expected: 3 routes listed:
- `GET admin/requests/{customerRequest}/quote/edit` → `admin.requests.quote.edit`
- `POST admin/requests/{customerRequest}/quote` → `admin.requests.quote.store`
- `POST admin/requests/{customerRequest}/quote/action` → `admin.requests.quote.action`

- [ ] **Step 5: Manual smoke test checklist**

Open the admin at `/admin/requests` and verify:

1. **No quote yet:** Open any request detail. The quote card shows "Nog geen offerte aangemaakt" with a "+ Offerte aanmaken" button.
2. **Create quote:** Click "+ Offerte aanmaken" → edit page loads. Fill title, amount `€500`, VAT `21%`. The live preview shows BTW `€105` and incl. BTW `€605`. Save → redirected to detail page, green flash "Offerte werd opgeslagen.", quote card shows `Concept` badge with amounts.
3. **Quote number:** The quote card shows `OFF-2026-0001` (or current year + increment).
4. **Edit quote:** Click "✏ Bewerken" → edit page prefills with existing values. Change title, save → returns to detail, values updated. Quote number unchanged.
5. **Mark sent:** Quote card shows "Verstuurd ▸" button (status is `draft`). Click → status badge changes to `Verstuurd`, request status also becomes `quote_sent`. Timestamp "Verstuurd: DD/MM/YYYY HH:MM" appears.
6. **Mark accepted:** With `sent` quote, card shows "Gewonnen ▸" and "Verloren ▸". Click "Gewonnen ▸" → quote status `Aanvaard`, request status `won`.
7. **Mark rejected:** With a `sent` quote, click "Verloren ▸" → quote status `Afgewezen`, request status `lost`.
8. **Accepted/Rejected final state:** Only "✏ Bewerken" button shows — no status-transition buttons.
9. **Existing quick actions:** Back button, WhatsApp link, threaded notes, uploads, internal memo, CSV export — all unaffected.
10. **Mobile:** On narrow viewport, quote form amounts stack vertically. Quote card and buttons wrap correctly.

- [ ] **Step 6: Final commit (if any uncommitted changes remain)**

```powershell
git status
```

If all changes are already committed, no action needed. Otherwise:

```powershell
git add -A
git commit -m "feat(quotes): quote system foundation complete"
```

---

## Risk Register

| Risk | Mitigation |
|---|---|
| `customer_requests.quote_sent_at` set by both quick-action AND quote `mark_sent` | `?? now()` — only writes if currently null, safe to call twice |
| Quick-action "Gewonnen"/"Verloren" and quote `mark_accepted`/`mark_rejected` can both set request status | Last write wins; both are valid admin actions. Quote card reflects quote model, quick-action card reflects request status. |
| `$customerRequest->quote` is `null` in Blade before any quote exists | Blade uses `@if (! $quote)` guard; null-safe `?->` operator on all property accesses |
| `decimal:2` cast returns string `"1210.00"` — test comparisons must use string literals | All QuoteTest assertions use string literals e.g. `assertSame('210.00', $quote->amount_vat)` |
| Quote number `LIKE 'OFF-YYYY-%'` using `max()` string comparison | Works correctly for zero-padded 4-digit suffixes (string max = numeric max up to 9999). Not an issue for < 10000 quotes/year. |
| VAT calculation floating-point edge cases | `round(..., 2)` provides consistent 2-decimal precision for small business amounts |
