# Live Polish Sprint (Wizard UX, Favicon, Hero, Footer, SEO) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ship the post-launch polish sprint for mastechnics.be — wizard top navigation, a real file-upload correctness bug found during audit, homepage hero viewport fix, footer credit alignment, and SEO content/technical fixes — without touching the request form's non-negotiable storage/mail/upload pipeline.

**Architecture:** Laravel 12 + Blade + vanilla JS (no frontend framework). The request wizard's real logic lives in an inline `<script>` block at the bottom of `resources/views/pages/partials/request-page.blade.php` — NOT in `resources/js/request-form.js`, which turned out to be dead/duplicate code from an earlier iteration that still loads via `resources/js/app.js` and fights the inline script for the same DOM elements. SEO copy is DB-seeded via `database/seeders/PageSeeder.php`; a second, unused seeder (`database/seeders/PageContentSeeder.php`) already contains materially better copy for every service page that was written in an earlier sprint but never wired into `DatabaseSeeder`.

**Tech Stack:** Laravel 12, Blade, vanilla JS, Vite, PHPUnit (`php artisan test`), dompdf for quote PDFs.

## Audit Summary (read this before starting)

Before writing this plan, the current code was read end-to-end. Several of the 10 requested items are **already fixed** by the six unpushed commits on `main` (`9d6c667`..`87a7426`). Re-doing them would be wasted, conflicting work. Status per requested item:

| # | Item | Status |
|---|------|--------|
| 1 | Top wizard nav buttons | **Not done** — only a top *submit* button exists on the summary step. Back/Next are bottom-only on every other step. → Task 1 |
| 2 | Service card auto-advance | **Already implemented** (`request-page.blade.php` `onCategoryChange()`) and works. But `resources/js/request-form.js` still attaches a second, dead click listener to the same `.option-card` elements (targets markup that no longer exists) — latent reliability risk. → Task 2 |
| 3 | Favicon files + `<head>` links | **Already correct.** All files exist in `public/`, all tags present in `layouts/app.blade.php:107-111`. Only a regression test is missing. → folded into Task 4 |
| 4 | Hero fills viewport | **Not done.** `.home-hero` uses fixed `padding: 80px 0 88px` with no `min-height`, so on tall desktop screens the "Diensten" section starts wherever content happens to end. → Task 5 |
| 5 | Footer credit right-aligned | **Not done.** Markup/CSS already have the pulsing gold link (`footer-credit-link`) and correct URL, but `.footer-bottom` has no flex layout — the credit link just sits inline in the same paragraph as the copyright text, left-aligned, no way to push it right. → Task 6 |
| 6 | SEO service page content | **Partially done.** `PageSeeder.php` currently generates one generic templated sentence per service (`"Wij helpen met aanvragen rond {title}..."`, identical shape for all 6 services) plus a weak `"{title} \| mastechnics"` meta title. `PageContentSeeder.php` — never called by `DatabaseSeeder` — already contains unique, differentiated, professional copy (content + meta_title + meta_description) for all 6 services in nl/fr/en. → Task 7 |
| 7 | SEO technical (robots/sitemap/canonical/OG) | **Mostly correct**, two real gaps: `robots.txt` has the `Sitemap:` line commented out even though the site is live, and there is no `og:image` tag at all. Canonical/OG URL correctness depends on `APP_URL` in the production `.env`, which is outside this repo. → Task 8 |
| 8 | Contact "Bericht voorbereiden" button | **Already fixed** (commit `a3ac4a8`). Verified by reading `contact-page.blade.php` — validates then builds a `mailto:` link. Missing: regression test. → Task 9 |
| 9 | Quote item save | **Already fixed** (commit `9539017`). Verified by reading `Admin\QuoteController::store()` — deletes and recreates items transactionally per request. Missing: regression test. → Task 10 |
| — | **New finding, not in the original list**: attachment "remove ×" button bug | The inline wizard script's file-picker preview (both the early tech-step upload and the description-step upload) never rebuilds the real `<input type="file">` FileList after add/remove. Consequence: (a) picking files in two separate "Choose files" dialogs silently drops the first batch on submit (the browser replaces `.files` on every picker invocation), and (b) clicking "×" to remove a file from the preview list does **not** remove it from what actually gets uploaded. This directly touches the non-negotiable file-upload constraint. → Task 3 |

Tasks are ordered so each is independently testable and commit-able. Tasks 2, 3, 9, 10 are mostly regression-test-and-verify since the underlying behavior already works (or, for Task 3, needs a small targeted fix). Tasks 1, 5, 6, 7, 8 involve real code/content changes.

## Global Constraints

- Windows/PowerShell only — no `&&`, no `$VAR`, no `/dev/null` (see CLAUDE.md).
- Never break: `CustomerRequest` storage, file uploads (`attachments[]` / `CustomerRequestAttachment`), the request flow routes (`/nl/aanvraag`, `/fr/demande`, `/en/request`), `/admin/requests`, `NewCustomerRequestMail` / `CustomerRequestConfirmationMail`, all routes in `routes/web.php`.
- Every user-facing string needs nl/fr/en translations (nl is the fallback).
- Client-side JS is UX only — never the source of truth for validation; server-side validation in controllers must remain authoritative.
- No OpenAI/Claude/WhatsApp-API/Google-Calendar integration work — out of scope, `ai_summary` / `ai_detected_missing_fields` stay `null`.
- Do not deploy. Do not run destructive git commands. Work in logical, separate commits per task.
- Mobile-first: every visual change (Tasks 1, 5, 6) must be checked at a mobile width, not just desktop.

---

## Task 1: Wizard top navigation buttons

**Files:**
- Modify: `resources/views/pages/partials/request-page.blade.php:340-347` (markup — insert new top nav bar), `:803-808` (JS DOM refs), `:871-887` (JS `showStep()`), `:941-959` (JS click handlers)
- Modify: `resources/css/pages/request.css:138-158` (add a spacing modifier for the top bar; the existing `.wizard-nav-bar` / `.wizard-nav-forward` / `.is-wizard-hidden` rules are reused as-is)
- Test: `tests/Feature/RequestWizardTest.php` (new file)

**Interfaces:**
- Consumes: existing `showStep(sections, index)`, `getSelectedCategory()`, `validateCurrentStep()`, `visibleSections`, `currentIndex` from the inline script.
- Produces: two new functions `goToNextStep()` and `goToPreviousStep()` that both button pairs call, plus new element IDs `wizardTerugTop` / `wizardVerderTop` that later steps in this plan do not depend on.

- [ ] **Step 1: Add the top nav bar markup**

In `resources/views/pages/partials/request-page.blade.php`, insert a new block right after the closing `</div>` of `#wizardProgressArea` (line 347) and before `<div class="request-form-card" id="requestFormCard">` (line 349):

```blade
                        <div class="wizard-nav-bar wizard-nav-bar--top" id="wizardNavBarTop">
                            <button type="button" id="wizardTerugTop" class="button button-ghost wizard-nav-back is-wizard-hidden">
                                &larr; {{ $text['terug'] }}
                            </button>

                            <div class="wizard-nav-forward">
                                <button type="button" id="wizardVerderTop" class="button button-primary">
                                    {{ $text['verder'] }} &rarr;
                                </button>
                            </div>
                        </div>
```

Note: `#wizardProgressArea` has `aria-hidden="true"` — do NOT nest the new buttons inside it, or they become inaccessible to screen readers while still visually clickable. The new bar is a sibling, not a child.

- [ ] **Step 2: Add JS DOM refs for the new buttons**

In the inline `<script>` block, immediately after the existing refs (around line 806, right after `var wizardSubmitTop = document.getElementById('wizardSubmitTop');`):

```js
    var wizardTerugTop  = document.getElementById('wizardTerugTop');
    var wizardVerderTop = document.getElementById('wizardVerderTop');
```

- [ ] **Step 3: Extract shared navigation logic into named functions**

Replace the existing click handlers (lines 941-959):

```js
    // ── Nav buttons ───────────────────────────────────────────────────────────
    wizardVerder.addEventListener('click', function () {
        if (currentIndex === 0) {
            if (currentIndex < visibleSections.length - 1) {
                showStep(visibleSections, currentIndex + 1);
            }
            return;
        }
        if (!validateCurrentStep()) return;
        if (currentIndex < visibleSections.length - 1) {
            showStep(visibleSections, currentIndex + 1);
        }
    });

    wizardTerug.addEventListener('click', function () {
        if (currentIndex > 0) {
            showStep(visibleSections, currentIndex - 1);
        }
    });
```

with:

