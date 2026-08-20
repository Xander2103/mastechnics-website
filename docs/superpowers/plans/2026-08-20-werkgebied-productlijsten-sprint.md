# Werkgebied Removal + Productlijsten Completion Sprint — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Remove the visible "Werkgebied" homepage section and footer column without harming local SEO, and close the five audited gaps in the HVAC Productlijsten hierarchy, then run E2E acceptance.

**Architecture:** Part A is pure Blade/label/CSS removal — the SEO layer (SeoService, sitemap, schema, breadcrumbs, location pages) is data-driven from `config/site.php` + DB pages and is untouched. One natural footer sentence keeps the service-area hub linked site-wide. Part B extends existing models/controllers/views (`HvacSupplier`, `HvacProductController::catalogOverview`, guided import confirm) — no new subsystems.

**Tech Stack:** Laravel 12, Blade, PHPUnit (`php artisan test`), Vite (`npm run build`), PowerShell shell.

## Global Constraints

- Multilingual nl / fr / en for every user-facing label; fallback `nl`.
- Never break: CustomerRequest storage, uploads, request flow routes, admin dashboard, mail, existing routes in `routes/web.php`.
- SEO only via `App\Services\SeoService`; one `ld+json` per page (`SeoStructureTest`).
- Never hard-delete `HvacProduct`; rules only via `HvacRuleSetResolver`; TestBrand-only fixtures.
- Admin UI language: simple Dutch (Productlijsten, Importgeschiedenis, Archiveren…), no DB jargon.
- Do not push. Do not deploy. Commit per task with descriptive messages.
- Baseline: 597 tests passing before this sprint.

---

### Task 1: Remove homepage Werkgebied section (A1)

**Files:**
- Modify: `resources/views/pages/partials/home-page.blade.php` (delete lines 521–546 section incl. leading comment; delete label keys nl 107–110, fr 206–209, en 305–308; delete `$serviceAreas` 319–321)
- Modify: `resources/css/pages/location.css:249-259` (delete `.home-area-links` / `.section-werkgebied` rules)
- Test: existing `tests/Feature/HomepageTest.php`, `tests/Feature/SeoStructureTest.php`

**Interfaces:** Produces nothing; keeps `services_all` label and services-hub link (515–517) intact.

- [ ] **Step 1:** Delete the section block:

```blade
{{-- ═══════════ (comment lines 521–524) ═══════════ --}}
<section class="section section-white section-werkgebied" id="werkgebied">
    ... (through closing </section> at 546)
```

Delete label keys `areas_label`, `areas_title`, `areas_intro`, `areas_all` in all three locale arrays (keep `services_all`). Delete the `$serviceAreas` collect block (319–321).

- [ ] **Step 2:** Delete `location.css` 249–259 (`.home-area-links`, `.section-werkgebied .services-hub-areas-link`). Verify no other `.section-werkgebied` / `#werkgebied` references remain (`grep werkgebied resources/`), except page slugs config.
- [ ] **Step 3:** Run `php artisan test --filter=HomepageTest; php artisan test --filter=SeoStructureTest` → expect PASS.
- [ ] **Step 4:** Commit `feat(seo): remove visible homepage werkgebied section, keep local SEO architecture`.

### Task 2: Footer cleanup + natural area sentence (A2 + A3)

**Files:**
- Modify: `resources/views/layouts/app.blade.php` (labels ~19/33/46, `$footerAreas` 73–76, footer column 410–438)
- Modify: footer CSS file that styles `.footer-list` (add `.footer-area-note`)
- Test: existing `tests/Feature/HomepageTest.php` (footer assertions 141–171)

**Interfaces:** Consumes existing `$serviceNav` (built ~line 51) and `$seoService->pageUrl/pageLabel`. Produces `.footer-area-note` link to the service-area hub — the sole surviving site-wide entry into the location cluster.

- [ ] **Step 1:** Replace `all_areas` label with `area_note` in all three locale arrays:

