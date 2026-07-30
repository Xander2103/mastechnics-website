# Admin Sprint: Deletion Modal, Blocklist, Multi-Admin, Admin Nav, Attachments, Quote Locale

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Give Martin overview-level request deletion with an accessible modal, a contact-form email blocklist, a second secure admin account for Xander, a dedicated admin navigation bar, safe attachment viewing, and quote emails localized to the customer's request language.

**Architecture:** All work extends the existing session-based admin (`session('admin_user_email')` + `admin` middleware), the `MailDispatcher`/`MailLog` mail infra, and the `StandardReplyService` localization pattern (service class = single source of truth, blade/mailable consume). Admin pages keep extending `layouts.app`; the layout swaps the public header for a new admin header partial on `admin.*` routes with an active admin session.

**Tech Stack:** Laravel 12, vanilla JS (IIFE modules in `resources/js/app.js`), hand-written CSS (`resources/css/pages/admin.css`), sqlite in-memory tests, Vite.

## Global Constraints

- Admin UI language: Dutch. Customer-facing messages: nl/fr/en with nl fallback.
- Never store plain-text passwords; never commit Xander's real password anywhere.
- Do not break: CustomerRequest storage, uploads, request flow form, admin dashboard, existing mail system, existing routes.
- All admin routes behind `admin` middleware + CSRF. No browser `confirm()` for the new flows.
- Existing IP rate limits on contact form (10/day, 20/hour) stay active. NOTE: spec's "2 accepted submissions per rolling 24h" does not exist in the codebase — preserved as-is, reported.
- Do not push, do not deploy. Logical commit per task. Exclude unrelated dirty file `resources/css/pages/home.css` from sprint commits.
- Not installed (do not invoke): /laravel-architect, /security-audit, /ui-ux-pro-max, /page-cro, /form-cro, /pricing-strategy.

---

### Task A — Overview delete + reusable admin confirm modal

**Files:**
- Modify: `resources/views/admin/requests/index.blade.php` (delete button column + one shared modal + per-row form)
- Modify: `resources/views/admin/requests/show.blade.php` (replace native confirm() on delete with same modal)
- Modify: `app/Http/Controllers/Admin/RequestController.php:493-495` (redirect preserving whitelisted filters)
- Modify: `resources/js/app.js` (add `initAdminConfirmModal()`)
- Modify: `resources/css/pages/admin.css` (modal + red icon button styles)
- Test: `tests/Feature/Admin/RequestDeleteTest.php`

**Interfaces (produces, reused by Task B):**
- JS: any `<button type="button" data-confirm-modal data-confirm-title="…" data-confirm-body="…" data-confirm-label="…" data-confirm-form="#form-id">` opens the shared modal `#admin-confirm-modal`; confirm submits the referenced form. Modal: `role="dialog" aria-modal="true" aria-labelledby="admin-confirm-title" aria-describedby="admin-confirm-body"`, focus trap, Escape + backdrop close, focus restore, `body` scroll lock via existing `reviews-modal-open` class pattern (new `admin-modal-open`), `prefers-reduced-motion` respected (CSS only transitions).
- Controller: `destroy()` redirects to `route('admin.requests.index', $filters)` where `$filters = $request->only(['search','status','service_slug','service_category','request_type','urgency','customer_type','has_quote','date_from','date_to'])` submitted as hidden inputs from the index form.

**Steps:**
- [ ] Tests: overview renders red delete button with aria-label "Aanvraag verwijderen" + modal markup attrs; delete from overview with filters redirects back with query preserved; quote-guard still blocks (existing); unauth blocked (existing).
- [ ] Modal markup: one `<div id="admin-confirm-modal">` per page bottom; body text injected from `data-confirm-body` (textContent, no HTML). Body copy: `Weet u zeker dat u de aanvraag van {customer_name} wilt verwijderen? Deze actie kan niet ongedaan worden gemaakt.` Buttons: `Annuleren` / `Definitief verwijderen` (red, `admin-button-danger`).
- [ ] Per-row hidden DELETE form with `@csrf @method('DELETE')` + hidden filter inputs.
- [ ] Detail page: swap `onsubmit confirm(...)` for the modal (keep quote-blocked else-branch untouched).
- [ ] `npm run build`, run tests, commit `feat(admin): add request deletion modal to overview`.

---

### Task B — Email blocklist (contact form only)

**Files:**
- Create: `database/migrations/2026_07_30_100000_create_blocked_emails_table.php`
- Create: `app/Models/BlockedEmail.php`
- Create: `app/Http/Controllers/Admin/BlockedEmailController.php`
- Create: `resources/views/admin/blocked-emails/index.blade.php`
- Modify: `routes/web.php` (admin group), `app/Http/Controllers/ContactController.php`, `resources/views/pages/partials/contact-page.blade.php` (blocked message rendering)
- Modify: `resources/css/pages/admin.css`
- Test: `tests/Feature/Admin/BlockedEmailManagementTest.php`, `tests/Feature/ContactBlocklistTest.php`