```js
    // ── Nav buttons ───────────────────────────────────────────────────────────
    function goToNextStep() {
        if (currentIndex === 0) {
            if (currentIndex < visibleSections.length - 1) {
                showStep(visibleSections, currentIndex + 1);
            }
            return;
        }
        if (!validateCurrentStep()) return;
        if (currentIndex < visibleSections.length - 1) {
            showStep(visibleSections, currentIndex + 1);
        }
    }

    function goToPreviousStep() {
        if (currentIndex > 0) {
            showStep(visibleSections, currentIndex - 1);
        }
    }

    wizardVerder.addEventListener('click', goToNextStep);
    wizardTerug.addEventListener('click', goToPreviousStep);
    if (wizardVerderTop) { wizardVerderTop.addEventListener('click', goToNextStep); }
    if (wizardTerugTop)  { wizardTerugTop.addEventListener('click', goToPreviousStep); }
```

- [ ] **Step 4: Mirror button state in `showStep()`**

In `showStep()`, the existing block (lines 871-887):

```js
        // Navigation buttons
        var isFirst = index === 0;
        var isLast  = index === sections.length - 1;

        wizardTerug.classList.toggle('is-wizard-hidden', isFirst);
        wizardVerder.classList.toggle('is-wizard-hidden', isLast);
        wizardSubmit.classList.toggle('is-wizard-hidden', !isLast);

        // Disable/enable Verder (only locked on step 0 until category chosen)
        if (isFirst) {
            var hasCat = !!getSelectedCategory();
            wizardVerder.disabled = !hasCat;
            wizardVerder.setAttribute('aria-disabled', String(!hasCat));
        } else {
            wizardVerder.disabled = false;
            wizardVerder.removeAttribute('aria-disabled');
        }
```

becomes:

```js
        // Navigation buttons
        var isFirst = index === 0;
        var isLast  = index === sections.length - 1;

        wizardTerug.classList.toggle('is-wizard-hidden', isFirst);
        wizardVerder.classList.toggle('is-wizard-hidden', isLast);
        wizardSubmit.classList.toggle('is-wizard-hidden', !isLast);
        if (wizardTerugTop)  { wizardTerugTop.classList.toggle('is-wizard-hidden', isFirst); }
        if (wizardVerderTop) { wizardVerderTop.classList.toggle('is-wizard-hidden', isLast); }

        // Disable/enable Verder (only locked on step 0 until category chosen)
        if (isFirst) {
            var hasCat = !!getSelectedCategory();
            wizardVerder.disabled = !hasCat;
            wizardVerder.setAttribute('aria-disabled', String(!hasCat));
            if (wizardVerderTop) {
                wizardVerderTop.disabled = !hasCat;
                wizardVerderTop.setAttribute('aria-disabled', String(!hasCat));
            }
        } else {
            wizardVerder.disabled = false;
            wizardVerder.removeAttribute('aria-disabled');
            if (wizardVerderTop) {
                wizardVerderTop.disabled = false;
                wizardVerderTop.removeAttribute('aria-disabled');
            }
        }
```

The summary step keeps its existing dedicated `wizardSubmitTop` button (already at the top of the summary section content) as the top-of-step call to action — do not add a second submit button to `#wizardNavBarTop`, since `isLast` already hides `wizardVerderTop` there and `wizardSubmitTop` already fills that role.

- [ ] **Step 5: Add a spacing modifier in CSS**

In `resources/css/pages/request.css`, after the existing `.wizard-nav-bar` rule (around line 144), add:

```css
.wizard-nav-bar--top {
    margin-top: 0;
    margin-bottom: 24px;
}
```

- [ ] **Step 6: Write the feature test**

Create `tests/Feature/RequestWizardTest.php`:

```php
<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RequestWizardTest extends TestCase
{
    use RefreshDatabase;

    public function test_wizard_renders_top_and_bottom_navigation_buttons(): void
    {
        $this->seed(\Database\Seeders\PageSeeder::class);

        $response = $this->get('/nl/aanvraag');

        $response->assertOk();
        $response->assertSee('id="wizardNavBarTop"', false);
        $response->assertSee('id="wizardTerugTop"', false);
        $response->assertSee('id="wizardVerderTop"', false);
        $response->assertSee('id="wizardTerug"', false);
        $response->assertSee('id="wizardVerder"', false);
    }

    public function test_wizard_progress_area_is_not_wrapping_the_top_nav_buttons(): void
    {
        $this->seed(\Database\Seeders\PageSeeder::class);

        $response = $this->get('/nl/aanvraag');
        $html = $response->getContent();

        $progressAreaEnd = strpos($html, 'id="wizardProgressArea"');
        $navBarStart     = strpos($html, 'id="wizardNavBarTop"');
        $formCardStart   = strpos($html, 'id="requestFormCard"');

        $this->assertNotFalse($progressAreaEnd);
        $this->assertNotFalse($navBarStart);
        $this->assertNotFalse($formCardStart);
        $this->assertTrue($navBarStart > $progressAreaEnd && $navBarStart < $formCardStart);
    }
}
```

- [ ] **Step 7: Run the test**

Run: `php artisan test --filter=RequestWizardTest`
Expected: PASS (2 tests)

- [ ] **Step 8: Commit**

```bash
git add resources/views/pages/partials/request-page.blade.php resources/css/pages/request.css tests/Feature/RequestWizardTest.php
git commit -m "fix(request): add top wizard navigation buttons matching bottom controls"
```

---

## Task 2: Remove dead/duplicate wizard JS and add an auto-advance regression test

**Files:**
- Modify: `resources/js/request-form.js`
- Test: `tests/Feature/RequestWizardTest.php` (extend)

**Interfaces:**
- Consumes: nothing new.
- Produces: `resources/js/request-form.js` exports no wizard-step behavior anymore (the inline script in `request-page.blade.php` is the single source of truth for wizard interaction).

**Why this is safe:** `initSelectableCards()`, `initStepNavigation()`, and `initManualStepTracking()` in `request-form.js` target `.request-step` elements that do not exist anywhere in the current Blade output (`grep` confirms zero matches), so they are no-ops. `initSelectableCards()` also attaches a second click listener to `.option-card` — the same elements the inline script in `request-page.blade.php` already handles — which is a real duplicate-listener risk (two independent handlers reacting to the same click, relying on script-load-order to not visibly conflict). `initAttachmentPreview()` duplicates the inline script's `#attachmentsInput` / `#selectedAttachments` handling with a competing implementation. None of this is needed; the inline script is complete and correct on its own.

- [ ] **Step 1: Confirm no other file imports the functions being removed**

Run: `grep -rn "initSelectableCards\|initStepNavigation\|initManualStepTracking\|initAttachmentPreview\|renderSelectedFiles\|syncFileInput" resources/js resources/views`

Expected: only matches inside `resources/js/request-form.js` itself.

- [ ] **Step 2: Simplify `request-form.js` to remove the dead/duplicate logic**

Replace the entire contents of `resources/js/request-form.js` with:

```js
function initRequestForm() {
    const requestPage = document.querySelector('.request-form-card');

    if (!requestPage) {
        return;
    }

    // Step navigation, card selection, auto-advance and attachment previews
    // are handled by the inline wizard script in
    // resources/views/pages/partials/request-page.blade.php, which owns the
    // step state machine. This file intentionally does no wizard-specific
    // DOM wiring to avoid double-binding the same elements.
}

document.addEventListener('DOMContentLoaded', initRequestForm);
```

- [ ] **Step 3: Add an auto-advance regression test**

Extend `tests/Feature/RequestWizardTest.php` with:

```php
    public function test_auto_advance_script_wires_option_card_clicks_to_category_change(): void
    {
        $this->seed(\Database\Seeders\PageSeeder::class);

        $response = $this->get('/nl/aanvraag');
        $html = $response->getContent();

        // The inline wizard script must be the one driving card clicks:
        // clicking an .option-card checks its radio then calls onCategoryChange(),
        // which auto-advances from step 0 to step 1 once a category is selected.
        $this->assertStringContainsString("card.addEventListener('click', function () {", $html);
        $this->assertStringContainsString('onCategoryChange();', $html);
        $this->assertStringContainsString('showStep(visibleSections, 1);', $html);
    }
```

- [ ] **Step 4: Run the tests**

Run: `php artisan test --filter=RequestWizardTest`
Expected: PASS (3 tests)

- [ ] **Step 5: Manual verification (JS behavior — no JS test runner in this repo)**

Run `npm run build`, then `php artisan serve`, open `http://127.0.0.1:8000/nl/aanvraag` in a browser, and confirm:
- Clicking a service card selects it and auto-advances to step 2 exactly once (not double-rendered/flickering).
- Browser devtools console has no errors on page load or on card click.

- [ ] **Step 6: Commit**

```bash
git add resources/js/request-form.js tests/Feature/RequestWizardTest.php
git commit -m "fix(request): remove dead duplicate wizard listeners from request-form.js"
```

---

## Task 3: Fix attachment "remove" button not syncing the real file input

**Files:**
- Modify: `resources/views/pages/partials/request-page.blade.php:1002-1083` (inline script — both attachment preview blocks)