```php
// nl
'area_note' => 'Actief in de Druivenstreek en omliggende gemeenten.',
// fr
'area_note' => 'Actifs dans le Druivenstreek et les communes environnantes.',
// en
'area_note' => 'Active in the Druivenstreek and surrounding municipalities.',
```

Delete the `$footerAreas` block (73–76).

- [ ] **Step 2:** Replace the footer Werkgebied column (413–438) with a services column + subtle sentence:

```blade
{{-- Services links use the freed column; one natural sentence keeps the
     service-area hub reachable site-wide without a municipality link grid. --}}
<div>
    <h3>{{ $seoService->pageLabel('services', $currentLocale) }}</h3>

    <ul class="footer-list">
        @foreach ($serviceNav as $serviceItem)
            <li><a href="{{ $serviceItem['url'] }}">{{ $serviceItem['label'] }}</a></li>
        @endforeach
        <li>
            <a href="{{ $seoService->pageUrl('services', $currentLocale) }}">
                {{ $seoService->pageLabel('services', $currentLocale) }}
            </a>
        </li>
    </ul>

    <p class="footer-area-note">
        <a href="{{ $seoService->pageUrl('service_area', $currentLocale) }}">{{ $nav['area_note'] }}</a>
    </p>
</div>
```

(Adapt `$serviceItem` keys to the actual `$serviceNav` shape at line 51–60.)

- [ ] **Step 3:** Add CSS next to existing footer-list rules:

```css
.footer-area-note { margin-top: 0.9rem; font-size: 0.85rem; opacity: 0.75; }
.footer-area-note a { color: inherit; text-decoration: none; }
.footer-area-note a:hover { text-decoration: underline; }
```

- [ ] **Step 4:** Run `php artisan test --filter=HomepageTest` → PASS. Commit `feat(seo): replace footer werkgebied column with services links and natural area sentence`.

### Task 3: Location-page SEO audit (A4) — verification only, no commit

- [ ] Run `php artisan test --filter=SeoStructureTest; php artisan test --filter=SitemapTest; php artisan test --filter=ServicePageTest` → all PASS (covers: every sitemap URL 200s, self-canonical, hreflang reciprocity, 6 location pages non-template, FAQ schema).
- [ ] Grep public views for `noindex` — must only appear for admin context.
- [ ] Record in final report: hub reachable via footer sentence + breadcrumbs + service-page area blocks (kept as contextual links); municipalities reachable via hub grid + sibling links + service pages + sitemap.

### Task 4: Supplier view shows catalogs (B11)

**Files:**
- Modify: `app/Models/HvacSupplier.php` (add relation)
- Modify: `app/Http/Controllers/Admin/HvacSupplierController.php:13-18`
- Modify: `resources/views/admin/hvac/suppliers/index.blade.php` (tabs include + new columns + per-supplier catalog list)
- Modify: `resources/views/admin/hvac/brands/index.blade.php` (tabs include only)
- Test: Create `tests/Feature/Hvac/HvacSupplierViewTest.php`

**Interfaces:** Produces `HvacSupplier::catalogs(): HasMany` (→ `HvacImportCatalog`, FK `hvac_supplier_id`). View shows per supplier: active catalog count, active product count, last import date, and its catalogs (name, product_count, status, Openen link).

- [ ] **Step 1:** Write failing test:

```php
<?php

namespace Tests\Feature\Hvac;

use App\Models\HvacImportCatalog;
use App\Models\HvacSupplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HvacSupplierViewTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): array { /* reuse login helper pattern from HvacCatalogUiTest */ }

    public function test_supplier_index_lists_catalogs_with_counts_and_last_import(): void
    {
        $supplier = HvacSupplier::create(['name' => 'Cairox', 'is_active' => true]);
        $old = HvacImportCatalog::create(['name' => 'Cairox HVAC 2026', 'hvac_supplier_id' => $supplier->id, 'product_count' => 86, 'status' => 'active', 'imported_at' => now()->subDays(9)]);
        HvacImportCatalog::create(['name' => 'Cairox HVAC 2027', 'hvac_supplier_id' => $supplier->id, 'product_count' => 90, 'status' => 'active', 'imported_at' => now()->subDay()]);

        $response = $this->actingAsAdmin()->get(route('admin.hvac.suppliers.index'));

        $response->assertOk()
            ->assertSee('Cairox HVAC 2026')
            ->assertSee('Cairox HVAC 2027')
            ->assertSee(route('admin.hvac.catalogs.show', $old), false);
    }

    public function test_supplier_index_requires_admin(): void
    {
        $this->get(route('admin.hvac.suppliers.index'))->assertRedirect();
    }
}
```

