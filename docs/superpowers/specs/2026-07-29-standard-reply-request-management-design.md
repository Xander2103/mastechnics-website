# Standard Reply, Request Deletion, Account Settings & VAT — Design Spec

**Date:** 2026-07-29
**Status:** Approved (design accepted by Xander; refinements below incorporated)

## Scope

Four features for the Mastechnics site (Laravel 12, PHPUnit, session-flag admin auth):

1. **Standard reply** — admin sends a localized (nl/fr/en) standard reply email to a customer request from the detail page, with idempotency, explicit resend, and a WhatsApp variant sharing the exact same generated text.
2. **Safe request deletion** — hard delete of a customer request, blocked when a quote exists, transactional, with attachment file cleanup.
3. **Admin account settings** — email and password change requiring the current password.
4. **VAT number** — `config('site.vat_number')` rendered in the footer and contact page as exactly `BE 0760.768.228`.

## Locked decisions

- Block request deletion when a quote exists (app-level guard; the DB FK is `cascadeOnDelete` and would silently destroy the quote).
- Skip DE locale; nl/fr/en only, nl fallback.
- Sending the standard reply automatically sets the request to `contacted` (+ `contacted_at`), reusing the existing `applyMarkContacted` idempotent pattern. Status is only advanced from `new`/`viewed`; a later status (e.g. `won`) is never regressed.
- Reuse `App\Services\MailDispatcher` and `MailLog` — no new mail plumbing.
- One source of truth for the standard reply text: `App\Services\StandardReplyService::message()`. Email body, admin preview, and the WhatsApp `wa.me` deep link all use this exact string.
- Hard delete (project does not use SoftDeletes).
- No WhatsApp API (CLAUDE.md constraint) — `wa.me` deep links only.

## Feature 1 — Standard reply

### Message generation (`App\Services\StandardReplyService`)

Static-method service (house style, like `MailDispatcher`/`ActivityService`):

- `locale(CustomerRequest): string` — request locale whitelisted against `['nl','fr','en']`, else `'nl'`.
- `subject(CustomerRequest): string` — localized subject.
- `message(CustomerRequest): string` — localized multi-line plain-text message: greeting with customer name, intro with the service-category label in the request locale (from `config('request-flow.service_categories')`, nl fallback; unknown/missing category → category clause omitted entirely), body, questions line with `config('site.contact.phone_display')`, sign-off.
- `whatsappUrl(CustomerRequest): ?string` — normalizes the customer phone (logic moved out of `show.blade.php`), returns `https://wa.me/<number>?text=<rawurlencode(message())>` or `null`.

### Mailable + view

`App\Mail\StandardReplyMail` mirrors `CustomerRequestConfirmationMail`: envelope subject from the service, view `emails.customer.standard-reply` extending `emails.layout` with `emailLocale`. The body renders `message()` escaped via `{{ }}` inside a `white-space: pre-line` div. No raw user input is ever rendered as HTML.

### Idempotency

New nullable columns on `customer_requests`: `standard_reply_sent_at` (timestamp), `standard_reply_sent_by` (string email, matching the `customer_request_notes.author_email` precedent).

- **Send** (`POST admin/requests/{customerRequest}/standard-reply`): atomic claim — `UPDATE ... SET standard_reply_sent_at = now() WHERE id = ? AND standard_reply_sent_at IS NULL`. Zero affected rows → already sent → error flash, **no send**. After a claimed send: if `MailDispatcher::send()` returns `false`, the claim is rolled back (columns nulled) so the reply is not marked sent and a retry stays possible. Because `MailDispatcher` isolates `MailLog` write failures (returns `true` if the mail itself went out), a logging failure never triggers a second send.
- **Resend** (`POST admin/requests/{customerRequest}/standard-reply/resend`): requires `standard_reply_sent_at` already set, guarded by `Cache::lock` (15 s) against rapid duplicate POSTs, requires a `confirm()` dialog in the UI. On success, `standard_reply_sent_at`/`_by` are updated; on failure the original stamp is kept.
- **Client side**: submit buttons disable themselves in `onsubmit`.
- Flash messages are generic keys rendered by the view — technical mail errors are never exposed to the admin (matches the existing `quote_email_failed` pattern).

### UI (admin request detail page)

The "Snel bericht" block becomes "Standaardantwoord": preview of the exact generated message (copyable), the WhatsApp quick link reuses the same text, plus either a "Verstuur per e-mail" form (not yet sent) or a sent-timestamp + "Opnieuw versturen" form (already sent).

## Feature 2 — Safe request deletion