**Interfaces:**
- Consumes: nothing new.
- Produces: nothing consumed by later tasks — this is a self-contained correctness fix.

**Why:** Neither the description-step (`#attachmentsInput` / `#selectedAttachments`) nor the tech-step (`#techAttachmentsInput` / `#techSelectedAttachments`) preview writes back to the real `<input type="file">` after add or remove. Two concrete failure modes: (1) picking files across two separate "Choose files" dialogs — browsers replace `input.files` on every picker invocation, so only the most recent batch is actually submitted even though the preview list shows all of them; (2) clicking "×" removes a file from the visual list but not from `input.files`, so it still uploads. This touches the non-negotiable file-upload constraint, so it needs a real fix, not just a test.

- [ ] **Step 1: Add a `DataTransfer`-based sync helper and call it from both attachment blocks**

Replace the description-step block (lines 1002-1042):

```js
    // ── File attachment preview (description step upload) ─────────────────────
    // SECURITY: file.name is rendered via textContent only — never innerHTML
    if (attachInput && attachList) {
        var files = [];

        attachInput.addEventListener('change', function () {
            Array.from(attachInput.files).forEach(function (file) {
                if (!files.find(function (f) { return f.name === file.name && f.size === file.size; })) {
                    files.push(file);
                }
            });
            renderAttachments();
        });

        function renderAttachments() {
            attachList.innerHTML = '';
            files.forEach(function (file, i) {
                var item = document.createElement('div');
                item.className = 'selected-attachment-item';

                var nameSpan = document.createElement('span');
                nameSpan.textContent = file.name;

                var removeBtn = document.createElement('button');
                removeBtn.type = 'button';
                removeBtn.setAttribute('aria-label', attachList.dataset.removeLabel || 'Verwijder');
                removeBtn.textContent = '×';

                removeBtn.addEventListener('click', (function (index) {
                    return function () {
                        files.splice(index, 1);
                        renderAttachments();
                    };
                })(i));

                item.appendChild(nameSpan);
                item.appendChild(removeBtn);
                attachList.appendChild(item);
            });
        }
    }
```

with:

```js
    // ── File attachment preview (description step upload) ─────────────────────
    // SECURITY: file.name is rendered via textContent only — never innerHTML
    if (attachInput && attachList) {
        var files = [];

        function syncAttachInputFiles() {
            var dt = new DataTransfer();
            files.forEach(function (file) { dt.items.add(file); });
            attachInput.files = dt.files;
        }

        attachInput.addEventListener('change', function () {
            Array.from(attachInput.files).forEach(function (file) {
                if (!files.find(function (f) { return f.name === file.name && f.size === file.size; })) {
                    files.push(file);
                }
            });
            syncAttachInputFiles();
            renderAttachments();
        });

        function renderAttachments() {
            attachList.innerHTML = '';
            files.forEach(function (file, i) {
                var item = document.createElement('div');
                item.className = 'selected-attachment-item';

                var nameSpan = document.createElement('span');
                nameSpan.textContent = file.name;

                var removeBtn = document.createElement('button');
                removeBtn.type = 'button';
                removeBtn.setAttribute('aria-label', attachList.dataset.removeLabel || 'Verwijder');
                removeBtn.textContent = '×';

                removeBtn.addEventListener('click', (function (index) {
                    return function () {
                        files.splice(index, 1);
                        syncAttachInputFiles();
                        renderAttachments();
                    };
                })(i));

                item.appendChild(nameSpan);
                item.appendChild(removeBtn);
                attachList.appendChild(item);
            });
        }
    }
```

- [ ] **Step 2: Apply the identical fix to the tech-step block**

Replace the tech-step block (lines 1044-1083):

```js
    // ── Technical photo upload preview ─────────────────────────────────────────
    if (techAttachInput && techAttachList) {
        var techFiles = [];

        techAttachInput.addEventListener('change', function () {
            Array.from(techAttachInput.files).forEach(function (file) {
                if (!techFiles.find(function (f) { return f.name === file.name && f.size === file.size; })) {
                    techFiles.push(file);
                }
            });
            renderTechAttachments();
        });

        function renderTechAttachments() {
            techAttachList.innerHTML = '';
            techFiles.forEach(function (file, i) {
                var item = document.createElement('div');
                item.className = 'selected-attachment-item';

                var nameSpan = document.createElement('span');
                nameSpan.textContent = file.name;

                var removeBtn = document.createElement('button');
                removeBtn.type = 'button';
                removeBtn.setAttribute('aria-label', techAttachList.dataset.removeLabel || 'Verwijder');
                removeBtn.textContent = '×';

                removeBtn.addEventListener('click', (function (index) {
                    return function () {
                        techFiles.splice(index, 1);
                        renderTechAttachments();
                    };
                })(i));

                item.appendChild(nameSpan);
                item.appendChild(removeBtn);
                techAttachList.appendChild(item);
            });
        }
    }
```

with:

```js
    // ── Technical photo upload preview ─────────────────────────────────────────
    if (techAttachInput && techAttachList) {
        var techFiles = [];

        function syncTechAttachInputFiles() {
            var dt = new DataTransfer();
            techFiles.forEach(function (file) { dt.items.add(file); });
            techAttachInput.files = dt.files;
        }

        techAttachInput.addEventListener('change', function () {
            Array.from(techAttachInput.files).forEach(function (file) {
                if (!techFiles.find(function (f) { return f.name === file.name && f.size === file.size; })) {
                    techFiles.push(file);
                }
            });
            syncTechAttachInputFiles();
            renderTechAttachments();
        });

        function renderTechAttachments() {
            techAttachList.innerHTML = '';
            techFiles.forEach(function (file, i) {
                var item = document.createElement('div');
                item.className = 'selected-attachment-item';

                var nameSpan = document.createElement('span');
                nameSpan.textContent = file.name;

                var removeBtn = document.createElement('button');
                removeBtn.type = 'button';
                removeBtn.setAttribute('aria-label', techAttachList.dataset.removeLabel || 'Verwijder');
                removeBtn.textContent = '×';

                removeBtn.addEventListener('click', (function (index) {
                    return function () {
                        techFiles.splice(index, 1);
                        syncTechAttachInputFiles();
                        renderTechAttachments();
                    };
                })(i));

                item.appendChild(nameSpan);
                item.appendChild(removeBtn);
                techAttachList.appendChild(item);
            });
        }
    }
```

- [ ] **Step 3: Manual verification (no JS test runner in this repo)**

`npm run build`, `php artisan serve`, open `/nl/aanvraag`:
- On the technical-details step, pick 1 file, then open the file picker again and pick a different file. Confirm the preview list shows both.
- Remove one via "×". Confirm the count updates.
- Complete and submit the wizard with 2 photos attached (one added, one removed, one remaining is fine for this check — the point is the remaining one must actually upload).
- In `/admin/requests`, open the created request and confirm the attachment count and filenames match exactly what was left in the preview list at submit time.

- [ ] **Step 4: Commit**

```bash
git add resources/views/pages/partials/request-page.blade.php
git commit -m "fix(request): sync attachment input files on add/remove in wizard previews"
```

---

## Task 4: Favicon regression test + missing Open Graph image

**Files:**
- Modify: `resources/views/layouts/app.blade.php:113-118` (Open Graph block)
- Test: `tests/Feature/HomepageTest.php` (extend)

**Interfaces:**
- Consumes: `public/assets/images/logoMetTekst.webp` (existing asset, logo with wordmark — used as the OG image since no dedicated 1200×630 social share image exists in the repo).
- Produces: nothing consumed elsewhere.

- [ ] **Step 1: Add the missing `og:image` tag**

In `resources/views/layouts/app.blade.php`, the Open Graph block currently reads:

```blade
    {{-- Open Graph --}}
    <meta property="og:title" content="@yield('title', $siteName)">
    <meta property="og:description" content="@yield('meta_description', '')">
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:locale" content="{{ $currentLocale === 'fr' ? 'fr_BE' : ($currentLocale === 'en' ? 'en_GB' : 'nl_BE') }}">
```

Add an `og:image` line right after `og:title`:

```blade
    {{-- Open Graph --}}
    <meta property="og:title" content="@yield('title', $siteName)">
    <meta property="og:image" content="{{ asset('assets/images/logoMetTekst.webp') }}">
    <meta property="og:description" content="@yield('meta_description', '')">
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:locale" content="{{ $currentLocale === 'fr' ? 'fr_BE' : ($currentLocale === 'en' ? 'en_GB' : 'nl_BE') }}">
```

- [ ] **Step 2: Add regression tests to `HomepageTest.php`**

Add these test methods to the existing `HomepageTest` class:

```php
    public function test_homepage_includes_favicon_links(): void
    {
        $this->seed(\Database\Seeders\PageSeeder::class);

        $response = $this->get('/nl');

        $response->assertOk();
        $response->assertSee('rel="icon" href="/favicon.ico" sizes="any"', false);
        $response->assertSee('rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png"', false);
        $response->assertSee('rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png"', false);
        $response->assertSee('rel="apple-touch-icon" href="/apple-touch-icon.png"', false);
        $response->assertSee('rel="manifest" href="/site.webmanifest"', false);
    }

    public function test_homepage_includes_open_graph_image(): void
    {
        $this->seed(\Database\Seeders\PageSeeder::class);

        $response = $this->get('/nl');

        $response->assertOk();
        $response->assertSee('property="og:image"', false);
        $response->assertSee('logoMetTekst.webp', false);
    }

    public function test_homepage_includes_footer_credit_link(): void
    {
        $this->seed(\Database\Seeders\PageSeeder::class);

        $response = $this->get('/nl');

        $response->assertOk();
        $response->assertSee('href="https://vanmalderstudio.be/nl"', false);
        $response->assertSee('footer-credit-link', false);
    }
```

- [ ] **Step 3: Run the tests**

Run: `php artisan test --filter=HomepageTest`
Expected: PASS (all HomepageTest tests, including the 3 new ones)

- [ ] **Step 4: Commit**

```bash
git add resources/views/layouts/app.blade.php tests/Feature/HomepageTest.php
git commit -m "feat(seo): add missing Open Graph image and favicon/footer regression tests"
```

---

## Task 5: Homepage hero fills the first viewport cleanly

**Files:**
- Modify: `resources/css/pages/home.css:5-10` (`.home-hero`), `:562-564` (mobile override)

**Interfaces:**
- Consumes: nothing.
- Produces: nothing consumed elsewhere.

**Why `calc(100vh - 79px)`:** The header (`resources/css/layout/header.css:29-35`) has `min-height: 76px` plus a 3px gradient accent border (`.site-header::after`), so its rendered height is 79px. Using that exact figure means the hero + sticky header together fill exactly one viewport height, so the "Diensten" section starts right at the fold instead of mid-scroll.

- [ ] **Step 1: Add `min-height` and a sane max cap to the desktop hero**

In `resources/css/pages/home.css`, the current rule:

```css
.home-hero {
    position: relative;
    overflow: hidden;
    padding: 80px 0 88px;
    background: var(--color-background);
}
```

becomes:

```css
.home-hero {
    position: relative;
    overflow: hidden;
    display: flex;
    align-items: center;
    min-height: calc(100vh - 79px);
    max-height: 960px;
    padding: 80px 0 88px;
    background: var(--color-background);
}
```

`display: flex; align-items: center;` vertically centers the `.container` inside the taller hero on short-content pages. `max-height: 960px` stops the hero from becoming absurdly tall on ultra-wide/tall desktop monitors while still comfortably covering typical 1080p/1440p viewports.

- [ ] **Step 2: Cancel the viewport-height rule on mobile**

In the existing mobile media query, the current rule:

```css
@media (max-width: 680px) {
    .home-hero {
        padding: 48px 0 56px;
    }
```

becomes:

```css
@media (max-width: 680px) {
    .home-hero {
        min-height: auto;
        max-height: none;
        padding: 48px 0 56px;
    }
```

This keeps mobile at its existing natural content height (per the "don't make mobile hero too tall" requirement) while the desktop fold fix applies only at wider viewports.

- [ ] **Step 3: Manual verification**

`npm run build`, `php artisan serve`, open `/nl` at a 1440×900 and a 1920×1080 desktop viewport width in devtools:
- Hero fills the screen below the sticky header with no visible "Diensten" content peeking in.
- CTA buttons remain visible without scrolling.
- At a 375px mobile width, hero height looks unchanged from before this change (content-driven, not viewport-driven).

- [ ] **Step 4: Commit**

```bash
git add resources/css/pages/home.css
git commit -m "style(home): make hero fill first viewport cleanly on desktop"
```

---

## Task 6: Footer credit right-aligned on desktop, centered on mobile

**Files:**
- Modify: `resources/views/layouts/app.blade.php:319-330` (footer-bottom markup)
- Modify: `resources/css/layout/footer.css:74-83` (footer-bottom layout), `:162-168` (mobile block)
- Test: `tests/Feature/HomepageTest.php` (extend — reuses the assertion already added in Task 4, no new test needed here beyond a layout smoke check)

**Interfaces:**
- Consumes: nothing new (`footer-credit-link` CSS class, pulse animation and `prefers-reduced-motion` guard at `footer.css:97-119` are untouched).
- Produces: new class `.footer-bottom-row` for later reference if needed; nothing else depends on it.

- [ ] **Step 1: Restructure the footer-bottom markup**

In `resources/views/layouts/app.blade.php`, the current block:

```blade
        <div class="container footer-bottom">
            <p>
                &copy; {{ date('Y') }} {{ $siteName }}. All rights reserved.
                &nbsp;&middot;&nbsp;
                <a class="footer-privacy-link" href="{{ route('pages.show', ['locale' => $currentLocale, 'slug' => $privacySlug]) }}">
                    {{ $privacyLabel }}
                </a>
                &nbsp;&middot;&nbsp;
                <a class="footer-credit-link" href="https://vanmalderstudio.be/nl" target="_blank" rel="noopener">
                    {{ $nav['designed_by'] }}
                </a>
            </p>

            @if (session()->has('admin_user_email'))
```

becomes:

```blade
        <div class="container footer-bottom">
            <div class="footer-bottom-row">
                <p class="footer-copyright">
                    &copy; {{ date('Y') }} {{ $siteName }}. All rights reserved.
                    &nbsp;&middot;&nbsp;
                    <a class="footer-privacy-link" href="{{ route('pages.show', ['locale' => $currentLocale, 'slug' => $privacySlug]) }}">
                        {{ $privacyLabel }}
                    </a>
                </p>

                <a class="footer-credit-link" href="https://vanmalderstudio.be/nl" target="_blank" rel="noopener">
                    {{ $nav['designed_by'] }}
                </a>
            </div>

            @if (session()->has('admin_user_email'))
```

(The `@if`/`@else` admin-actions block and the closing `</div>` after it are unchanged — only the copyright paragraph is being wrapped and split.)

- [ ] **Step 2: Add the flex layout in CSS**

In `resources/css/layout/footer.css`, after the existing `.footer-bottom p` rule (around line 83), add:

```css
.footer-bottom-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 6px 16px;
    margin-bottom: 10px;
}

.footer-bottom-row .footer-copyright {
    margin: 0;
}
```

- [ ] **Step 3: Stack and center on mobile**

In the existing mobile block (currently lines 162-168):

```css
@media (max-width: 860px) {
    .footer-grid {
        grid-template-columns: 1fr;
        gap: 30px;
        padding: 42px 0 34px;
    }
}
```

add a second rule in the same block:

```css
@media (max-width: 860px) {
    .footer-grid {
        grid-template-columns: 1fr;
        gap: 30px;
        padding: 42px 0 34px;
    }

    .footer-bottom-row {
        flex-direction: column;
        align-items: center;
        text-align: center;
        gap: 8px;
    }
}
```

- [ ] **Step 4: Manual verification**

`npm run build`, `php artisan serve`, open `/nl`:
- Desktop (≥861px): copyright + privacy link on the left, "Designed by VanMalderStudio" on the right, same row.
- Mobile (≤860px): both stacked, centered.
- Pulsing gold animation on the credit link still runs; confirm it stops when the OS "reduce motion" setting is enabled (devtools → Rendering → Emulate CSS prefers-reduced-motion).
- Admin login link / admin panel + logout row (below this) is unaffected either way.

- [ ] **Step 5: Commit**

```bash
git add resources/views/layouts/app.blade.php resources/css/layout/footer.css
git commit -m "style(footer): right-align designed-by credit on desktop, center on mobile"
```

---

## Task 7: Sync stronger SEO copy into the service page seeder

**Files:**
- Modify: `database/seeders/PageSeeder.php:135-176` (`createServicePages()` and `getServiceContent()`)

**Interfaces:**
- Consumes: content already written and reviewed in `database/seeders/PageContentSeeder.php:27-127` (unique per-service, per-locale `content` / `meta_title` / `meta_description`).
- Produces: `Page`/`PageTranslation` rows seeded by `PageSeeder` (the seeder `DatabaseSeeder` actually calls) now carry the better copy directly — no separate patch step needed for fresh installs.

**Why here and not by wiring `PageContentSeeder` into `DatabaseSeeder`:** the prior commit `87a7426` ("sync homepage seeder meta copy with live content") already established the pattern of keeping `PageSeeder` itself as the single source of truth for seed content, rather than layering a patch seeder on top. This task extends that same pattern to the 6 service pages, which is the gap that commit didn't cover.

- [ ] **Step 1: Replace the generic content generator with real per-service copy**

In `database/seeders/PageSeeder.php`, the current `createServicePages()` method:

```php
    private function createServicePages(): void
    {
        $services = config('services');

        foreach ($services as $serviceCode => $service) {
            if (!($service['is_active'] ?? false)) {
                continue;
            }

            $page = Page::create([
                'code' => $serviceCode,
                'type' => 'service',
                'is_active' => true,
            ]);

            $translations = [];

            foreach ($service['translations'] as $locale => $translation) {
                $translations[] = [
                    'locale' => $locale,
                    'slug' => $translation['slug'],
                    'title' => $translation['title'],
                    'intro' => $translation['description'],
                    'content' => $this->getServiceContent($locale, $translation['title']),
                    'meta_title' => $translation['title'] . ' | mastechnics',
                    'meta_description' => $translation['description'],
                ];
            }

            $page->translations()->createMany($translations);
        }
    }

    private function getServiceContent(string $locale, string $serviceTitle): string
    {
        return match ($locale) {
            'fr' => "Nous vous aidons avec les demandes liées à {$serviceTitle}. Grâce à une prise d'informations structurée, nous pouvons mieux comprendre votre situation et proposer une prochaine étape claire.",
            'en' => "We help with requests related to {$serviceTitle}. With a structured intake, we can better understand your situation and suggest a clear next step.",
            default => "Wij helpen met aanvragen rond {$serviceTitle}. Door de informatie gestructureerd te verzamelen, kunnen we de situatie sneller begrijpen en een duidelijke volgende stap voorstellen.",
        };
    }
```

becomes:

```php
    private function createServicePages(): void
    {
        $services = config('services');
        $seoCopy  = $this->serviceSeoCopy();

        foreach ($services as $serviceCode => $service) {
            if (!($service['is_active'] ?? false)) {
                continue;
            }

            $page = Page::create([
                'code' => $serviceCode,
                'type' => 'service',
                'is_active' => true,
            ]);

            $translations = [];

            foreach ($service['translations'] as $locale => $translation) {
                $copy = $seoCopy[$serviceCode][$locale] ?? null;

                $translations[] = [
                    'locale' => $locale,
                    'slug' => $translation['slug'],
                    'title' => $translation['title'],
                    'intro' => $translation['description'],
                    'content' => $copy['content'] ?? $this->getServiceContent($locale, $translation['title']),
                    'meta_title' => $copy['meta_title'] ?? ($translation['title'] . ' | mastechnics'),
                    'meta_description' => $copy['meta_description'] ?? $translation['description'],
                ];
            }

            $page->translations()->createMany($translations);
        }
    }

    private function getServiceContent(string $locale, string $serviceTitle): string
    {
        return match ($locale) {
            'fr' => "Nous vous aidons avec les demandes liées à {$serviceTitle}. Grâce à une prise d'informations structurée, nous pouvons mieux comprendre votre situation et proposer une prochaine étape claire.",
            'en' => "We help with requests related to {$serviceTitle}. With a structured intake, we can better understand your situation and suggest a clear next step.",
            default => "Wij helpen met aanvragen rond {$serviceTitle}. Door de informatie gestructureerd te verzamelen, kunnen we de situatie sneller begrijpen en een duidelijke volgende stap voorstellen.",
        };
    }

    /**
     * Unique, keyword-relevant SEO copy per service and locale. Keyed by the
     * config('services') service code so it survives slug changes.
     */
    private function serviceSeoCopy(): array
    {
        return [
            'heating' => [
                'nl' => [
                    'content' => 'Mastechnics verzorgt het onderhoud, de herstelling en de installatie van verwarmingssystemen voor particulieren en bedrijven. Van klassieke gasketel tot moderne warmtepomp: wij werken met alle gangbare systemen. Via een duidelijke technische intake zorgen wij voor snelle opvolging en eerlijk advies.',
                    'meta_title' => 'Verwarming — Mastechnics | Onderhoud, herstelling en installatie',
                    'meta_description' => 'Professionele verwarmingsservice: onderhoud, herstelling en installatie van gasketel, condensatieketel of warmtepomp. Vraag snel een offerte of interventie aan.',
                ],
                'fr' => [
                    'content' => 'Mastechnics assure l\'entretien, la réparation et l\'installation de systèmes de chauffage pour particuliers et entreprises. Des chaudières gaz aux pompes à chaleur : nous travaillons avec tous les systèmes courants. Une prise en charge technique claire garantit un suivi rapide et un conseil honnête.',
                    'meta_title' => 'Chauffage — Mastechnics | Entretien, réparation et installation',
                    'meta_description' => 'Service de chauffage professionnel : entretien, réparation et installation de chaudière gaz, condensation ou pompe à chaleur. Demandez un devis ou une intervention.',
                ],
                'en' => [
                    'content' => 'Mastechnics provides maintenance, repair and installation of heating systems for homes and businesses. From gas boilers to heat pumps: we work with all common systems. A clear technical intake ensures faster follow-up and honest advice.',
                    'meta_title' => 'Heating — Mastechnics | Maintenance, repair and installation',
                    'meta_description' => 'Professional heating service: maintenance, repair and installation of gas boiler, condensing boiler or heat pump. Request a fast quote or call-out.',
                ],
            ],
            'airco' => [
                'nl' => [
                    'content' => 'Mastechnics installeert, onderhoudt en herstelt airconditioningsystemen voor woning, kantoor en bedrijf. Wij werken met split-units, multi-split en commerciële systemen van alle gangbare merken. Een grondige technische intake helpt ons een scherpe en correcte offerte op te maken.',
                    'meta_title' => 'Airco — Mastechnics | Installatie, onderhoud en herstelling',
                    'meta_description' => 'Airco installatie, onderhoud en herstelling voor particulieren en bedrijven. Split-unit, multi-split of commercieel systeem. Vraag snel een offerte aan.',
                ],
                'fr' => [
                    'content' => 'Mastechnics installe, entretient et répare les systèmes de climatisation pour habitations, bureaux et entreprises. Nous travaillons avec des unités split, multi-split et des systèmes commerciaux de toutes les marques courantes. Une prise en charge technique approfondie nous aide à établir un devis précis.',
                    'meta_title' => 'Climatisation — Mastechnics | Installation, entretien et réparation',
                    'meta_description' => 'Installation, entretien et réparation de climatisation pour particuliers et entreprises. Split-unit, multi-split ou système commercial. Demandez un devis rapide.',
                ],
                'en' => [
                    'content' => 'Mastechnics installs, maintains and repairs air conditioning systems for homes, offices and businesses. We work with split units, multi-split and commercial systems from all common brands. A thorough technical intake helps us prepare an accurate and competitive quote.',
                    'meta_title' => 'Air conditioning — Mastechnics | Installation, maintenance and repair',
                    'meta_description' => 'Air conditioning installation, maintenance and repair for homes and businesses. Split unit, multi-split or commercial system. Request a fast quote.',
                ],
            ],
            'plumbing' => [
                'nl' => [
                    'content' => 'Mastechnics biedt professionele hulp bij sanitaire installaties, herstellingen en loodgieterswerk. Van waterlek tot volledige badkamerrenovatie: wij reageren snel en werken degelijk, voor zowel particulieren als professionele klanten.',
                    'meta_title' => 'Sanitair — Mastechnics | Installatie, herstelling en loodgieterswerk',
                    'meta_description' => 'Professioneel sanitair en loodgieterswerk: waterlek, verstopte afvoer, badkamer of toilet. Snelle interventie voor particulieren en bedrijven in België.',
                ],
                'fr' => [
                    'content' => 'Mastechnics propose une aide professionnelle pour les installations et réparations sanitaires et de plomberie. De la fuite d\'eau à la rénovation complète de salle de bain : nous intervenons rapidement et efficacement, pour les particuliers comme pour les professionnels.',
                    'meta_title' => 'Plomberie — Mastechnics | Installation, réparation et sanitaire',
                    'meta_description' => 'Plomberie et sanitaire professionnels : fuite d\'eau, évacuation bouchée, salle de bain ou toilette. Intervention rapide pour particuliers et entreprises.',
                ],
                'en' => [
                    'content' => 'Mastechnics provides professional help with plumbing installations and repairs. From water leaks to complete bathroom renovations: we respond quickly and work reliably for both homeowners and businesses.',
                    'meta_title' => 'Plumbing — Mastechnics | Installation, repair and sanitary work',
                    'meta_description' => 'Professional plumbing and sanitary work: water leak, blocked drain, bathroom or toilet. Fast response for homeowners and businesses in Belgium.',
                ],
            ],
            'ventilation' => [
                'nl' => [
                    'content' => 'Mastechnics plaatst, onderhoudt en herstelt ventilatiesystemen voor woningen, appartementen en bedrijven. Systeem C, systeem D of mechanische ventilatie op maat: wij zorgen voor een correcte installatie en EPB-conforme oplevering.',
                    'meta_title' => 'Ventilatie — Mastechnics | Plaatsing en onderhoud van ventilatiesystemen',
                    'meta_description' => 'Ventilatiesystemen voor woning en bedrijf: plaatsing en onderhoud van systeem C, D en mechanische ventilatie. EPB-conform en energiezuinig. Vraag een offerte aan.',
                ],
                'fr' => [
                    'content' => 'Mastechnics installe, entretient et répare les systèmes de ventilation pour habitations, appartements et entreprises. Système C, système D ou ventilation mécanique sur mesure : nous garantissons une installation correcte et une réception conforme aux normes EPB.',
                    'meta_title' => 'Ventilation — Mastechnics | Installation et entretien de systèmes de ventilation',
                    'meta_description' => 'Systèmes de ventilation pour habitations et entreprises : installation et entretien système C, D et ventilation mécanique. Conforme aux normes EPB.',
                ],
                'en' => [
                    'content' => 'Mastechnics installs, maintains and repairs ventilation systems for homes, apartments and businesses. System C, system D or custom mechanical ventilation: we ensure correct installation and EPB-compliant delivery.',
                    'meta_title' => 'Ventilation — Mastechnics | Installation and maintenance of ventilation systems',
                    'meta_description' => 'Ventilation systems for homes and businesses: installation and maintenance of system C, D and mechanical ventilation. EPB-compliant and energy-efficient.',
                ],
            ],
            'water-softeners' => [
                'nl' => [
                    'content' => 'Mastechnics adviseert, installeert en onderhoudt waterverzachters voor zones met hard water in België. Wij werken met kwalitatieve toestellen van erkende merken en zorgen voor een correcte installatie, aansluiting en inregeling.',
                    'meta_title' => 'Waterverzachters — Mastechnics | Advies, installatie en onderhoud',
                    'meta_description' => 'Waterverzachter plaatsen of onderhouden in België? Advies bij kalkproblemen, professionele installatie en periodiek onderhoud. Vraag vrijblijvend een offerte aan.',
                ],
                'fr' => [
                    'content' => 'Mastechnics conseille, installe et entretient des adoucisseurs d\'eau pour les zones à eau dure en Belgique. Nous travaillons avec des appareils de qualité de marques reconnues et garantissons une installation correcte, un raccordement et un réglage soignés.',
                    'meta_title' => 'Adoucisseurs d\'eau — Mastechnics | Conseil, installation et entretien',
                    'meta_description' => 'Installer ou entretenir un adoucisseur d\'eau en Belgique ? Conseil pour problèmes de calcaire, installation professionnelle et entretien périodique.',
                ],
                'en' => [
                    'content' => 'Mastechnics advises, installs and maintains water softeners for hard water areas in Belgium. We work with quality appliances from recognised brands and ensure correct installation, connection and calibration.',
                    'meta_title' => 'Water softeners — Mastechnics | Advice, installation and maintenance',
                    'meta_description' => 'Install or maintain a water softener in Belgium? Expert advice for hard water problems, professional installation and periodic servicing. Request a free quote.',
                ],
            ],
            'cold-rooms' => [
                'nl' => [
                    'content' => 'Mastechnics ontwerpt, installeert en onderhoudt koelinstallaties en koelcellen voor de horeca, voedingsindustrie en commerciële sector. Wij werken met erkende F-gas technici en leveren betrouwbare koeloplossingen op maat van uw activiteit.',
                    'meta_title' => 'Koelcellen — Mastechnics | Installatie en onderhoud van koelinstallaties',
                    'meta_description' => 'Koelcel of koelinstallatie voor horeca of industrie in België? Plaatsing, onderhoud en herstelling door erkende F-gas installateur. Vraag een offerte aan.',
                ],
                'fr' => [
                    'content' => 'Mastechnics conçoit, installe et entretient des installations frigorifiques et chambres froides pour l\'horeca, l\'industrie alimentaire et le secteur commercial. Nous faisons appel à des techniciens F-gaz agréés et proposons des solutions de réfrigération sur mesure.',
                    'meta_title' => 'Chambres froides — Mastechnics | Installation et entretien de réfrigération',
                    'meta_description' => 'Chambre froide ou installation frigorifique pour l\'horeca ou l\'industrie en Belgique ? Installation, entretien et réparation par technicien F-gaz agréé.',
                ],
                'en' => [
                    'content' => 'Mastechnics designs, installs and maintains refrigeration installations and cold rooms for the catering industry, food sector and commercial clients. We use certified F-gas technicians and deliver reliable cooling solutions tailored to your business.',
                    'meta_title' => 'Cold rooms — Mastechnics | Installation and maintenance of refrigeration',
                    'meta_description' => 'Cold rooms or refrigeration for catering or industry in Belgium? Installation, maintenance and repair by certified F-gas technician. Request a quote.',
                ],
            ],
        ];
    }
```