(Match the real admin-login helper + catalog `create` fillable from `HvacCatalogUiTest` when writing.)

- [ ] **Step 2:** Run → FAIL (catalog names absent).
- [ ] **Step 3:** Implement: add to `HvacSupplier`:

```php
public function catalogs(): HasMany
{
    return $this->hasMany(HvacImportCatalog::class);
}
```

Controller index:

```php
'suppliers' => HvacSupplier::query()
    ->withCount([
        'products',
        'products as active_products_count' => fn ($q) => $q->where('is_active', true),
        'catalogs as active_catalogs_count' => fn ($q) => $q->where('status', '!=', HvacImportCatalog::STATUS_ARCHIVED),
    ])
    ->with(['catalogs' => fn ($q) => $q->orderByDesc('imported_at')])
    ->orderBy('name')->get(),
```

View: `@include('admin.hvac.partials.catalog-tabs', ['activeTab' => 'suppliers'])` after the nav include; columns become Naam / Productlijsten / Producten (actief) / Laatste import / Status / toggle; under each supplier row render its catalogs as an indented sub-list (`Naam — N producten — status — [Openen]`). Same tabs include (`'brands'`) in brands view.

- [ ] **Step 4:** Run new test + `php artisan test --filter=Hvac` → PASS. Commit `feat(hvac): supplier view shows productlijsten per leverancier`.

### Task 5: Archived filter on Productlijsten overview (B10)

**Files:**
- Modify: `app/Http/Controllers/Admin/HvacProductController.php:107-120` (`catalogOverview`)
- Modify: `resources/views/admin/hvac/catalogs/index.blade.php`
- Test: extend `tests/Feature/Hvac/HvacCatalogUiTest.php`

**Interfaces:** Query param `lists` ∈ `active` (default) | `archived` | `all` on `admin.hvac.products.index`.

- [ ] **Step 1:** Failing test: archived catalog is NOT on default overview, IS on `?view=lists&lists=archived`, count badge visible.
- [ ] **Step 2:** `catalogOverview(Request $request)`: add

```php
$filter = in_array($request->string('lists')->toString(), ['archived', 'all'], true)
    ? $request->string('lists')->toString() : 'active';
// base query as today, plus:
->when($filter === 'active', fn ($q) => $q->where('status', '!=', HvacImportCatalog::STATUS_ARCHIVED))
->when($filter === 'archived', fn ($q) => $q->where('status', HvacImportCatalog::STATUS_ARCHIVED))
```

Pass `['catalogs' => $catalogs, 'listsFilter' => $filter, 'archivedCount' => ..., 'activeCount' => ...]`. View: pill links `Actief (n) / Gearchiveerd (n) / Alle` above the grid; empty-state text for archived tab: `Geen gearchiveerde productlijsten.`

- [ ] **Step 3:** Check existing `HvacCatalogUiTest` archive-visibility assertions; update any that assumed archived inline on default view to use `lists=archived`.
- [ ] **Step 4:** Run `php artisan test --filter=HvacCatalogUiTest` → PASS. Commit `feat(hvac): archived productlijsten behind separate filter tab`.

### Task 6: Duplicate-submit safety on guided import confirm (B15)

**Files:**
- Modify: `app/Http/Controllers/Admin/HvacGuidedImportController.php:505-613` (`confirm`)
- Modify: `resources/views/admin/hvac/imports/guided/confirm.blade.php` (submit button ~L92)
- Test: extend `tests/Feature/Hvac/HvacGuidedImportTest.php` or `HvacCatalogFrImportTest.php`