**Schema `blocked_emails`:** `id`, `email` string unique (stored trimmed+lowercased), `reason` string(500) nullable, `blocked_by` string (admin email), `is_active` bool default true, `expires_at` timestamp nullable, timestamps.

**Interfaces:**
- `BlockedEmail::isBlocked(string $email): bool` — normalize (trim+mb_strtolower), match `is_active=1` AND (`expires_at` null OR future). Swallows QueryException (missing table ⇒ not blocked) to mirror contact-form resilience pattern.
- `BlockedEmail::normalizeEmail(string $email): string`
- Routes: `GET admin/blocked-emails` (`admin.blocked-emails.index`), `POST admin/blocked-emails` (`admin.blocked-emails.store`, throttle:10,1), `PATCH admin/blocked-emails/{blockedEmail}/unblock` (`admin.blocked-emails.unblock`), `PATCH admin/blocked-emails/{blockedEmail}/reactivate` optional — NO: keep minimal, re-block via store (updateOrCreate).
- Store validation: `email` required|email|max:255; `reason` nullable|string|max:500; `duration` required|in:permanent,24h,7d,30d → expires_at null/now+24h/+7d/+30d. `blocked_by = session('admin_user_email')`.
- Contact flow: after `$request->validate(...)` in `ContactController@store`, before idempotency claim/rate-limit hit: if `BlockedEmail::isBlocked($validated['email'])` → `back()->withErrors(['blocked' => self::blockedMessage($locale)])->withInput()`; no mail, no ContactSubmission row, no RateLimiter::hit. Neutral messages (exact):
  - nl: `Uw bericht kon niet worden verwerkt. Neem bij een dringende vraag telefonisch contact met ons op.`
  - fr: `Votre message n'a pas pu être traité. Pour une demande urgente, contactez-nous par téléphone.`
  - en: `Your message could not be processed. For urgent enquiries, please contact us by phone.`
- Admin page shows full emails (masking skipped deliberately — admins must manage exact addresses; documented in report), date blocked, reason, blocked_by, state badge (Actief/Inactief/Verlopen), expiry, unblock button behind confirm modal (Task A component), add-block form behind confirm modal.
- Smart request form untouched.

**Steps:** tests first (blocked email sends no mail + neutral message per locale; inactive/expired do not reject; case-insensitive; admin add/unblock; auth required; request wizard unaffected) → migration/model → controller/routes/view → contact hook → build, test, commit `feat(contact): add email blocklist management`.

---

### Task C — Secure multiple admin accounts

**Files:**
- Create: `app/Console/Commands/CreateAdminUser.php` (signature `admin:create {--update : Update the password of an existing admin}`)
- Modify: `app/Http/Controllers/Admin/AccountController.php` (password rule → `Password::min(12)->letters()->numbers()`)
- Modify: `resources/views/admin/account/edit.blade.php` (helper text + read-only "Beheerders" card: name, email, created date; no delete, no roles)
- Test: `tests/Feature/Admin/CreateAdminCommandTest.php`, extend `tests/Feature/Admin/AccountSettingsTest.php`

**Command behavior:** `ask('E-mailadres')` → validate `email:filter`; `secret('Wachtwoord')` + `secret('Bevestig wachtwoord')` → must match, min 12 chars, ≥1 letter, ≥1 digit; duplicate email without `--update` ⇒ error exit 1; `confirm()` before write; `Hash::make`; never echoes password; name derived via `ask('Naam', default ucfirst(local part))`. Output only the email, never the password.

**Steps:** tests (artisan test double with `expectsQuestion`) → command → password-rule change + account card → run, commit `feat(admin): support secure multiple admin accounts`.

---

### Task D — Dedicated admin navigation + dashboard header cleanup

**Files:**
- Create: `resources/views/partials/admin-header.blade.php`
- Modify: `resources/views/layouts/app.blade.php` (`@if ($isAdminContext) include admin header @else public header @endif`; same for footer admin block; `$isAdminContext = request()->routeIs('admin.*') && session()->has('admin_user_email')`)
- Modify: `resources/js/app.js` (`initAdminNav()` mobile toggle: aria-expanded, aria-controls, Escape closes + focus restore)
- Modify: `resources/css/pages/admin.css` (admin header bar, badge, active state, focus-visible, mobile menu)
- Test: `tests/Feature/Admin/AdminNavigationTest.php`, adjust `HomepageTest` footer expectations if needed

**Nav structure:** `<header class="admin-header"><nav aria-label="Adminnavigatie">` — logo (same brand mark as public header, links to `admin.requests.index`), `<span class="admin-header-badge">Admin</span>`, links: Aanvragen (`admin.requests.*` active), Account (`admin.account.*`), Geblokkeerde e-mails (`admin.blocked-emails.*`), logout as POST form button. Active link gets class `is-active` + `aria-current="page"`. No Start aanvraag CTA, no language switcher, no public marketing links in admin context.

