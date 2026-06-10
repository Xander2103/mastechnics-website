# Design Spec: Admin Request Follow-up Foundation

**Date:** 2026-06-10
**Project:** Mastechnics Laravel 12 — Admin dashboard
**Sprint:** Admin Request Follow-up Foundation
**Status:** Approved

---

## 1. Goal

Turn the existing admin request overview into a practical follow-up tool for Martin. He can see the status of each request at a glance, advance it through a workflow with quick-action buttons, leave a short internal memo, and get a clear readable summary of what the request is about — all without touching the existing public form, email system, uploads, or admin login.

---

## 2. What Is Reused Without Change

| Component | Location | Note |
|---|---|---|
| `CustomerRequestNote` model + threaded log | `app/Models/CustomerRequestNote.php` | Main follow-up timeline. Untouched. |
| WhatsApp link + phone normalization | `show.blade.php` (lines 117–134) | Already correct. Not rewritten. |
| Existing filters (service_slug, urgency, customer_type, date, search) | `index.blade.php` + `RequestController::buildFilteredQuery` | Kept. `service_category` filter added alongside. |
| Stats bar (new/contacted/planned/urgent counts) | `index.blade.php` | Kept. Stats labels updated for new status flow. |
| CSV export | `exportCsv()` | Kept. New status labels added to the map. |
| Detail page two-column layout | `show.blade.php` | Structure kept. Content augmented. |
| Status/urgency badge CSS | `admin.css` | Extended, not replaced. |
| Mobile-responsive table card layout | `admin.css` | Untouched. |
| Admin auth, middleware, routes | `web.php`, `AuthController` | Untouched. |
| Public request form, email system, file uploads | `CustomerRequestController`, `CustomerRequest`, migrations | Untouched. |

---

## 3. Database Changes

### 3.1 New migration: `add_followup_fields_to_customer_requests_table`

Adds to `customer_requests`:

| Column | Type | Nullable | Default | Purpose |
|---|---|---|---|---|
| `internal_notes` | `text` | yes | `null` | Fixed admin memo/summary (not a log) |
| `contacted_at` | `timestamp` | yes | `null` | Set once when status → contacted |
| `quote_sent_at` | `timestamp` | yes | `null` | Set once when status → quote_sent |
| `won_at` | `timestamp` | yes | `null` | Set once when status → won |
| `lost_at` | `timestamp` | yes | `null` | Set once when status → lost |

**`status` column is not changed structurally.** It already exists as `string default 'new'`. The new status values are enforced at the application layer only.

### 3.2 `CustomerRequest` model updates

Add to `$fillable`:
```
internal_notes, contacted_at, quote_sent_at, won_at, lost_at
```

Add to `$casts`:
```php
'contacted_at'  => 'datetime',
'quote_sent_at' => 'datetime',
'won_at'        => 'datetime',
'lost_at'       => 'datetime',
```

Add new method `getSummaryLines(): array` — see Section 6.

---

## 4. Status Flow

### 4.1 New canonical status values

| Value | Label (NL) | Badge colour |
|---|---|---|
| `new` | Nieuw | Blue (existing) |
| `viewed` | Bekeken | Light grey-blue (new) |
| `contacted` | Gecontacteerd | Sky blue (existing) |
| `quote_sent` | Offerte verstuurd | Amber (new) |
| `won` | Gewonnen | Green (new) |
| `lost` | Verloren | Red-muted (new) |

### 4.2 Backwards compatibility for old values

Records with `planned`, `done`, `cancelled` remain valid in the database. They are displayed using a fallback map:

```php
'planned'   => 'Ingepland (oud)',
'done'      => 'Afgewerkt (oud)',
'cancelled' => 'Geannuleerd (oud)',
```

The UI status dropdown (if any remains) and the quick-action buttons only offer the 6 new values. Old statuses are read-only/display-only.

### 4.3 Quick-action transition rules

| Action | Allowed when | Sets status | Sets timestamp |
|---|---|---|---|
| `mark_viewed` | status === `new` only | `viewed` | — |
| `mark_contacted` | any status | `contacted` | `contacted_at = now()` if null |
| `mark_quote_sent` | any status | `quote_sent` | `quote_sent_at = now()` if null |
| `mark_won` | any status | `won` | `won_at = now()` if null |
| `mark_lost` | any status | `lost` | `lost_at = now()` if null |