**Interfaces:** Consumes existing state machine (`stateAndPath`, step `confirm`→`done`, result page route `admin.hvac.import.guided.result`).

- [ ] **Step 1:** Failing test: run a full guided import to confirm; POST confirm a second time with same token → assert redirect to result page (not the "verlopen" error), `HvacImportCatalog::count() === 1`, `HvacImportRun::count() === 1`.
- [ ] **Step 2:** Implement in `confirm()`:

```php
[$state, $path] = $this->stateAndPath($token);
// A completed import re-POSTed (double-click / back button) must land on the
// result page, not wipe state via expired().
if ($state !== null && $state['step'] === 'done') {
    return redirect()->route('admin.hvac.import.guided.result', $token);
}
if ($state === null || $state['step'] !== 'confirm') {
    return $this->expired($token);
}

$lock = Cache::lock("hvac-guided-confirm:{$token}", 60);
if (! $lock->get()) {
    return redirect()->route('admin.hvac.import.guided.result', $token);
}
try {
    // existing body (validate → transaction → run log → state 'done')
} finally {
    $lock->release();
}
```

Concurrent loser lands on the result route; the result page already renders from state, and by the time the winner finishes state is `done`. If state is not yet `done` when the loser arrives, the result action's own guard handles it — verify that guard redirects rather than erroring, adjust if needed.

Button: `<button ... onclick="this.disabled=true;this.form.requestSubmit();">` — or a small inline `form.addEventListener('submit', ...)` disabling the button; no framework.

- [ ] **Step 3:** Run new test + `php artisan test --filter=HvacGuidedImport` → PASS. Commit `fix(hvac): guided import confirm is idempotent — double submit lands on result page`.

### Task 7: Mobile data-labels for HVAC tables (B13)

**Files:**
- Modify: `resources/views/admin/hvac/catalogs/show.blade.php:157-176` (add `data-label` to each `<td>`)
- Modify: `resources/views/admin/hvac/suppliers/index.blade.php` (same, matches Task 4 columns)
- Test: extend `tests/Feature/Hvac/HvacCatalogUiTest.php`

- [ ] **Step 1:** Failing test: catalog detail response contains `data-label="Koelvermogen"`.
- [ ] **Step 2:** Add `data-label` matching each header, e.g.:

```blade
<td data-label="SKU"><a class="admin-link" ...>{{ $product->sku }}</a></td>
<td data-label="Model">{{ $product->model }}</td>
<td data-label="Merk">{{ $product->brand?->name }}</td>
<td data-label="Type">{{ $product->product_type }}</td>
<td data-label="Koelvermogen">...</td>
<td data-label="Verkoopprijs">...</td>
<td data-label="Voorraad">...</td>
<td data-label="Levertijd">...</td>
<td data-label="Status">...</td>
```

(Existing CSS `resources/css/pages/admin.css:1147` renders these via `td::before` under 680px — no CSS change needed.)

- [ ] **Step 3:** Run test → PASS. Commit `fix(hvac): mobile card labels on catalog and supplier tables`.

### Task 8: Catalogs index route + Importprofiel label (B2/B8 polish)

**Files:**
- Modify: `routes/web.php:124-131` (add GET `/hvac/catalogs`)
- Modify: `app/Http/Controllers/Admin/HvacCatalogController.php` (add `index` delegating to the same overview logic — move `catalogOverview` here as `public function index(Request $request)`, keep `HvacProductController` delegating to it)
- Modify: `resources/views/admin/hvac/products/form.blade.php:279` (label `Leveranciersinstellingen` → `Importprofiel`)
- Test: extend `HvacCatalogUiTest`

