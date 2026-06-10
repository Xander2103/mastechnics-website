# Admin Request Follow-up Foundation — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use `superpowers:subagent-driven-development` (recommended) or `superpowers:executing-plans` to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add follow-up status workflow, quick-action buttons, internal memo, summary block, and service_category filter to the admin request dashboard without breaking any existing functionality.

**Architecture:** New migration adds 5 columns to `customer_requests`. Controller gains two new action methods (`performAction`, `updateInternalNotes`) alongside updates to `getStatuses`, `buildFilteredQuery`, and `index` stats. Views are augmented in-place — no layout changes, only content additions. CSS is append-only.

**Tech Stack:** Laravel 12, Blade, PHP 8.x, MySQL, Vite/npm (for CSS build)

**Spec:** `docs/superpowers/specs/2026-06-10-admin-followup-foundation-design.md`

---

## File Map

| File | Action | Responsibility |
|---|---|---|
| `database/migrations/[ts]_add_followup_fields_to_customer_requests_table.php` | **Create** | Add 5 nullable columns: `internal_notes`, `contacted_at`, `quote_sent_at`, `won_at`, `lost_at` |
| `app/Models/CustomerRequest.php` | **Modify** | Add new columns to `$fillable`, add datetime `$casts`, add `getSummaryLines()` method |
| `routes/web.php` | **Modify** | Add `admin.requests.action` and `admin.requests.internal-notes.update` routes |
| `app/Http/Controllers/Admin/RequestController.php` | **Modify** | Update `getStatuses()`, `buildFilteredQuery()`, `updateStatus()`, `index()` stats; add `performAction()`, `updateInternalNotes()`, private action helpers |
| `tests/Feature/Admin/RequestFollowupTest.php` | **Create** | Feature tests for `performAction()` and `updateInternalNotes()` |
| `resources/css/pages/admin.css` | **Modify** | Append new badge, quick-action, summary block, and internal notes card styles |
| `resources/views/admin/requests/index.blade.php` | **Modify** | Add `service_category` filter select; update stats 4th card from `planned` → `quote_sent` |
| `resources/views/admin/requests/show.blade.php` | **Modify** | Replace status dropdown with quick-action grid; add summary block; add internal notes form; add flash messages |

---

## Task 1: Migration — Add Follow-up Columns

**Files:**
- Create: `database/migrations/[generated-timestamp]_add_followup_fields_to_customer_requests_table.php`

- [ ] **Step 1: Generate the migration file**

```powershell
php artisan make:migration add_followup_fields_to_customer_requests_table
```

Expected output: `Created Migration: database/migrations/[timestamp]_add_followup_fields_to_customer_requests_table.php`

- [ ] **Step 2: Replace the migration body**

Open the generated file. Replace the entire `up()` and `down()` bodies with:

```php
public function up(): void
{
    Schema::table('customer_requests', function (Blueprint $table) {
        $table->text('internal_notes')->nullable()->after('ai_detected_missing_fields');
        $table->timestamp('contacted_at')->nullable()->after('internal_notes');
        $table->timestamp('quote_sent_at')->nullable()->after('contacted_at');
        $table->timestamp('won_at')->nullable()->after('quote_sent_at');
        $table->timestamp('lost_at')->nullable()->after('won_at');
    });
}

public function down(): void
{
    Schema::table('customer_requests', function (Blueprint $table) {
        $table->dropColumn(['internal_notes', 'contacted_at', 'quote_sent_at', 'won_at', 'lost_at']);
    });
}
```

- [ ] **Step 3: Run the migration**

```powershell
php artisan migrate
```

Expected: `Running migrations ... [timestamp]_add_followup_fields_to_customer_requests_table ...DONE`

- [ ] **Step 4: Verify columns exist**

```powershell
php artisan tinker --execute="Schema::getColumnListing('customer_requests')" 
```

Expected output includes: `internal_notes`, `contacted_at`, `quote_sent_at`, `won_at`, `lost_at`

- [ ] **Step 5: Commit**

```powershell
git add database/migrations/
git commit -m "feat(admin): add follow-up columns to customer_requests"
```

---

## Task 2: CustomerRequest Model — Fillable, Casts, getSummaryLines

**Files:**
- Modify: `app/Models/CustomerRequest.php`

- [ ] **Step 1: Add new columns to `$fillable`**

In `app/Models/CustomerRequest.php`, the current `$fillable` ends with `'ai_detected_missing_fields'`. Add the 5 new fields after it:

```php
protected $fillable = [
    'locale',
    'service_slug',
    'request_type',
    'customer_name',
    'customer_email',
    'customer_phone',
    'description',
    'brand',
    'device_model',
    'serial_number',
    'unknown_device_details',
    'metadata',
    'status',
    'source',
    'service_category',
    'urgency_level',
    'preferred_time',
    'customer_message',
    'ai_summary',
    'ai_detected_missing_fields',
    'internal_notes',
    'contacted_at',
    'quote_sent_at',
    'won_at',
    'lost_at',
];
```

- [ ] **Step 2: Add datetime casts**

Replace the existing `$casts` with:

```php
protected $casts = [
    'metadata'                   => 'array',
    'unknown_device_details'     => 'boolean',
    'ai_detected_missing_fields' => 'array',
    'contacted_at'               => 'datetime',
    'quote_sent_at'              => 'datetime',
    'won_at'                     => 'datetime',
    'lost_at'                    => 'datetime',
];
```