`getServiceContent()` stays in place as the fallback for any service code not present in `serviceSeoCopy()` (defensive — keeps the seeder from ever producing an empty `content` field if a new service is added to `config/services.php` before its copy is written).

- [ ] **Step 2: Write a seeder test**

Create `tests/Feature/ServicePageSeoTest.php`:

```php
<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServicePageSeoTest extends TestCase
{
    use RefreshDatabase;

    public function test_service_pages_have_unique_non_generic_meta_titles(): void
    {
        $this->seed(\Database\Seeders\PageSeeder::class);

        $response = $this->get('/nl/verwarming');
        $response->assertOk();
        $response->assertSee('<title>Verwarming — Mastechnics | Onderhoud, herstelling en installatie</title>', false);

        $response = $this->get('/nl/airco');
        $response->assertOk();
        $response->assertSee('<title>Airco — Mastechnics | Installatie, onderhoud en herstelling</title>', false);
    }

    public function test_french_and_english_service_pages_have_localized_meta_titles(): void
    {
        $this->seed(\Database\Seeders\PageSeeder::class);

        $this->get('/fr/chauffage')->assertSee(
            '<title>Chauffage — Mastechnics | Entretien, réparation et installation</title>',
            false
        );

        $this->get('/en/heating')->assertSee(
            '<title>Heating — Mastechnics | Maintenance, repair and installation</title>',
            false
        );
    }
}
```

- [ ] **Step 3: Run the tests**

Run: `php artisan test --filter=ServicePageSeoTest`
Expected: PASS (2 tests)

- [ ] **Step 4: Commit**

```bash
git add database/seeders/PageSeeder.php tests/Feature/ServicePageSeoTest.php
git commit -m "feat(seo): give each service page unique, differentiated meta copy"
```

- [ ] **Step 5: Note the production data migration (do NOT run this yourself — flag it for the user)**

This task changes the **seeder source**, which only affects fresh `php artisan migrate:fresh --seed` runs. The live production database already has rows seeded with the old generic copy and will not pick up this change automatically. To apply it to the already-running site, someone with production DB access needs to run one of:
- `php artisan db:seed --class=PageContentSeeder` (idempotent, `updateOrCreate`-style `update()` calls, safe to run against existing data, and its copy is now identical to what `PageSeeder` produces), or
- a fresh reseed if the environment supports it.

Do not run this against production yourself — surface it as a manual follow-up step in the final report per "Do not deploy automatically."

---

## Task 8: SEO technical — enable the sitemap reference in robots.txt

**Files:**
- Modify: `public/robots.txt`

**Interfaces:** none.

- [ ] **Step 1: Uncomment the sitemap line now that the site is live**

Current `public/robots.txt`:

```
User-agent: *
Disallow: /admin/

# After deploying to production, uncomment the Sitemap line below
# and replace the domain with the actual production domain.
# Sitemap: https://mastechnics.be/sitemap.xml
```

becomes:

```
User-agent: *
Disallow: /admin/

Sitemap: https://mastechnics.be/sitemap.xml
```

- [ ] **Step 2: Verify the sitemap route still resolves and includes the pages this affects**

Run: `php artisan route:list --name=sitemap`
Expected: shows `GET sitemap.xml` bound to `SitemapController@index`.

Manually visit `/sitemap.xml` on a local server and confirm it lists all locale variants of home, the 6 service pages, `aanvraag`/`demande`/`request`, and `contact` — `SitemapController` already pulls every active `PageTranslation`, so no controller change is needed; this step is verification only.

- [ ] **Step 3: Note the canonical/OG URL caveat for the final report**

`layouts/app.blade.php` builds `canonical` and `og:url` from `url()->current()`, which resolves using Laravel's `APP_URL` config (`config/app.php:55`, `env('APP_URL', 'http://localhost')`). This repo's `.env` is not committed, so it cannot be verified from the codebase. Flag in the final report: confirm the production `.env` has `APP_URL=https://mastechnics.be` (not a preview/staging subdomain) so canonical URLs and OG tags point at the real domain.

- [ ] **Step 4: Commit**

```bash
git add public/robots.txt
git commit -m "fix(seo): reference production sitemap in robots.txt now that the site is live"
```

---

## Task 9: Contact form "Bericht voorbereiden" regression test