- [ ] **Step 1:** Failing test: `GET route('admin.hvac.catalogs.index')` → 200, sees a created catalog name; provenance section shows `Importprofiel`.
- [ ] **Step 2:** Add route `Route::get('/hvac/catalogs', [HvacCatalogController::class, 'index'])->name('hvac.catalogs.index');` before the `{catalog}` route. Move overview logic; `HvacProductController::index` calls `app(HvacCatalogController::class)->index($request)` (or shared private service method — pick the smallest diff that keeps both entry points passing existing default-view tests).
- [ ] **Step 3:** Run `php artisan test --filter=HvacCatalog` → PASS. Commit `feat(hvac): dedicated productlijsten route + Importprofiel label`.

### Task 9: E2E acceptance gap check (Part C)

**Files:**
- Inspect: `tests/Feature/Hvac/HvacE2eAcceptanceTest.php` (scenarios A–H already cover C1, C2, and most of C3/C4)
- Possibly add tests there for the C3 items without coverage: **unknown electrical supply** and **unvalidated/draft rule set** (verify actual engine behavior first via `CoolingLoadCalculator` / `HvacRuleSetResolver` / recommendation warnings; assert manual-review or warning, never silent success). Also verify a test asserts **purchase price + margin absent from customer quote PDF** (`test_quote_pdf_follows_customer_locale` vicinity) — add assertion if missing.

- [ ] **Step 1:** Read scenarios A–H + hardening tests; map to C1–C4 checklist; list uncovered items.
- [ ] **Step 2:** For each uncovered item, write a test asserting the existing blocking/warning behavior (do not change engine semantics; if behavior is genuinely silent-continue, that is a finding for the report and a minimal fix: surface a warning in the engine-warnings list following the Sprint 16 pattern in `test_needs_review_and_three_phase_products_warn_in_the_panel`).
- [ ] **Step 3:** Run `php artisan test --filter=HvacE2eAcceptanceTest` → PASS. Commit `test(hvac): close C3 acceptance gaps (electrical supply, draft rule set, pdf confidentiality)`.

### Task 10: Client manual + docs (C5)

**Files:**
- Modify: `docs/hvac/klantenhandleiding-mastechnics.md` — ensure the Productlijsten chapter matches actual behavior incl. new bits: Leveranciers tab shows lijsten per leverancier, gearchiveerde lijsten under separate filter, dubbelklik-veilig importeren; navigation story: HVAC-producten → Productlijsten → lijst openen → producten bekijken → lijst bijwerken → importhistoriek → archiveren.
- Modify: same file (or a short section in it): note that gemeente-/werkgebiedpagina's still exist for SEO (sitemap, Google) even though the visible homepage/footer blocks are gone; they are reachable via the footer sentence and breadcrumbs.

- [ ] **Step 1:** Read the manual, update in simple Dutch, no jargon.
- [ ] **Step 2:** Commit `docs: update klantenhandleiding — productlijsten navigation + werkgebied SEO note`.

### Task 11: Final verification

- [ ] `php artisan test` (full) → expect ≥597 passing, 0 failures.
- [ ] `npm run build` → success.
- [ ] `php -l` on every changed PHP file.
- [ ] `git status` clean after commits; `git log --oneline` shows task commits.
- [ ] Write final report (18 required sections from the sprint spec), incl. explicit statement that production readiness still blocked on real supplier data, manufacturer compatibility, and Martin's rule validation.

## Self-Review Notes

- Spec coverage: A1→T1, A2/A3→T2, A4→T3, B1 audit done pre-plan, B2–B4/B8/B9 already complete (audit evidence) with polish in T7/T8, B5–B7 complete, B10→T5, B11→T4, B12 complete, B13→T7, B14 respected throughout, B15→T6, B16 largely existing + new tests in T4–T8, C1–C4→T9, C5→T10.
- Deliberately NOT removed (contextual internal links, per A3 "keep internal linking where contextually relevant"): services-hub area block, per-service area blocks, contact-page text line. Recorded for the final report; trivial to remove later if Martin wants.
- Existing tests asserting default Productlijsten view (`HvacCatalogUiTest:44`) must keep passing after T5/T8 refactors.
