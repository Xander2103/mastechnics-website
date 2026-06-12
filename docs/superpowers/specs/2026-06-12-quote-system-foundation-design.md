# Quote System Foundation — Design Spec

**Date:** 2026-06-12
**Sprint:** Quote System Foundation
**Project:** Mastechnics Laravel 12 admin

---

## Goal

Allow Martin to create and manage a simple quote (offerte) linked to a customer request, track its lifecycle status, and prepare the data model for later PDF generation. No accounting, no payments, no email sending, no WhatsApp API, no quote line items in this sprint.

---

## Scope Boundaries

**In scope:**
- `quotes` database table and `Quote` Eloquent model
- `QuoteController` with `edit`, `store`, `performAction`
- Quote number generation (`OFF-YYYY-NNNN`)
- Server-side VAT calculation
- Quote card on request detail page (read-only display + action buttons)
- Quote edit page (separate admin page)
- Quote status badges and form CSS
- Feature tests (TDD)

**Out of scope (future sprints):**
- PDF generation
- Quote line items
- Email sending of quotes
- WhatsApp API
- Payment tracking
- Quote revisions / history

---

## Database

### Decision: Separate `quotes` table

Rationale:
- `customer_requests` already has 27 columns; adding 11+ more is technical debt
- A quote is a business document with its own lifecycle, separate from the customer request
- A dedicated `Quote` model is cleaner to pass to a PDF generator later
- Future quote line items (`quote_items` table) fit naturally as children of `quotes`
- No conflict with the existing `customer_requests.quote_sent_at` follow-up flow marker

### `quotes` table schema

```
id                  bigint unsigned PK auto-increment
customer_request_id bigint unsigned FK → customer_requests.id (cascade delete)
quote_number        varchar(20) unique nullable       -- OFF-2026-0001, generated on first save
quote_status        varchar(20) default 'draft'       -- draft | sent | accepted | rejected
title               varchar(200) nullable
description         text nullable
amount_excl_vat     decimal(10,2) nullable
vat_rate            decimal(5,2) default 21.00
amount_vat          decimal(10,2) nullable            -- server-calculated
amount_incl_vat     decimal(10,2) nullable            -- server-calculated
valid_until         date nullable
sent_at             timestamp nullable
accepted_at         timestamp nullable
rejected_at         timestamp nullable
created_at          timestamp
updated_at          timestamp
```

### Relationship

- `CustomerRequest` hasOne `Quote`
- `Quote` belongsTo `CustomerRequest`
- One quote per request for now. Future: hasMany for revisions.

### Existing `customer_requests` columns

No changes. The existing follow-up columns remain:
- `quote_sent_at` — flow marker set by the quick-action "Offerte verstuurd"
- `won_at`, `lost_at` — flow markers set by quick-actions and quote actions

---

## Quote Statuses

| Status | Meaning |
|---|---|
| `draft` | Quote is being prepared (default on creation) |
| `sent` | Quote has been sent to the customer |
| `accepted` | Customer accepted the quote |
| `rejected` | Customer rejected the quote |

No `none` status — the absence of a `quotes` row means no quote yet.

### Mapping to `customer_requests.status`

| Quote action | `quotes.status` | `quotes.timestamp` | `customer_requests.status` | `customer_requests.timestamp` |
|---|---|---|---|---|
| `mark_sent` | `sent` | `sent_at = now() if null` | `quote_sent` | `quote_sent_at = now() if null` |
| `mark_accepted` | `accepted` | `accepted_at = now() if null` | `won` | `won_at = now() if null` |
| `mark_rejected` | `rejected` | `rejected_at = now() if null` | `lost` | `lost_at = now() if null` |

**Rule:** Quote actions always update request status. Existing quick-action buttons (Gewonnen, Verloren) remain and do not touch the quote.

---

## Quote Number Generation

Format: `OFF-YYYY-NNNN` (e.g., `OFF-2026-0001`)

Algorithm:
1. Find the highest existing `quote_number` for the current year via a `LIKE 'OFF-{year}-%'` query on the `quotes` table
2. Parse the 4-digit suffix, increment by 1
3. If none exists for the year, start at 1
4. Zero-pad to 4 digits

```php
private function generateQuoteNumber(): string
{
    $year = now()->year;
    $max = Quote::where('quote_number', 'LIKE', "OFF-{$year}-%")
        ->max('quote_number');
    $next = $max ? ((int) substr($max, -4)) + 1 : 1;
    return 'OFF-' . $year . '-' . str_pad($next, 4, '0', STR_PAD_LEFT);
}
```

**When generated:** On first `store` (save). Once assigned, never regenerated.

**Uniqueness:** The `quote_number` column has a `unique` index. In the extremely unlikely event of a concurrent duplicate (not possible for a solo admin), the DB constraint will throw an exception rather than silently creating a duplicate.