**Files:**
- Test: `tests/Feature/ContactPageTest.php` (new file)

**Interfaces:** none — this is verification-only, since `contact-page.blade.php` already validates and builds the `mailto:` link correctly (commit `a3ac4a8`).

- [ ] **Step 1: Write the test**

Create `tests/Feature/ContactPageTest.php`:

```php
<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContactPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_contact_page_renders_prepare_message_button_wired_to_mailto(): void
    {
        $this->seed(\Database\Seeders\PageSeeder::class);

        $response = $this->get('/nl/contact');

        $response->assertOk();
        $response->assertSee('id="contactPrepareBtn"', false);
        $response->assertSee('id="contactForm"', false);
        $response->assertSee('data-mailto=', false);
        $response->assertSee("window.location.href = 'mailto:' + mailto", false);
    }

    public function test_contact_page_renders_in_all_locales(): void
    {
        $this->seed(\Database\Seeders\PageSeeder::class);

        $this->get('/nl/contact')->assertOk()->assertSee('Bericht voorbereiden');
        $this->get('/fr/contact')->assertOk()->assertSee('Préparer le message');
        $this->get('/en/contact')->assertOk()->assertSee('Prepare message');
    }
}
```

- [ ] **Step 2: Run the tests**

Run: `php artisan test --filter=ContactPageTest`
Expected: PASS (2 tests)

- [ ] **Step 3: Manual verification**

`php artisan serve`, open `/nl/contact`, fill in name + message, click "Bericht voorbereiden". Confirm the browser attempts to open a `mailto:` link (devtools → check `window.location` change, or just observe the OS mail-client prompt).

- [ ] **Step 4: Commit**

```bash
git add tests/Feature/ContactPageTest.php
git commit -m "test(contact): add regression coverage for prepare-message button"
```

---

## Task 10: Quote item save regression test

**Files:**
- Test: `tests/Feature/Admin/QuoteSaveTest.php` (new file)

**Interfaces:** none — this is verification-only, since `Admin\QuoteController::store()` already deletes and recreates items per request (commit `9539017`). No `Quote`/`QuoteItem` factories exist in this repo, so the test creates records directly via `CustomerRequest::create()` and posts the same payload shape the desktop/mobile quote-edit form submits.

- [ ] **Step 1: Read the quote-edit Blade form to confirm the exact POST field names expected**

Run: `grep -n "name=\"items" resources/views/admin/quotes/edit.blade.php`

Confirm the field names match `items[{i}][description]`, `items[{i}][quantity]`, `items[{i}][unit_price_excl_vat]`, `items[{i}][vat_rate]` as validated in `QuoteController::store()` — adjust the test payload below if the actual names differ.

- [ ] **Step 2: Write the test**

Create `tests/Feature/Admin/QuoteSaveTest.php`:

```php
<?php

namespace Tests\Feature\Admin;

use App\Models\CustomerRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuoteSaveTest extends TestCase
{
    use RefreshDatabase;

    private function asAdmin(): self
    {
        $this->withSession(['admin_user_email' => 'admin@mastechnics.be']);

        return $this;
    }

    private function makeCustomerRequest(): CustomerRequest
    {
        return CustomerRequest::create([
            'locale' => 'nl',
            'service_slug' => 'verwarming',
            'customer_name' => 'Jan Janssens',
            'customer_email' => 'jan@example.com',
            'description' => 'Ketel maakt lawaai.',
            'privacy_consent' => true,
            'status' => 'new',
            'service_category' => 'cv_onderhoud',
        ]);
    }

    public function test_quote_items_are_saved_and_totals_recalculated(): void
    {
        $customerRequest = $this->makeCustomerRequest();

        $response = $this->asAdmin()->post(
            route('admin.requests.quote.store', $customerRequest),
            [
                'title' => 'Offerte CV-onderhoud',
                'items' => [
                    [
                        'description' => 'Jaarlijks onderhoud gasketel',
                        'quantity' => 1,
                        'unit_price_excl_vat' => 120,
                        'vat_rate' => 21,
                    ],
                    [
                        'description' => 'Verplaatsingskosten',
                        'quantity' => 1,
                        'unit_price_excl_vat' => 25,
                        'vat_rate' => 21,
                    ],
                ],
            ]
        );

        $response->assertRedirect(route('admin.requests.show', $customerRequest));
        $response->assertSessionHas('success', 'quote_saved');

        $customerRequest->refresh();
        $quote = $customerRequest->quote;

        $this->assertNotNull($quote);
        $this->assertCount(2, $quote->items);
        $this->assertSame('Jaarlijks onderhoud gasketel', $quote->items()->orderBy('position')->first()->description);
    }

    public function test_resaving_a_quote_replaces_items_instead_of_duplicating_them(): void
    {
        $customerRequest = $this->makeCustomerRequest();

        $payload = [
            'items' => [
                ['description' => 'Item A', 'quantity' => 1, 'unit_price_excl_vat' => 50, 'vat_rate' => 21],
            ],
        ];

        $this->asAdmin()->post(route('admin.requests.quote.store', $customerRequest), $payload);

        $this->asAdmin()->post(route('admin.requests.quote.store', $customerRequest), [
            'items' => [
                ['description' => 'Item A', 'quantity' => 1, 'unit_price_excl_vat' => 50, 'vat_rate' => 21],
                ['description' => 'Item B', 'quantity' => 2, 'unit_price_excl_vat' => 30, 'vat_rate' => 21],
            ],
        ]);

        $customerRequest->refresh();

        $this->assertCount(2, $customerRequest->quote->items);
    }

    public function test_quote_store_requires_admin_session(): void
    {
        $customerRequest = $this->makeCustomerRequest();

        $response = $this->post(route('admin.requests.quote.store', $customerRequest), [
            'items' => [
                ['description' => 'Item A', 'quantity' => 1, 'unit_price_excl_vat' => 50, 'vat_rate' => 21],
            ],
        ]);

        $response->assertRedirect(route('admin.login'));
    }
}
```

- [ ] **Step 3: Run the tests**

Run: `php artisan test --filter=QuoteSaveTest`
Expected: PASS (3 tests). If the field-name check in Step 1 revealed different names, adjust the payload keys before running.

- [ ] **Step 4: Manual verification (desktop + mobile input regression)**

`php artisan serve`, log into `/admin/login`, open a request's quote edit page:
- Desktop width: add 2 line items, save, confirm redirect shows "quote_saved" success and the items persist on reload.
- Mobile width (devtools responsive mode, ≤480px): repeat — add items via the mobile-layout inputs specifically, since the bug this is guarding against was a duplicate desktop/mobile input issue. Confirm no duplicate items are created and values match what was typed.

- [ ] **Step 5: Commit**

```bash
git add tests/Feature/Admin/QuoteSaveTest.php
git commit -m "test(quotes): add regression coverage for quote item save flow"
```

---

## Task 11: Add company logo to the quote PDF header

**Files:**
- Modify: `app/Http/Controllers/Admin/QuoteController.php:105-123` (`pdf()` method)
- Modify: `resources/views/admin/quotes/pdf.blade.php:18-40` (header CSS), `:341-357` (header markup)
- Test: `tests/Feature/Admin/QuotePdfTest.php` (new file)