`mark_viewed` explicitly does **not** run if the current status is `viewed`, `contacted`, `quote_sent`, `won`, or `lost`. It will silently redirect with a no-op (or with a neutral flash message).

Timestamps are **only set if null** — so they record the first time that milestone was reached, not the last time the button was clicked.

---

## 5. Routes

### 5.1 New route: quick action

```
POST /admin/requests/{customerRequest}/action
Name: admin.requests.action
```

Request body: `action` (string, validated against enum: `mark_viewed`, `mark_contacted`, `mark_quote_sent`, `mark_won`, `mark_lost`).

Controller method: `RequestController::performAction(Request $request, CustomerRequest $customerRequest): RedirectResponse`

### 5.2 New route: internal notes

```
PATCH /admin/requests/{customerRequest}/internal-notes
Name: admin.requests.internal-notes.update
```

Request body: `internal_notes` (nullable string, max 2000 chars).

Controller method: `RequestController::updateInternalNotes(Request $request, CustomerRequest $customerRequest): RedirectResponse`

### 5.3 Existing route kept

`PATCH /admin/requests/{customerRequest}/status` (`admin.requests.update-status`) — kept for backwards compatibility. The Blade dropdown that used it will be **removed** from the UI (quick-action buttons replace it), but the route stays so existing bookmarks/scripts don't break.

### 5.4 Index filter

`buildFilteredQuery()` gains a `service_category` filter alongside existing `service_slug`. Both can be active simultaneously; they are independent `when()` clauses.

---

## 6. Request Summary Block

### 6.1 Location on detail page

Rendered as the **first card in `.admin-detail-main`**, before "Aanvraaggegevens". CSS class: `.admin-summary-block`.

### 6.2 Implementation

New method on `CustomerRequest`:

```php
public function getSummaryLines(): array
```

Returns a `string[]` of short Dutch sentences. Logic (evaluated in order, each independently):

| Condition | Line |
|---|---|
| `service_category` is set | `"Aanvraag voor {$categoryLabel}."` |
| `urgency_level` in `[water_leaking, small_leak, no_heating, no_hot_water, urgent]` | `"Klant geeft aan dat het dringend is."` |
| `urgency_level === 'within_days'` | `"Klant wenst behandeling binnen enkele dagen."` |
| `attachments` count > 0 (use `attachments_count` if loaded, else `attachments()->count()`) | `"Er {zijn/is} {n} bijlage(n) toegevoegd."` |
| `preferred_time` is set | `"Voorkeurmoment: {$preferred_time}."` |
| `service_category === 'airco_offerte'` and `rooms` in answers is non-empty | `"{n} kamer(s) opgegeven voor offerte."` |
| `brand` or `device_model` is set | `"Toestel: {$brand} {$device_model}."` (trim) |

If `getSummaryLines()` returns an empty array, the summary block is not rendered.

`$categoryLabel` is looked up from `config('request-flow.service_categories')` inside the method (same pattern as the controller already uses).

### 6.3 Attachment count

`show()` in `RequestController` already calls `->load(['attachments', 'notes'])`. The `getSummaryLines()` method checks `relationLoaded('attachments')` before querying (same pattern as `getMissingInfoChecklist()`).

---

## 7. Detail Page Layout Changes

### 7.1 Sidebar (left column)

**Remove:** Status dropdown form + "Status opslaan" button.

**Add (top of sidebar, before WhatsApp):**
- Current status badge (read-only display)
- Quick-action button grid (`.admin-quick-action-grid`)
  - Up to 5 buttons depending on current status
  - Each submits `POST admin.requests.action` with the relevant `action` value
  - Buttons are visually differentiated: won = green, lost = red-muted, others = neutral
  - The **Bekeken** button (`mark_viewed`) is rendered as visually disabled (muted, `pointer-events: none`) when the current status is anything other than `new`. All other action buttons are always clickable regardless of current status.

**Add (bottom of sidebar, below Klantgegevens):**
- Internal notes card (`.admin-internal-notes-card`)
  - Label: "Interne memo"
  - Textarea (max 2000 chars), pre-filled with current `internal_notes` value
  - "Memo opslaan" button
  - `PATCH admin.requests.internal-notes.update`

### 7.2 Main column (right)

**Add (first card, new):** Summary block (`.admin-summary-block`) — see Section 6.