- [ ] **Step 3: Add `getSummaryLines()` method**

Add this method to the model class, after the `getMissingInfoChecklist()` method:

```php
public function getSummaryLines(): array
{
    $lines    = [];
    $metadata = $this->metadata ?? [];
    $answers  = $metadata['answers'] ?? [];

    if ($this->service_category) {
        $category = collect(config('request-flow.service_categories', []))
            ->firstWhere('value', $this->service_category);
        $label   = $category['labels']['nl'] ?? $this->service_category;
        $lines[] = "Aanvraag voor {$label}.";
    }

    $urgentLevels = ['water_leaking', 'small_leak', 'no_heating', 'no_hot_water', 'urgent'];
    if ($this->urgency_level && in_array($this->urgency_level, $urgentLevels, true)) {
        $lines[] = 'Klant geeft aan dat het dringend is.';
    } elseif ($this->urgency_level === 'within_days') {
        $lines[] = 'Klant wenst behandeling binnen enkele dagen.';
    }

    $attachmentCount = $this->relationLoaded('attachments')
        ? $this->attachments->count()
        : $this->attachments()->count();
    if ($attachmentCount > 0) {
        $word    = $attachmentCount === 1 ? 'is' : 'zijn';
        $lines[] = "Er {$word} {$attachmentCount} bijlage(n) toegevoegd.";
    }

    if (! empty($this->preferred_time)) {
        $lines[] = "Voorkeurmoment: {$this->preferred_time}.";
    }

    if ($this->service_category === 'airco_offerte'
        && ! empty($answers['rooms'])
        && is_array($answers['rooms'])
    ) {
        $n       = count($answers['rooms']);
        $lines[] = "{$n} kamer(s) opgegeven voor offerte.";
    }

    $brandModel = trim(($this->brand ?? '') . ' ' . ($this->device_model ?? ''));
    if ($brandModel !== '') {
        $lines[] = "Toestel: {$brandModel}.";
    }

    return $lines;
}
```

- [ ] **Step 4: Sanity check**

```powershell
php -l app/Models/CustomerRequest.php
```

Expected: `No syntax errors detected`

- [ ] **Step 5: Commit**

```powershell
git add app/Models/CustomerRequest.php
git commit -m "feat(admin): add follow-up fillable/casts and getSummaryLines to CustomerRequest"
```

---

## Task 3: Routes — Add Two New Admin Routes

**Files:**
- Modify: `routes/web.php`

- [ ] **Step 1: Add the two new routes inside the admin middleware group**

Open `routes/web.php`. Inside the `Route::middleware('admin')` group, add these two routes **after** the existing `requests.notes.store` route (currently the last route in the group):

```php
Route::post('/requests/{customerRequest}/action', [AdminRequestController::class, 'performAction'])
    ->name('requests.action');

Route::patch('/requests/{customerRequest}/internal-notes', [AdminRequestController::class, 'updateInternalNotes'])
    ->name('requests.internal-notes.update');
```

The admin group should now look like:

```php
Route::middleware('admin')
    ->prefix('admin')
    ->name('admin.')
    ->group(function (): void {
        Route::get('/requests/export', [AdminRequestController::class, 'exportCsv'])
            ->name('requests.export');

        Route::get('/requests', [AdminRequestController::class, 'index'])
            ->name('requests.index');

        Route::get('/requests/{customerRequest}', [AdminRequestController::class, 'show'])
            ->name('requests.show');

        Route::patch('/requests/{customerRequest}/status', [AdminRequestController::class, 'updateStatus'])
            ->name('requests.update-status');

        Route::post('/requests/{customerRequest}/notes', [AdminRequestController::class, 'storeNote'])
            ->name('requests.notes.store');

        Route::post('/requests/{customerRequest}/action', [AdminRequestController::class, 'performAction'])
            ->name('requests.action');

        Route::patch('/requests/{customerRequest}/internal-notes', [AdminRequestController::class, 'updateInternalNotes'])
            ->name('requests.internal-notes.update');
    });
```

- [ ] **Step 2: Verify routes are registered**

```powershell
php artisan route:list --name=admin.requests
```

Expected: both `admin.requests.action` and `admin.requests.internal-notes.update` appear in the list.

- [ ] **Step 3: Commit**

```powershell
git add routes/web.php
git commit -m "feat(admin): add action and internal-notes routes"
```

---

## Task 4: Controller — Core Updates (getStatuses, buildFilteredQuery, updateStatus, index stats)

**Files:**
- Modify: `app/Http/Controllers/Admin/RequestController.php`

- [ ] **Step 1: Update `getStatuses()`**

Replace the existing private `getStatuses()` method with:

```php
private function getStatuses(): array
{
    return [
        'new'        => 'Nieuw',
        'viewed'     => 'Bekeken',
        'contacted'  => 'Gecontacteerd',
        'quote_sent' => 'Offerte verstuurd',
        'won'        => 'Gewonnen',
        'lost'       => 'Verloren',
        // backwards compat — display-only, not offered as new UI actions
        'planned'    => 'Ingepland (oud)',
        'done'       => 'Afgewerkt (oud)',
        'cancelled'  => 'Geannuleerd (oud)',
    ];
}
```