`DELETE admin/requests/{customerRequest}` (route name `admin.requests.destroy`), inside the existing `admin` middleware group (session auth enforced).

Order of operations in `RequestController::destroy()`:

1. Collect attachment paths **before** any deletion.
2. `DB::transaction`: re-check `quote()->exists()` inside the transaction (blocks races where a quote is created between page load and delete) → return blocked; otherwise hard-delete the request (DB cascades remove attachment/note/appointment rows; `mail_logs` are kept with `customer_request_id` nulled).
3. Only after the transaction commits, delete the files: each path must be a non-empty string, contain no `..`, and start with `customer-requests/` (the only directory uploads are stored in) — anything else is skipped and logged. `Storage::disk('public')->delete()` failures are logged and never abort.

A failed DB operation rolls back the whole transaction — no half-deleted request. File deletion failures leave orphan files (logged) but never corrupt data.

UI: destructive button in the quick-actions card with `confirm()` + self-disabling submit; when a quote exists the button is replaced by an explanatory note. Success flash on the index page (flash rendering added there — it has none today).

## Feature 3 — Admin account settings

New `Admin\AccountController` + `admin/account` routes (inside the `admin` middleware group) + `admin.account.edit` view, linked from the footer admin actions.

- Both email and password change require `current_password`, verified with `Hash::check()` (Laravel's `current_password` rule can't be used — admin auth is session-flag based, not guard based; CLAUDE.md mandates `AdminUser` + `Hash::check()`).
- Email change: validated unique (ignoring own row); on success the DB row is updated **and** `session('admin_user_email')` is set immediately so the active session keeps working.
- **Historical values are preserved**: `standard_reply_sent_by` and `customer_request_notes.author_email` keep the address that was current at the time. Nothing rewrites history.
- Password change: `min:12`, `confirmed`, stored via `Hash::make()`; session ID regenerated after the change.

### Documented future security improvements (out of scope now)

- **Invalidating other sessions**: admin auth stores no `user_id` in the `sessions` table (no guard), so other sessions of the same admin cannot be targeted safely today. Requires migrating admin auth to a proper guard (or recording session ownership) first.
- Note authorship (`author_email`) of existing notes no longer matches after an email change, so the admin loses edit/delete rights on their older notes. Accepted consequence of preserving history; a future `admin_user_id` FK would fix both.

## Feature 4 — VAT number

- `config/site.php`: new top-level key `'vat_number' => 'BE 0760.768.228'` (exact formatting).
- Footer (`layouts/app.blade.php`): rendered in the contact column with a localized label (BTW / TVA / VAT).
- Contact page (`pages/partials/contact-page.blade.php`): new contact item with localized label (BTW-nummer / Numéro de TVA / VAT number).
- The existing `site.contact.company_number` (env-driven, currently empty) stays untouched.

## Test matrix (required by Xander → covering test)

| Requirement | Test |
|---|---|
| NL/FR/EN reply generation | `tests/Unit/StandardReplyServiceTest` |
| Unknown locale falls back to NL | `tests/Unit/StandardReplyServiceTest` |
| Unknown category falls back safely | `tests/Unit/StandardReplyServiceTest` |
| WhatsApp text identical to email text | `tests/Unit/StandardReplyServiceTest` |
| Localized subject + escaped rendering | `tests/Unit/StandardReplyMailTest` |
| Standard reply sends exactly once | `tests/Feature/Admin/StandardReplyTest` |
| Resend requires explicit action | `tests/Feature/Admin/StandardReplyTest` |
| Successful send → `contacted` + `contacted_at` | `tests/Feature/Admin/StandardReplyTest` |
| Failed mail does not mark as sent | `tests/Feature/Admin/StandardReplyTest` |
| Mail-log failure does not double-send | `tests/Feature/Admin/StandardReplyTest` |
| Request without quote can be deleted | `tests/Feature/Admin/RequestDeleteTest` |
| Request with quote cannot be deleted | `tests/Feature/Admin/RequestDeleteTest` |
| Attachments removed on successful delete | `tests/Feature/Admin/RequestDeleteTest` |
| Unsafe attachment paths skipped | `tests/Feature/Admin/RequestDeleteTest` |
| Email change updates session | `tests/Feature/Admin/AccountSettingsTest` |
| Wrong current password rejected | `tests/Feature/Admin/AccountSettingsTest` |
| Password hashed after change | `tests/Feature/Admin/AccountSettingsTest` |
| `standard_reply_sent_by` preserved after email change | `tests/Feature/Admin/AccountSettingsTest` |
| Unauthenticated users blocked on all new routes | all four feature test files |
| VAT number in footer + contact page | `tests/Feature/VatNumberTest` |