**Footer:** admin block reduced — public pages, admin session: only "Admin panel" link; logged out: "Admin" login link (unchanged); admin context: no admin links (nav has them). Account/Uitloggen removed from footer everywhere.

**Steps:** tests (admin sees nav; logged-out visitor on public page sees no admin nav; aria-current present; footer lacks Account/Uitloggen; logout POST works; blocked-emails link resolves) → partial + layout switch + JS + CSS → build, test, commit `feat(admin): add dedicated admin navigation`.

---

### Task E — Account spam section + attachment visibility/security

**Files:**
- Modify: `resources/views/admin/account/edit.blade.php` ("Spam en blokkeringen" card: count of active blocks, link to `admin.blocked-emails.index`, helper: `Beheer e-mailadressen die het contactformulier niet meer mogen gebruiken.`)
- Modify: `app/Http/Controllers/Admin/AccountController.php@edit` (pass `$activeBlockCount`, `$admins`)
- Create route + action: `GET admin/requests/{customerRequest}/attachments/{attachment}` → `admin.requests.attachments.download`, `RequestController@downloadAttachment` — 404 unless `$attachment->customer_request_id === $customerRequest->id`; images stream inline, PDFs as download; 404 → detail page shows `Bestand niet meer beschikbaar.` when `Storage::disk('public')->exists()` is false.
- Modify: `resources/views/admin/requests/show.blade.php` Bijlagen section — cards: original name, type label, human-readable size, upload date, image thumbnail via download route, "Openen"/"Downloaden" buttons; zero `asset('storage/...')` references; mobile stacking CSS.
- Test: `tests/Feature/Admin/AttachmentDownloadTest.php`, extend `RequestDetailTest`.

**Steps:** tests (auth required; cross-request attachment 404; response headers; missing file shows message; no `storage/` path in HTML) → route+controller → view+CSS → build, test, commit `feat(admin): expose blocklist and attachments clearly`.

---

### Task F — Localize quote emails + send guards

**Files:**
- Create: `app/Services/QuoteEmailTextService.php`
- Modify: `app/Http/Controllers/Admin/QuoteController.php@sendEmail` (mark-sent only on success; `Cache::lock('quote-email-send-{id}', 15)`; reject when `quote_status !== 'draft'` with flash `quote_email_already_sent`)
- Modify: `app/Mail/QuoteSentMail.php` (unchanged API; subject still from validated input which now defaults from service)
- Modify: `resources/views/admin/requests/show.blade.php` (prefill from service, drop inline `$quoteEmailDefaults`; add submit-disable on send form)
- Modify: `resources/views/emails/customer/quote-sent.blade.php` (labels from service, drop inline array)
- Test: `tests/Unit/QuoteEmailTextServiceTest.php`, extend `tests/Feature/Admin/QuoteTest.php`

**Service API (single source of truth):**
```php
QuoteEmailTextService::locale(CustomerRequest $cr): string        // whitelist nl|fr|en, fallback nl
QuoteEmailTextService::subject(CustomerRequest $cr): string       // nl: Uw offerte van Mastechnics / fr: Votre devis de Mastechnics / en: Your quotation from Mastechnics
QuoteEmailTextService::body(CustomerRequest $cr): string          // greeting (Beste|Bonjour|Dear {name},) + body (Zoals besproken vindt u in bijlage onze offerte. | Comme convenu, vous trouverez notre devis en pièce jointe. | As discussed, please find our quotation attached.) + sign-off
QuoteEmailTextService::labels(CustomerRequest $cr): array         // email chrome: heading, intro, attachment note, CTA, closing
```
- PDF (`admin/quotes/pdf.blade.php`) stays hardcoded Dutch — architecture does not support safe localization (557-line hand-built template, no translation layer); explicitly reported.
- Behavior change documented in test updates: failed mail no longer advances `quote_status`/`quote_sent_at` (spec override of previous deliberate behavior).

**Steps:** service unit tests (per-locale subject/body, unknown→nl) → service → controller guards (lock + draft check + success-conditional mark) → view/mailable wiring → feature tests (NL/FR/EN prefill + sent subject; admin app-locale irrelevant; failure not marked; duplicate POST sends once; PDF still attached) → build, test, commit `fix(mail): localize quote emails by request language`.

---

### Task G — Final verification

- [ ] `php artisan migrate` (dev DB), `php artisan test`, `npm run build`, `php -l` each changed PHP file.
- [ ] Final report: routes added, delete/quote-guard behavior, blocklist behavior, secure account creation ("Xander's admin account can be created securely using php artisan admin:create" — no password anywhere), locale resolution + fallback, PDF language note, residual risks (public-disk storage URLs, no pagination on index, "2/24h rule" never existed), deployment commands. Do not push.

## Self-review notes
- Spec coverage: tasks 1→A, 2→B, 3/4→C, 8/12→D, 9/10→E, 11→F, 5/13→G+per-task tests. Admin-users management screen delivered minimally as read-only card (spec: "only if small and safe").
- Type consistency: modal data-attribute contract defined once in Task A and reused verbatim in Task B/E views.