- [ ] **Step 2: Update `updateStatus()` validation to accept new values**

In the existing `updateStatus()` method, update the validation rule from:

```php
'status' => ['required', 'string', 'in:new,contacted,planned,done,cancelled'],
```

to:

```php
'status' => ['required', 'string', 'in:new,viewed,contacted,quote_sent,won,lost,planned,done,cancelled'],
```

- [ ] **Step 3: Update `buildFilteredQuery()` — add `service_category` filter**

In the existing `buildFilteredQuery()` method, add this new `->when()` clause after the existing `service_slug` filter block:

```php
->when($request->filled('service_category'), function ($query) use ($request): void {
    $query->where('service_category', $request->string('service_category')->toString());
})
```

- [ ] **Step 4: Update `index()` — fix stats and add `service_category` to `$filters`**

In the `index()` method, replace the current `$statusCounts` and `$stats` block:

```php
// OLD — replace this:
$statusCounts = CustomerRequest::query()
    ->selectRaw('status, count(*) as total')
    ->whereIn('status', ['new', 'contacted', 'planned'])
    ->groupBy('status')
    ->pluck('total', 'status');

$stats = [
    'new'       => $statusCounts->get('new', 0),
    'contacted' => $statusCounts->get('contacted', 0),
    'planned'   => $statusCounts->get('planned', 0),
    'urgent'    => CustomerRequest::whereNotIn('status', ['done', 'cancelled'])
                        ->where(function ($q): void {
                            $q->where('service_category', 'dringend_lek')
                              ->orWhereIn('urgency_level', ['water_leaking', 'small_leak', 'no_heating', 'no_hot_water', 'urgent']);
                        })->count(),
];
```

with:

```php
// NEW
$statusCounts = CustomerRequest::query()
    ->selectRaw('status, count(*) as total')
    ->whereIn('status', ['new', 'contacted', 'quote_sent'])
    ->groupBy('status')
    ->pluck('total', 'status');

$stats = [
    'new'        => $statusCounts->get('new', 0),
    'contacted'  => $statusCounts->get('contacted', 0),
    'quote_sent' => $statusCounts->get('quote_sent', 0),
    'urgent'     => CustomerRequest::whereNotIn('status', ['won', 'lost'])
                        ->where(function ($q): void {
                            $q->where('service_category', 'dringend_lek')
                              ->orWhereIn('urgency_level', ['water_leaking', 'small_leak', 'no_heating', 'no_hot_water', 'urgent']);
                        })->count(),
];
```

Also add `service_category` to the `$filters` array in the `return view(...)` call:

```php
'filters' => [
    'search'           => $request->string('search')->toString(),
    'status'           => $request->string('status')->toString(),
    'service_slug'     => $request->string('service_slug')->toString(),
    'service_category' => $request->string('service_category')->toString(),  // ADD
    'request_type'     => $request->string('request_type')->toString(),
    'urgency'          => $request->string('urgency')->toString(),
    'customer_type'    => $request->string('customer_type')->toString(),
    'date_from'        => $request->string('date_from')->toString(),
    'date_to'          => $request->string('date_to')->toString(),
],
```

- [ ] **Step 5: Syntax check**

```powershell
php -l app/Http/Controllers/Admin/RequestController.php
```

Expected: `No syntax errors detected`

- [ ] **Step 6: Commit**

```powershell
git add app/Http/Controllers/Admin/RequestController.php
git commit -m "feat(admin): update statuses, add service_category filter, fix stats"
```

---

## Task 5: TDD — `performAction()` Controller Method

**Files:**
- Create: `tests/Feature/Admin/RequestFollowupTest.php`
- Modify: `app/Http/Controllers/Admin/RequestController.php`

- [ ] **Step 1: Create the test directory and file**

```powershell
New-Item -ItemType Directory -Force "tests/Feature/Admin"
```

Create `tests/Feature/Admin/RequestFollowupTest.php` with this content:

```php
<?php

namespace Tests\Feature\Admin;

use App\Models\CustomerRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RequestFollowupTest extends TestCase
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

    // --- mark_viewed ---

    public function test_mark_viewed_sets_status_to_viewed_when_new(): void
    {
        $req = $this->makeRequest(['status' => 'new']);

        $this->withSession($this->adminSession())
            ->post(route('admin.requests.action', $req), ['action' => 'mark_viewed'])
            ->assertRedirect();

        expect($req->fresh()->status)->toBe('viewed');
    }

    public function test_mark_viewed_does_not_downgrade_from_contacted(): void
    {
        $req = $this->makeRequest(['status' => 'contacted']);

        $this->withSession($this->adminSession())
            ->post(route('admin.requests.action', $req), ['action' => 'mark_viewed']);

        expect($req->fresh()->status)->toBe('contacted');
    }

    public function test_mark_viewed_does_not_downgrade_from_won(): void
    {
        $req = $this->makeRequest(['status' => 'won']);

        $this->withSession($this->adminSession())
            ->post(route('admin.requests.action', $req), ['action' => 'mark_viewed']);

        expect($req->fresh()->status)->toBe('won');
    }

    public function test_mark_viewed_does_not_downgrade_from_lost(): void
    {
        $req = $this->makeRequest(['status' => 'lost']);

        $this->withSession($this->adminSession())
            ->post(route('admin.requests.action', $req), ['action' => 'mark_viewed']);

        expect($req->fresh()->status)->toBe('lost');
    }

    // --- mark_contacted ---

    public function test_mark_contacted_sets_status_and_timestamp(): void
    {
        $req = $this->makeRequest(['status' => 'new']);

        $this->withSession($this->adminSession())
            ->post(route('admin.requests.action', $req), ['action' => 'mark_contacted']);

        $fresh = $req->fresh();
        expect($fresh->status)->toBe('contacted');
        expect($fresh->contacted_at)->not->toBeNull();
    }

    public function test_mark_contacted_does_not_overwrite_existing_contacted_at(): void
    {
        $original = now()->subDay()->startOfMinute();
        $req      = $this->makeRequest([
            'status'       => 'new',
            'contacted_at' => $original,
        ]);

        $this->withSession($this->adminSession())
            ->post(route('admin.requests.action', $req), ['action' => 'mark_contacted']);

        expect($req->fresh()->contacted_at->timestamp)->toBe($original->timestamp);
    }

    // --- mark_quote_sent ---

    public function test_mark_quote_sent_sets_status_and_timestamp(): void
    {
        $req = $this->makeRequest(['status' => 'contacted']);

        $this->withSession($this->adminSession())
            ->post(route('admin.requests.action', $req), ['action' => 'mark_quote_sent']);

        $fresh = $req->fresh();
        expect($fresh->status)->toBe('quote_sent');
        expect($fresh->quote_sent_at)->not->toBeNull();
    }

    public function test_mark_quote_sent_does_not_overwrite_existing_quote_sent_at(): void
    {
        $original = now()->subHours(3)->startOfMinute();
        $req      = $this->makeRequest([
            'status'        => 'contacted',
            'quote_sent_at' => $original,
        ]);

        $this->withSession($this->adminSession())
            ->post(route('admin.requests.action', $req), ['action' => 'mark_quote_sent']);

        expect($req->fresh()->quote_sent_at->timestamp)->toBe($original->timestamp);
    }

    // --- mark_won ---

    public function test_mark_won_sets_status_and_timestamp(): void
    {
        $req = $this->makeRequest(['status' => 'quote_sent']);

        $this->withSession($this->adminSession())
            ->post(route('admin.requests.action', $req), ['action' => 'mark_won']);

        $fresh = $req->fresh();
        expect($fresh->status)->toBe('won');
        expect($fresh->won_at)->not->toBeNull();
    }

    public function test_mark_won_does_not_overwrite_existing_won_at(): void
    {
        $original = now()->subHour()->startOfMinute();
        $req      = $this->makeRequest([
            'status' => 'quote_sent',
            'won_at' => $original,
        ]);

        $this->withSession($this->adminSession())
            ->post(route('admin.requests.action', $req), ['action' => 'mark_won']);

        expect($req->fresh()->won_at->timestamp)->toBe($original->timestamp);
    }

    // --- mark_lost ---

    public function test_mark_lost_sets_status_and_timestamp(): void
    {
        $req = $this->makeRequest(['status' => 'quote_sent']);

        $this->withSession($this->adminSession())
            ->post(route('admin.requests.action', $req), ['action' => 'mark_lost']);

        $fresh = $req->fresh();
        expect($fresh->status)->toBe('lost');
        expect($fresh->lost_at)->not->toBeNull();
    }

    public function test_mark_lost_does_not_overwrite_existing_lost_at(): void
    {
        $original = now()->subHour()->startOfMinute();
        $req      = $this->makeRequest([
            'status'  => 'quote_sent',
            'lost_at' => $original,
        ]);

        $this->withSession($this->adminSession())
            ->post(route('admin.requests.action', $req), ['action' => 'mark_lost']);

        expect($req->fresh()->lost_at->timestamp)->toBe($original->timestamp);
    }

    // --- validation ---

    public function test_invalid_action_returns_validation_error(): void
    {
        $req = $this->makeRequest();

        $this->withSession($this->adminSession())
            ->post(route('admin.requests.action', $req), ['action' => 'do_something_invalid'])
            ->assertSessionHasErrors('action');
    }

    public function test_unauthenticated_request_redirects_to_login(): void
    {
        $req = $this->makeRequest();

        $this->post(route('admin.requests.action', $req), ['action' => 'mark_viewed'])
            ->assertRedirect(route('admin.login'));
    }
}
```

- [ ] **Step 2: Run tests — expect failures (method does not exist yet)**

```powershell
php artisan test tests/Feature/Admin/RequestFollowupTest.php
```

Expected: multiple failures with `BadMethodCallException: Method ... does not exist` or `404` for the action route.

- [ ] **Step 3: Implement `performAction()` and private helpers in the controller**

Open `app/Http/Controllers/Admin/RequestController.php`. Add these methods **before** the `buildFilteredQuery()` private method:

```php
public function performAction(Request $request, CustomerRequest $customerRequest): RedirectResponse
{
    $validated = $request->validate([
        'action' => ['required', 'string', 'in:mark_viewed,mark_contacted,mark_quote_sent,mark_won,mark_lost'],
    ]);

    match ($validated['action']) {
        'mark_viewed'     => $this->applyMarkViewed($customerRequest),
        'mark_contacted'  => $this->applyMarkContacted($customerRequest),
        'mark_quote_sent' => $this->applyMarkQuoteSent($customerRequest),
        'mark_won'        => $this->applyMarkWon($customerRequest),
        'mark_lost'       => $this->applyMarkLost($customerRequest),
    };

    return back()->with('success', 'action_applied');
}

private function applyMarkViewed(CustomerRequest $customerRequest): void
{
    if ($customerRequest->status === 'new') {
        $customerRequest->update(['status' => 'viewed']);
    }
}

private function applyMarkContacted(CustomerRequest $customerRequest): void
{
    $customerRequest->update([
        'status'       => 'contacted',
        'contacted_at' => $customerRequest->contacted_at ?? now(),
    ]);
}

private function applyMarkQuoteSent(CustomerRequest $customerRequest): void
{
    $customerRequest->update([
        'status'        => 'quote_sent',
        'quote_sent_at' => $customerRequest->quote_sent_at ?? now(),
    ]);
}

private function applyMarkWon(CustomerRequest $customerRequest): void
{
    $customerRequest->update([
        'status' => 'won',
        'won_at' => $customerRequest->won_at ?? now(),
    ]);
}

private function applyMarkLost(CustomerRequest $customerRequest): void
{
    $customerRequest->update([
        'status'  => 'lost',
        'lost_at' => $customerRequest->lost_at ?? now(),
    ]);
}
```

- [ ] **Step 4: Run tests — expect all to pass**

```powershell
php artisan test tests/Feature/Admin/RequestFollowupTest.php
```

Expected: all tests pass (`PASS`). If any fail, check the failure message and fix before proceeding.

- [ ] **Step 5: Commit**

```powershell
git add tests/Feature/Admin/RequestFollowupTest.php app/Http/Controllers/Admin/RequestController.php
git commit -m "feat(admin): add performAction with status/timestamp logic (TDD)"
```

---

## Task 6: TDD — `updateInternalNotes()` Controller Method

**Files:**
- Modify: `tests/Feature/Admin/RequestFollowupTest.php`
- Modify: `app/Http/Controllers/Admin/RequestController.php`

- [ ] **Step 1: Add tests to the existing test file**

Open `tests/Feature/Admin/RequestFollowupTest.php`. Add these three test methods at the end of the class (before the closing `}`):

```php
// --- updateInternalNotes ---

public function test_update_internal_notes_saves_memo(): void
{
    $req = $this->makeRequest();

    $this->withSession($this->adminSession())
        ->patch(route('admin.requests.internal-notes.update', $req), [
            'internal_notes' => 'Klant gebeld op 10/06. Wacht op foto.',
        ])
        ->assertRedirect();

    expect($req->fresh()->internal_notes)->toBe('Klant gebeld op 10/06. Wacht op foto.');
}

public function test_update_internal_notes_accepts_null_to_clear_memo(): void
{
    $req = $this->makeRequest(['internal_notes' => 'Oude memo']);

    $this->withSession($this->adminSession())
        ->patch(route('admin.requests.internal-notes.update', $req), [
            'internal_notes' => null,
        ]);

    expect($req->fresh()->internal_notes)->toBeNull();
}

public function test_update_internal_notes_rejects_over_2000_chars(): void
{
    $req = $this->makeRequest();

    $this->withSession($this->adminSession())
        ->patch(route('admin.requests.internal-notes.update', $req), [
            'internal_notes' => str_repeat('x', 2001),
        ])
        ->assertSessionHasErrors('internal_notes');
}
```

- [ ] **Step 2: Run the new tests — expect failures**

```powershell
php artisan test tests/Feature/Admin/RequestFollowupTest.php --filter="internal_notes"
```

Expected: all 3 fail with 404 or method not found.

- [ ] **Step 3: Implement `updateInternalNotes()` in the controller**

Open `app/Http/Controllers/Admin/RequestController.php`. Add this method after `performAction()`:

```php
public function updateInternalNotes(Request $request, CustomerRequest $customerRequest): RedirectResponse
{
    $validated = $request->validate([
        'internal_notes' => ['nullable', 'string', 'max:2000'],
    ]);

    $customerRequest->update([
        'internal_notes' => $validated['internal_notes'] ?? null,
    ]);

    return back()->with('success', 'internal_notes_updated');
}
```

- [ ] **Step 4: Run all tests — expect all to pass**

```powershell
php artisan test tests/Feature/Admin/RequestFollowupTest.php
```

Expected: all tests pass. Also run the full suite to check for regressions:

```powershell
php artisan test
```

Expected: all existing tests continue to pass.

- [ ] **Step 5: Commit**

```powershell
git add tests/Feature/Admin/RequestFollowupTest.php app/Http/Controllers/Admin/RequestController.php
git commit -m "feat(admin): add updateInternalNotes with validation (TDD)"
```

---

## Task 7: CSS — New Badge, Quick-Action, Summary Block, Internal Notes Card Styles

**Files:**
- Modify: `resources/css/pages/admin.css`

- [ ] **Step 1: Append new status badge classes**

Open `resources/css/pages/admin.css`. After the existing `.admin-status-cancelled` rule block, append:

```css
/* ================================
   Status badges — new follow-up values
================================ */

.admin-status-viewed {
    background: #f1f5f9;
    color: #475569;
}

.admin-status-quote_sent {
    background: #fef3c7;
    color: #92400e;
}

.admin-status-won {
    background: #dcfce7;
    color: #166534;
}

.admin-status-lost {
    background: rgba(229, 71, 63, 0.10);
    color: #b52a24;
}
```