**Interfaces:**
- Consumes: `public_path('android-chrome-512x512.png')` (existing asset, confirmed present at `public/android-chrome-512x512.png` — a square PNG, not the two `.webp` wordmark logos, chosen specifically because dompdf's image support is guaranteed for PNG regardless of the server's GD/Imagick WebP build, and because base64-embedding via `public_path()` + `file_get_contents()` sidesteps dompdf's remote-file-fetch path/chroot resolution entirely, per the "reliable local asset, no external URLs" requirement).
- Produces: `pdf.blade.php` now expects an optional `$logoBase64` view variable (a `data:image/png;base64,...` string or `null`).

**Why base64, not a plain `<img src="{{ public_path(...) }}">`:** dompdf's default `isRemoteEnabled` / chroot settings can silently fail to resolve an absolute Windows filesystem path or a `public_path()` string passed straight into `src`, especially when running under `php artisan serve` vs. a real webserver with different working directories. A `data:` URI has no such ambiguity — dompdf decodes it directly, so the image either renders or the base64 string is empty (which the `@if ($logoBase64)` guard handles gracefully instead of producing a broken PDF).

- [ ] **Step 1: Confirm the asset exists and check dompdf's config for anything that would block data URIs**

Run: `Test-Path "public/android-chrome-512x512.png"` (PowerShell) — expect `True`.

Run: `grep -n "isRemoteEnabled\|chroot" config/dompdf.php` — data URIs are decoded by dompdf's internal image cache before any remote-fetch or chroot check applies, so this is a confirmation step, not something that needs changing.

- [ ] **Step 2: Base64-encode the logo in the controller and pass it to the view**

In `app/Http/Controllers/Admin/QuoteController.php`, the current `pdf()` method:

```php
    public function pdf(CustomerRequest $customerRequest): Response
    {
        $quote = $customerRequest->quote;

        abort_if(! $quote, 404, 'Geen offerte gevonden.');

        $quote->load('items');

        $pdf = Pdf::loadView('admin.quotes.pdf', [
            'quote'           => $quote,
            'customerRequest' => $customerRequest,
        ]);

        $pdf->setPaper('A4', 'portrait');

        $filename = strtolower($quote->quote_number ?? 'offerte') . '-mastechnics-offerte.pdf';

        return $pdf->stream($filename);
    }
```

becomes:

```php
    public function pdf(CustomerRequest $customerRequest): Response
    {
        $quote = $customerRequest->quote;

        abort_if(! $quote, 404, 'Geen offerte gevonden.');

        $quote->load('items');

        $pdf = Pdf::loadView('admin.quotes.pdf', [
            'quote'           => $quote,
            'customerRequest' => $customerRequest,
            'logoBase64'      => $this->quotePdfLogoBase64(),
        ]);

        $pdf->setPaper('A4', 'portrait');

        $filename = strtolower($quote->quote_number ?? 'offerte') . '-mastechnics-offerte.pdf';

        return $pdf->stream($filename);
    }

    private function quotePdfLogoBase64(): ?string
    {
        $path = public_path('android-chrome-512x512.png');

        if (! is_file($path)) {
            return null;
        }

        return 'data:image/png;base64,' . base64_encode(file_get_contents($path));
    }
```

- [ ] **Step 3: Add the logo to the PDF header layout**

In `resources/views/admin/quotes/pdf.blade.php`, the current header markup:

```blade
    {{-- ── Header ─────────────────────────────────────── --}}
    <div class="header-bar">
        <div class="header-bar-inner">
            <div class="header-company">
                <div class="header-company-name">MAS Technics</div>
                <div class="header-company-tagline">
                    Verwarming · Airco · Sanitair · Ventilatie · Waterverzachters · Koeling
                </div>
            </div>
            <div class="header-doc-info">
```

becomes:

```blade
    {{-- ── Header ─────────────────────────────────────── --}}
    <div class="header-bar">
        <div class="header-bar-inner">
            @if ($logoBase64)
                <div class="header-logo-cell">
                    <img src="{{ $logoBase64 }}" alt="MAS Technics" class="header-logo-img">
                </div>
            @endif
            <div class="header-company">
                <div class="header-company-name">MAS Technics</div>
                <div class="header-company-tagline">
                    Verwarming · Airco · Sanitair · Ventilatie · Waterverzachters · Koeling
                </div>
            </div>
            <div class="header-doc-info">
```

(everything from `<div class="header-doc-info">` onward is unchanged).

- [ ] **Step 4: Add the logo cell CSS**

In the `<style>` block, after the existing `.header-company-tagline` rule (around line 46), add:

```css
        .header-logo-cell {
            display: table-cell;
            vertical-align: middle;
            width: 46px;
            padding-right: 14px;
        }

        .header-logo-img {
            display: block;
            width: 42px;
            height: 42px;
        }
```

- [ ] **Step 5: Write a PDF generation regression test**

Create `tests/Feature/Admin/QuotePdfTest.php`:

```php
<?php

namespace Tests\Feature\Admin;

use App\Models\CustomerRequest;
use App\Models\Quote;
use App\Models\QuoteItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuotePdfTest extends TestCase
{
    use RefreshDatabase;

    public function test_quote_pdf_downloads_successfully_with_logo_embedded(): void
    {
        $this->withSession(['admin_user_email' => 'admin@mastechnics.be']);

        $customerRequest = CustomerRequest::create([
            'locale' => 'nl',
            'service_slug' => 'verwarming',
            'customer_name' => 'Jan Janssens',
            'customer_email' => 'jan@example.com',
            'description' => 'Ketel maakt lawaai.',
            'privacy_consent' => true,
            'status' => 'new',
            'service_category' => 'cv_onderhoud',
        ]);

        $quote = Quote::create([
            'customer_request_id' => $customerRequest->id,
            'quote_number' => 'OFF-2026-0001',
        ]);

        $lineTotals = QuoteItem::calculateLine(1, 120, 21);
        $quote->items()->create(array_merge([
            'position' => 1,
            'description' => 'Jaarlijks onderhoud gasketel',
            'quantity' => 1,
            'unit_price_excl_vat' => 120,
            'vat_rate' => 21,
        ], $lineTotals));

        $quote->recalculateTotals();

        $response = $this->get(route('admin.requests.quote.pdf', $customerRequest));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_quote_pdf_requires_admin_session(): void
    {
        $customerRequest = CustomerRequest::create([
            'locale' => 'nl',
            'service_slug' => 'verwarming',
            'customer_name' => 'Jan Janssens',
            'privacy_consent' => true,
            'status' => 'new',
        ]);

        Quote::create([
            'customer_request_id' => $customerRequest->id,
            'quote_number' => 'OFF-2026-0002',
        ]);

        $response = $this->get(route('admin.requests.quote.pdf', $customerRequest));

        $response->assertRedirect(route('admin.login'));
    }
}
```

- [ ] **Step 6: Run the tests**

Run: `php artisan test --filter=QuotePdfTest`
Expected: PASS (2 tests)

- [ ] **Step 7: Manual verification**

`php artisan serve`, log into `/admin/login`, open a request that has a quote, click "Download PDF" (or visit the PDF route directly). Confirm:
- PDF downloads/streams without error.
- The MAS Technics icon appears top-left of the header bar, next to the company name.
- Company name + tagline still readable, quote number and date still visible top-right, nothing overlaps or is cut off.

- [ ] **Step 8: Commit**

```bash
git add app/Http/Controllers/Admin/QuoteController.php resources/views/admin/quotes/pdf.blade.php tests/Feature/Admin/QuotePdfTest.php
git commit -m "feat(quotes): add company logo to quote PDF header"
```

---

## Task 12: Full verification pass

**Files:** none modified — this task only runs checks.

- [ ] **Step 1: Run the full PHP test suite**

Run: `php artisan test`
Expected: all tests pass, including every test added in Tasks 1, 4, 7, 9, 10.

- [ ] **Step 2: Lint every PHP file touched by this plan**

Run (PowerShell):
```powershell
php -l app\Http\Controllers\Admin\QuoteController.php
php -l database\seeders\PageSeeder.php
php -l tests\Feature\RequestWizardTest.php
php -l tests\Feature\HomepageTest.php
php -l tests\Feature\ServicePageSeoTest.php
php -l tests\Feature\ContactPageTest.php
php -l tests\Feature\Admin\QuoteSaveTest.php
```
Expected: `No syntax errors detected` for each.

- [ ] **Step 3: Build the frontend**

Run: `npm run build`
Expected: build succeeds with no errors; confirm `resources/js/request-form.js`'s reduced size is reflected in the manifest output (sanity check that Task 2's simplification actually got bundled).

- [ ] **Step 4: Composer validate (only if composer files changed — they did not in this plan)**

Skip — no `composer.json`/`composer.lock` changes in this plan.

- [ ] **Step 5: Final manual checklist (run through in a real browser, not just devtools emulation where possible)**

- [ ] Favicon visible in the browser tab after a hard refresh / incognito window
- [ ] Request wizard: clicking a service card auto-advances exactly once
- [ ] Request wizard: top Back/Next buttons appear above the card and behave identically to the bottom ones (same enable/disable state, same step transitions)
- [ ] Request wizard: adding files across two separate "Choose files" dialogs keeps all of them; removing one via "×" actually removes it from what gets submitted (cross-check attachment count in `/admin/requests/{id}`)
- [ ] Footer credit "Designed by VanMalderStudio" is right-aligned on desktop, centered on mobile, links to `https://vanmalderstudio.be/nl`
- [ ] Homepage hero fills the first viewport on desktop with no awkward mid-scroll "Diensten" peek; mobile hero height is unchanged/reasonable
- [ ] All 6 service pages (`/nl/verwarming`, `/nl/airco`, `/nl/sanitair`, `/nl/ventilatie`, `/nl/waterverzachters`, `/nl/koelcellen`) render with the new unique meta titles (check the browser tab title) and existing situations/highlights content
- [ ] Contact page "Bericht voorbereiden" button opens a `mailto:` link with name/message
- [ ] Quote save works from both a desktop-width and mobile-width admin session
- [ ] `/sitemap.xml` loads and lists current pages; `/robots.txt` now references it
- [ ] Flag to the user (not something to do yourself): confirm production `.env` has `APP_URL=https://mastechnics.be`, and run `php artisan db:seed --class=PageContentSeeder` (or a fresh reseed) against production to apply Task 7's copy to already-seeded rows

- [ ] **Step 6: No commit for this task** — it is verification-only. If Step 1 or Step 3 fails, fix the regression in the task that introduced it and re-commit there, not here.