**Limitation:** Max 9999 quotes per year. Sufficient for a small business.

---

## VAT Calculation

Always server-side. Client-side JS preview is a nice-to-have (not required).

```php
$amountVat    = round($amountExclVat * ($vatRate / 100), 2);
$amountInclVat = round($amountExclVat + $amountVat, 2);
```

Stored in `amount_vat` and `amount_incl_vat` on every save.

---

## Routes

Three new routes inside the existing `Route::middleware('admin')` group:

```
GET    /admin/requests/{customerRequest}/quote/edit
       name: admin.requests.quote.edit
       → Admin\QuoteController@edit

POST   /admin/requests/{customerRequest}/quote
       name: admin.requests.quote.store
       → Admin\QuoteController@store

POST   /admin/requests/{customerRequest}/quote/action
       name: admin.requests.quote.action
       → Admin\QuoteController@performAction
```

No PATCH/PUT — `store` handles both create and update (upsert via `updateOrCreate`).

---

## Controller: `Admin\QuoteController`

### `edit(CustomerRequest $customerRequest): View`

- Loads `$customerRequest->quote` (nullable)
- Returns `admin.quotes.edit` view with the request and existing quote (or null)

### `store(Request $request, CustomerRequest $customerRequest): RedirectResponse`

Validation:
```
title            nullable|string|max:200
description      nullable|string
amount_excl_vat  nullable|numeric|min:0
vat_rate         nullable|numeric|min:0
valid_until      nullable|date
```

Logic:
1. Calculate `amount_vat` and `amount_incl_vat` server-side
2. If no quote exists yet, generate `quote_number`
3. `Quote::updateOrCreate(['customer_request_id' => $customerRequest->id], [...data...])`
4. Redirect back to `admin.requests.show` with `success = 'quote_saved'`

### `performAction(Request $request, CustomerRequest $customerRequest): RedirectResponse`

Validation: `action` must be one of `mark_sent|mark_accepted|mark_rejected`

Actions:
- `mark_sent` — see status mapping table above
- `mark_accepted` — see status mapping table above
- `mark_rejected` — see status mapping table above

All use `?? now()` to preserve original timestamps. Redirects back with `success = 'quote_action_applied'`.

---

## Model: `Quote`

```php
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
```

---

## UI: Request Detail Page (`show.blade.php`)

### Quote card position in main column

```
main column:
  1. Summary block        (existing)
  2. Quote card           ← NEW, after summary
  3. Aanvraaggegevens     (existing)
  4. Omschrijving         (existing)
  5. Locatie              (existing)
  6. Technische gegevens  (existing)
  7. Bijlagen             (existing)
```

### Quote card — no quote yet

```
┌─────────────────────────────────────────────────┐
│  Offerte                                        │
│  Nog geen offerte aangemaakt voor deze aanvraag.│
│                                                 │
│  [+ Offerte aanmaken]                           │
└─────────────────────────────────────────────────┘
```

### Quote card — quote exists

```
┌─────────────────────────────────────────────────┐
│  Offerte  [badge: Concept / Verstuurd / ...]    │
│  OFF-2026-0001  ·  Geldig t/m 30/06/2026        │
│                                                 │
│  Airco-installatie 3 kamers                     │
│  Beschrijving indien ingevuld                   │
│                                                 │
│  Excl. BTW    €  1.850,00                       │
│  BTW (21%)    €    388,50                       │
│  Incl. BTW    €  2.238,50                       │
│                                                 │
│  Verstuurd: 12/06/2026 14:30   (indien aanwezig)│
│  Aanvaard:  —                                   │
│  Afgewezen: —                                   │
│                                                 │
│  [✏ Bewerken]  [Verstuurd ▸]  [Gewonnen ▸]  [Verloren ▸] │
└─────────────────────────────────────────────────┘
```

Action buttons visibility rules:
- "Bewerken" button: always shown when a quote exists (links to edit page)
- "Verstuurd" button: shown only when status is `draft`
- "Gewonnen" button: shown only when status is `sent`
- "Verloren" button: shown only when status is `sent`
- When `accepted` or `rejected`: only "Bewerken" remains; no status-transition buttons

### Flash messages

- `success = 'quote_saved'` → "Offerte werd opgeslagen."
- `success = 'quote_action_applied'` → "Offerte-status werd bijgewerkt."

### Existing quick actions

Untouched. Quick-action buttons ("Gewonnen", "Verloren") still work independently. They do not affect the Quote model.

---

## UI: Quote Edit Page (`admin/quotes/edit.blade.php`)

Separate page at `/admin/requests/{id}/quote/edit`.