- [ ] **Step 2: Append quick-action grid and button styles**

After the new badge CSS, append:

```css
/* ================================
   Quick action buttons
================================ */

.admin-quick-action-grid {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 16px;
    padding-top: 18px;
    border-top: 1px solid var(--color-border);
}

.admin-quick-action-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 8px 14px;
    border: 1px solid var(--color-border);
    border-radius: 999px;
    background: #f8fbff;
    color: var(--color-primary-dark);
    font: inherit;
    font-size: 0.82rem;
    font-weight: 900;
    cursor: pointer;
    transition: background 0.15s, border-color 0.15s;
    white-space: nowrap;
    text-align: center;
}

.admin-quick-action-btn:hover:not(:disabled) {
    background: #dbeafe;
    border-color: rgba(15, 102, 194, 0.35);
}

.admin-quick-action-won {
    background: #dcfce7;
    border-color: #bbf7d0;
    color: #166534;
}

.admin-quick-action-won:hover:not(:disabled) {
    background: #bbf7d0;
}

.admin-quick-action-lost {
    background: rgba(229, 71, 63, 0.08);
    border-color: rgba(229, 71, 63, 0.20);
    color: #b52a24;
}

.admin-quick-action-lost:hover:not(:disabled) {
    background: rgba(229, 71, 63, 0.15);
}

.admin-quick-action-disabled,
.admin-quick-action-btn:disabled {
    opacity: 0.38;
    cursor: not-allowed;
    pointer-events: none;
}

.admin-current-status-row {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 4px;
}

.admin-current-status-label {
    font-size: 0.82rem;
    font-weight: 900;
    color: var(--color-muted);
}
```

- [ ] **Step 3: Append summary block styles**

```css
/* ================================
   Request summary block
================================ */

.admin-summary-block {
    background: #f8fbff;
    border-color: #dbeafe;
}

.admin-summary-list {
    margin: 0;
    padding: 0;
    list-style: none;
    display: grid;
    gap: 8px;
}

.admin-summary-line {
    display: flex;
    align-items: baseline;
    gap: 8px;
    color: var(--color-primary-dark);
    font-size: 0.92rem;
    font-weight: 800;
    line-height: 1.4;
}

.admin-summary-line::before {
    content: "·";
    color: var(--color-primary);
    font-weight: 900;
    flex-shrink: 0;
}
```

- [ ] **Step 4: Append internal notes card styles**

```css
/* ================================
   Internal notes card (sidebar memo)
================================ */

.admin-internal-notes-card {
    /* inherits admin-detail-card padding and border */
}

.admin-internal-notes-card h2 {
    margin-bottom: 14px;
}

.admin-internal-notes-card form {
    display: grid;
    gap: 12px;
}

.admin-internal-notes-card label {
    display: grid;
    gap: 7px;
    color: var(--color-primary-dark);
    font-size: 0.88rem;
    font-weight: 900;
}

.admin-internal-notes-card textarea {
    width: 100%;
    min-height: 96px;
    border: 1px solid var(--color-border);
    border-radius: 14px;
    padding: 12px 14px;
    background: var(--color-white);
    color: var(--color-text);
    font: inherit;
    resize: vertical;
}

.admin-internal-notes-card textarea:focus {
    outline: none;
    border-color: rgba(15, 102, 194, 0.55);
    box-shadow: 0 0 0 4px rgba(15, 102, 194, 0.1);
}
```

- [ ] **Step 5: Append mobile responsive rules**

Open `resources/css/pages/admin.css`. Find the final `@media (max-width: 680px)` block (the one that contains `.admin-note-meta` rules near the bottom). Add these two rules **inside** that block, just before its closing `}`:

```css
    .admin-quick-action-grid {
        flex-direction: column;
    }

    .admin-quick-action-btn {
        width: 100%;
        min-height: 44px;
    }
```

- [ ] **Step 6: Commit**

```powershell
git add resources/css/pages/admin.css
git commit -m "style(admin): add follow-up status badges, quick-action buttons, summary block CSS"
```

---

## Task 8: Index View — service_category Filter + Stats 4th Card

**Files:**
- Modify: `resources/views/admin/requests/index.blade.php`

- [ ] **Step 1: Add `service_category` filter to the filter form**

Open `resources/views/admin/requests/index.blade.php`. In the filter form (inside `<form class="admin-filter-form" ...>`), add this label block **after** the existing `service_slug` filter label block (around line 130):

```blade
<label>
    <span>Categorie</span>
    <select name="service_category">
        <option value="">Alle categorieën</option>

        @foreach ($serviceCategoryLabels as $catValue => $catLabel)
            <option value="{{ $catValue }}"
                {{ $filters['service_category'] === $catValue ? 'selected' : '' }}>
                {{ $catLabel }}
            </option>
        @endforeach
    </select>
</label>
```

- [ ] **Step 2: Update the 4th stats card (planned → quote_sent)**

Find this block in the stats row (around line 78):

```blade
<a class="admin-stat-card" href="{{ route('admin.requests.index', ['status' => 'planned']) }}">
    <span class="admin-stat-number">{{ $stats['planned'] }}</span>
    <span class="admin-stat-label">Ingepland</span>
</a>
```