**Keep unchanged:** Aanvraaggegevens, Omschrijving, Locatie, Technische gegevens, Bijlagen cards.

**Full-width below layout — keep unchanged:** Interne notities (threaded log), Alle antwoorden.

---

## 8. Index Page Changes

### 8.1 Filter addition

Add `service_category` filter select to `admin-filter-form`. Populates from `config('request-flow.service_categories')` (same source as controller already uses for `$serviceCategoryLabels`).

### 8.2 Stats bar

The index currently has 4 stat cards: **Nieuwe aanvragen** (`new`), **Dringend** (urgency-based), **Te contacteren** (`contacted`), **Ingepland** (`planned`).

With `planned` deprecated, replace the 4th card:

| Card | Old query | New query | New label |
|---|---|---|---|
| Nieuwe aanvragen | `status = new` | unchanged | Nieuwe aanvragen |
| Dringend | urgency conditions | unchanged | Dringend |
| Te contacteren | `status = contacted` | unchanged | Gecontacteerd |
| Ingepland | `status = planned` | `status = quote_sent` | Offerte verstuurd |

Update `$stats` array key from `planned` to `quote_sent` in `index()`. Update the Blade template accordingly.

### 8.3 Table

No structural changes. Status badges for new values will display correctly once CSS is added.

---

## 9. CSS Changes (`resources/css/pages/admin.css`)

Additions only. No existing rules modified.

### 9.1 New status badges

```css
.admin-status-viewed      { /* light grey-blue */ }
.admin-status-quote_sent  { /* amber */ }
.admin-status-won         { /* green */ }
.admin-status-lost        { /* red-muted */ }
```

### 9.2 Quick-action grid

```css
.admin-quick-action-grid  { /* flex-wrap grid */ }
.admin-quick-action-btn   { /* base quick action button */ }
.admin-quick-action-won   { /* green variant */ }
.admin-quick-action-lost  { /* red-muted variant */ }
.admin-quick-action-disabled { /* muted, pointer-events: none */ }
```

### 9.3 Summary block

```css
.admin-summary-block      { /* card with list of summary lines */ }
.admin-summary-line       { /* individual line style */ }
```

### 9.4 Internal notes card

```css
.admin-internal-notes-card { /* sidebar card for memo textarea */ }
```

Mobile: quick-action buttons stack vertically below 680px, matching existing mobile card pattern.

---

## 10. Controller Summary

All changes in `App\Http\Controllers\Admin\RequestController`:

| Method | Change |
|---|---|
| `getStatuses()` | Add `viewed`, `quote_sent`, `won`, `lost`; keep old values with `(oud)` suffix |
| `index()` | Add `service_category` to filter data passed to view |
| `buildFilteredQuery()` | Add `->when('service_category', ...)` clause |
| `show()` | No structural change; `getSummaryLines()` called in Blade |
| `updateStatus()` | Keep route, update validation to allow new status values |
| `performAction()` | **New.** Validates `action`, applies status + timestamp logic per Section 4.3 |
| `updateInternalNotes()` | **New.** Validates and saves `internal_notes` |

---

## 11. What Is NOT Changed

- Public request form (`/nl/aanvraag`, `/fr/demande`, `/en/request`)
- `CustomerRequestController::store()`
- `NewCustomerRequestMail`, `CustomerRequestConfirmationMail`
- File upload validation and storage
- `AdminUser` model + admin auth middleware
- `CustomerRequestNote` model, migration, and threaded notes UI
- WhatsApp phone normalization logic
- Existing CSS rules (admin.css additions only)
- Multilingual public site

---

## 12. Testing

After migration and implementation:

1. `php artisan migrate` — verify no errors
2. `php artisan test` — all existing tests pass
3. `npm run build` — assets compile without error
4. Manual smoke test:
   - Submit a test request via `/nl/aanvraag`
   - Open it in admin — summary block visible
   - Click "Bekeken" — status changes to `viewed`
   - Click "Bekeken" again — status does NOT change (no-op)
   - Click "Gecontacteerd" — status changes, `contacted_at` set
   - Click "Gecontacteerd" again — status changes, `contacted_at` unchanged
   - Save internal memo — persists on reload
   - Add threaded note — appears in log
   - Existing `planned`/`done`/`cancelled` records display with `(oud)` suffix
   - CSV export still works
   - WhatsApp link still opens correctly