Fields:
- `title` (text input, optional)
- `description` (textarea, optional)
- `amount_excl_vat` (number input, step 0.01)
- `vat_rate` (number input, default 21, step 0.01)
- `valid_until` (date input, optional)

Read-only preview (nice-to-have JS):
- Show calculated `amount_vat` and `amount_incl_vat` live as user types

Layout: single-column form inside `.admin-detail-card`, back button to request detail, submit button "Offerte opslaan".

---

## CSS Additions (`resources/css/pages/admin.css`)

Append-only. New sections:

```css
/* ================================
   Quote card
================================ */
.admin-quote-card { ... }
.admin-quote-meta-row { ... }
.admin-quote-number { ... }
.admin-quote-amounts { ... }
.admin-quote-amount-row { ... }
.admin-quote-amount-total { ... }
.admin-quote-timestamps { ... }
.admin-quote-actions { ... }

/* ================================
   Quote status badges
================================ */
.admin-quote-status-draft { background: #f1f5f9; color: #475569; }
.admin-quote-status-sent { background: #fef3c7; color: #92400e; }
.admin-quote-status-accepted { background: #dcfce7; color: #166534; }
.admin-quote-status-rejected { background: rgba(229, 71, 63, 0.10); color: #b52a24; }

/* ================================
   Quote edit form
================================ */
.admin-quote-form { ... }
.admin-quote-preview { ... }  /* live JS preview block */

/* @media (max-width: 680px) additions */
```

---

## Tests (`tests/Feature/Admin/QuoteTest.php`)

| Test | What it verifies |
|---|---|
| `test_store_creates_quote_with_vat_calculation` | Quote saved, amounts calculated correctly |
| `test_store_updates_existing_quote` | Subsequent save updates, does not create duplicate |
| `test_store_generates_quote_number_on_first_save` | `OFF-YYYY-NNNN` generated, unique |
| `test_quote_number_increments_per_year` | Second quote gets NNNN+1 |
| `test_store_validates_amount_must_be_numeric` | Rejects non-numeric amount |
| `test_store_validates_amount_must_be_non_negative` | Rejects negative amount |
| `test_store_validates_vat_rate_must_be_non_negative` | Rejects negative vat rate |
| `test_mark_sent_sets_quote_status_and_request_status` | quote→sent, request→quote_sent |
| `test_mark_sent_does_not_overwrite_existing_sent_at` | Preserves original sent_at |
| `test_mark_accepted_sets_quote_status_and_request_status` | quote→accepted, request→won |
| `test_mark_accepted_does_not_overwrite_existing_accepted_at` | Preserves original accepted_at |
| `test_mark_rejected_sets_quote_status_and_request_status` | quote→rejected, request→lost |
| `test_mark_rejected_does_not_overwrite_existing_rejected_at` | Preserves original rejected_at |
| `test_invalid_action_returns_validation_error` | Rejects unknown action |
| `test_unauthenticated_request_redirects_to_login` | Admin middleware enforced |

Existing `RequestFollowupTest` runs unmodified — no regressions.

---

## File Map

| File | Action |
|---|---|
| `database/migrations/[ts]_create_quotes_table.php` | Create |
| `app/Models/Quote.php` | Create |
| `app/Http/Controllers/Admin/QuoteController.php` | Create |
| `tests/Feature/Admin/QuoteTest.php` | Create |
| `resources/views/admin/quotes/edit.blade.php` | Create |
| `app/Models/CustomerRequest.php` | Modify — add `hasOne(Quote::class)` |
| `routes/web.php` | Modify — add 3 quote routes |
| `resources/views/admin/requests/show.blade.php` | Modify — add quote card in main column |
| `resources/css/pages/admin.css` | Modify — append quote CSS |

---

## Risks

| Risk | Mitigation |
|---|---|
| `customer_requests.quote_sent_at` set by both quick-action and quote `mark_sent` | `?? now()` — safe, only sets if null |
| Quick-action "Gewonnen"/"Verloren" and quote actions can set conflicting request status | Accepted as-is: both are valid admin actions. Last one wins. Quote actions document their intent clearly. |
| Quote number `unique` constraint violation on concurrent save | Practically impossible for single admin. DB constraint throws exception as last defence. |
| `$customerRequest->quote` is `null` in Blade | Null-safe operators `?->` everywhere; card shows "no quote" state gracefully |
| `customer_requests.description` is `NOT NULL` at DB level | No impact — Quote has its own `description` column (nullable) |
| VAT decimal rounding | PHP `round(..., 2)` — consistent rounding for small business use |

---

## Verification Checklist

After implementation:
- `php artisan test` — all tests pass (19 existing + new quote tests)
- `npm run build` — CSS build succeeds
- `php -l` on all modified PHP files
- `php artisan route:list --name=admin.requests.quote` — 3 routes registered