Replace it with:

```blade
<a class="admin-stat-card" href="{{ route('admin.requests.index', ['status' => 'quote_sent']) }}">
    <span class="admin-stat-number">{{ $stats['quote_sent'] }}</span>
    <span class="admin-stat-label">Offerte verstuurd</span>
</a>
```

Also update the 3rd stats card label from "Te contacteren" to "Gecontacteerd":

Find:
```blade
<a class="admin-stat-card" href="{{ route('admin.requests.index', ['status' => 'contacted']) }}">
    <span class="admin-stat-number">{{ $stats['contacted'] }}</span>
    <span class="admin-stat-label">Te contacteren</span>
</a>
```

Replace with:
```blade
<a class="admin-stat-card" href="{{ route('admin.requests.index', ['status' => 'contacted']) }}">
    <span class="admin-stat-number">{{ $stats['contacted'] }}</span>
    <span class="admin-stat-label">Gecontacteerd</span>
</a>
```

- [ ] **Step 3: Commit**

```powershell
git add resources/views/admin/requests/index.blade.php
git commit -m "feat(admin): add service_category filter and update stats bar"
```

---

## Task 9: Show View — Summary Block, Quick Actions, Internal Notes Form

**Files:**
- Modify: `resources/views/admin/requests/show.blade.php`

- [ ] **Step 1: Add flash messages for new session keys**

Open `resources/views/admin/requests/show.blade.php`. Find the existing success flash message block (around line 148). Add two new messages after the existing ones:

```blade
@if (session('success') === 'status_updated')
    <div class="form-success">
        Status werd opgeslagen.
    </div>
@endif

@if (session('success') === 'note_created')
    <div class="form-success">
        Notitie werd toegevoegd.
    </div>
@endif

{{-- ADD THESE TWO: --}}
@if (session('success') === 'action_applied')
    <div class="form-success">
        Status werd bijgewerkt.
    </div>
@endif

@if (session('success') === 'internal_notes_updated')
    <div class="form-success">
        Memo werd opgeslagen.
    </div>
@endif
```

- [ ] **Step 2: Replace the status dropdown form with current-status display + quick-action grid**

In the sidebar (inside `.admin-quick-actions-card`), find and **remove** the entire `<form class="admin-status-form" ...>` block (from `<form class="admin-status-form"` through `</form>`, lines ~178–203 in the current file).

In its place, insert:

```blade
{{-- Current status (read-only badge) --}}
<div class="admin-current-status-row" style="margin-top: 16px; margin-bottom: 4px;">
    <span class="admin-current-status-label">Huidige status</span>
    <span class="admin-status admin-status-{{ $customerRequest->status }}">
        {{ $statuses[$customerRequest->status] ?? $customerRequest->status }}
    </span>
</div>

{{-- Quick-action buttons --}}
<div class="admin-quick-action-grid">
    {{-- Bekeken: only enabled when status is 'new' --}}
    @if ($customerRequest->status === 'new')
        <form method="POST" action="{{ route('admin.requests.action', $customerRequest) }}">
            @csrf
            <input type="hidden" name="action" value="mark_viewed">
            <button type="submit" class="admin-quick-action-btn">Bekeken</button>
        </form>
    @else
        <button type="button" class="admin-quick-action-btn admin-quick-action-disabled" disabled>Bekeken</button>
    @endif

    <form method="POST" action="{{ route('admin.requests.action', $customerRequest) }}">
        @csrf
        <input type="hidden" name="action" value="mark_contacted">
        <button type="submit" class="admin-quick-action-btn">Gecontacteerd</button>
    </form>

    <form method="POST" action="{{ route('admin.requests.action', $customerRequest) }}">
        @csrf
        <input type="hidden" name="action" value="mark_quote_sent">
        <button type="submit" class="admin-quick-action-btn">Offerte verstuurd</button>
    </form>

    <form method="POST" action="{{ route('admin.requests.action', $customerRequest) }}">
        @csrf
        <input type="hidden" name="action" value="mark_won">
        <button type="submit" class="admin-quick-action-btn admin-quick-action-won">Gewonnen</button>
    </form>

    <form method="POST" action="{{ route('admin.requests.action', $customerRequest) }}">
        @csrf
        <input type="hidden" name="action" value="mark_lost">
        <button type="submit" class="admin-quick-action-btn admin-quick-action-lost">Verloren</button>
    </form>
</div>
```

- [ ] **Step 3: Add the summary block as the first card in `.admin-detail-main`**

Find the opening `<div class="admin-detail-main">` tag (around line 270). Immediately after it (before the `@php $missingItems` block), insert:

```blade
{{-- Summary block — rendered first in main column --}}
@php $summaryLines = $customerRequest->getSummaryLines(); @endphp
@if (! empty($summaryLines))
    <div class="admin-detail-card admin-summary-block">
        <h2>Samenvatting</h2>
        <ul class="admin-summary-list">
            @foreach ($summaryLines as $line)
                <li class="admin-summary-line">{{ $line }}</li>
            @endforeach
        </ul>
    </div>
@endif
```

- [ ] **Step 4: Add the internal notes card at the bottom of the sidebar**

Find the closing `</aside>` tag (end of the left sidebar, after the Klantgegevens card). Insert this block **before** `</aside>`:

```blade
{{-- Internal memo (fixed short note, not the follow-up log) --}}
<div class="admin-detail-card admin-internal-notes-card">
    <h2>Interne memo</h2>
    <form method="POST"
        action="{{ route('admin.requests.internal-notes.update', $customerRequest) }}">
        @csrf
        @method('PATCH')

        <label>
            <span>Korte interne samenvatting</span>
            <textarea name="internal_notes" rows="4" maxlength="2000"
                placeholder="Bijv. offerte doorgestuurd, klant wacht op keuring...">{{ old('internal_notes', $customerRequest->internal_notes) }}</textarea>
        </label>

        @error('internal_notes')
            <p class="field-error-text">{{ $message }}</p>
        @enderror

        <button class="button button-secondary" type="submit">
            Memo opslaan
        </button>
    </form>
</div>
```

- [ ] **Step 5: Syntax and blade check**

```powershell
php -l resources/views/admin/requests/show.blade.php
```

(PHP lint will catch most issues; Blade directives are valid PHP as long as tags are balanced.)

- [ ] **Step 6: Commit**

```powershell
git add resources/views/admin/requests/show.blade.php
git commit -m "feat(admin): add summary block, quick-action buttons, internal memo form to request detail"
```

---

## Task 10: Final Verification

- [ ] **Step 1: Run full test suite**

```powershell
php artisan test
```

Expected: all tests pass. No regressions. If failures appear, fix before continuing.

- [ ] **Step 2: Build frontend assets**

```powershell
npm run build
```

Expected: build completes without errors.

- [ ] **Step 3: Manual smoke test — follow this checklist**

Open the admin dashboard at `/admin/requests` (log in if needed) and verify:

1. **Index — stats bar:** 4th stat card shows "Offerte verstuurd" (not "Ingepland"). 3rd shows "Gecontacteerd".
2. **Index — filter:** "Categorie" filter dropdown is visible and populated. Selecting a category filters the results.
3. **Index — badges:** Records with status `viewed`, `quote_sent`, `won`, `lost` display correct coloured badges (you may need a seeded record or manually set via tinker).
4. **Detail — summary block:** Open a request with a `service_category` set. The "Samenvatting" card appears at the top of the main column with at least one line.
5. **Detail — no summary:** Open a request with no `service_category`, no attachments, no preferred_time, no brand. The summary block does NOT appear.
6. **Detail — quick actions:** The "Bekeken" button is active when status is `new`. Click it → status changes to `viewed`. The "Bekeken" button is now disabled (greyed out).
7. **Detail — quick actions no downgrade:** With a `viewed` record, the "Bekeken" button is disabled and clicking it (if somehow enabled) does not change the status.
8. **Detail — contacted timestamp:** Click "Gecontacteerd" → status = `contacted`, `contacted_at` is set. Click it again → status stays `contacted`, `contacted_at` is unchanged.
9. **Detail — internal memo:** Type a short memo in "Interne memo", click "Memo opslaan" → green flash message appears, reload page → memo text is still there.
10. **Detail — WhatsApp link:** WhatsApp link still opens correctly for a request with a phone number.
11. **Detail — threaded notes:** Adding a new note via "Interne notities" still works.
12. **CSV export:** Download CSV from the index page — file opens correctly.
13. **Public form:** Submit a test request via `/nl/aanvraag` — it appears in admin with status `new` and no errors.

- [ ] **Step 4: Verify old status records display correctly**

If any records exist with status `planned`, `done`, or `cancelled`, open one in the admin detail view. The badge should show "Ingepland (oud)", "Afgewerkt (oud)", or "Geannuleerd (oud)" — not a broken/empty badge.

To create one for testing if needed:
```powershell
php artisan tinker --execute="App\Models\CustomerRequest::first()->update(['status' => 'planned'])"
```

- [ ] **Step 5: Final commit with summary commit message**

```powershell
git add -A
git status
```

Verify no unintended files. Then:

```powershell
git commit -m "feat(admin): add request follow-up workflow

- New status flow: viewed, contacted, quote_sent, won, lost
- Quick-action buttons replace status dropdown on detail page
- contacted_at / quote_sent_at / won_at / lost_at timestamps (set once)
- internal_notes memo field (separate from threaded notes log)
- Summary block on detail page (rule-based, no AI)
- service_category filter on request index
- Old statuses planned/done/cancelled preserved as display-only"
```

---

## Risk Register

| Risk | Mitigation |
|---|---|
| `status` validation in `updateStatus()` must include old values to avoid breaking existing records | Done in Task 4 — validation accepts all 9 status values |
| `mark_viewed` must not downgrade higher statuses | Tested in Task 5 — `applyMarkViewed()` guards on `status === 'new'` only |
| `contacted_at` (and other timestamps) must not be overwritten | Tested in Task 5 — `??` operator preserves existing value |
| CSS changes to `admin.css` could break existing layout | Only appending — no existing rules modified |
| `getSummaryLines()` calls `attachments()->count()` which issues a query | Uses `relationLoaded()` guard same as `getMissingInfoChecklist()` — no N+1 |
| `$filters['service_category']` missing from `$filters` array could cause Blade undefined key error | Added explicitly in Task 4 `index()` |
| Old stat array key `$stats['planned']` used in Blade | Task 4 and Task 8 both update the key and the view together — commit includes both |
