# HVAC Supplier Import Wizard — UX + Robustness Sprint Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Turn the guided supplier import into a simple 5-step Dutch wizard (Bestand → Producten → Controle → Importeren → Resultaat) that robustly parses real supplier files (CatalogFR.csv: tab-delimited, cp1252, 50,991 rows) with automatic delimiter/header/mapping detection, category filtering, price semantics, and provenance — while never writing to the DB before explicit confirmation.

**Architecture:** Keep the existing token+cache wizard state machine and `HvacCsvImporter` validation/import core. Add pure services (`CsvDelimiterDetector`, `CategoryDetector`, extended `ColumnMappingSuggester`, extended `HvacGuidedImportService`) and restructure `HvacGuidedImportController` steps into: auto-analysis at upload → optional questions (delimiter/sheet/header only when genuinely ambiguous) → categories → review (mapping summary + uncertain questions + preview) → confirm → result. CSV reading becomes streaming (fgetcsv) so 50k-row files work within memory.

**Tech Stack:** Laravel 12, PHP 8.3, SQLite/MySQL, Blade, no new composer packages (native XLSX reader already exists).

## Global Constraints

- Admin UI language: simple Dutch. Never show: delimiter, mapping, normalization, schema, header detection (use: Kolommen herkennen, Gegevens koppelen, Producten kiezen, Controleren, Importeren). Technical terms allowed only inside "Geavanceerd".
- No DB product writes before the final confirm POST.
- No malformed supplier file may produce HTTP 500 — friendly Dutch errors, technical detail to `Log`.
- Never hard-delete `HvacProduct`; only additive migrations.
- Do not read `config/hvac.php` directly in services (rules via `HvacRuleSetResolver` — untouched here).
- Never infer indoor/outdoor compatibility from names; product type inference must be conservative and reviewable.
- Do not break: catalog, compatibility engine, classic CSV/XLSX template imports, recommendation engine, quote conversion, mapping profiles, admin auth.
- All commits local. Do not push, do not deploy.

## Reference facts (verified against the real file)

- `C:\Users\duisb\Downloads\CatalogFR.csv`: TAB-delimited, Windows-1252, no BOM, 50,991 data rows, 22 header cells (trailing tab → empty 22nd), comma decimals (`403,5`), space-padded `ProductID`, empty `RubricID` cells, `GroupName` has 408 distinct values (`Climatiseurs` = 123, `Accessoires et pièces détachées pour climatiseurs` = 126), `FamilyName` 36 values.
- Headers: ProductID, LabelFR, LabelNL, ProviderID, ProviderName, ProducerID, BrutPrice, DeliveryCode, DeliveryDelay, UpgradeDate, FamilyID, FamilyName, GroupID, GroupName, RubricID, RubricName, SequenceID, EAN, Intrastat, Weight, Piece.
- Semantics: ProductID = wholesaler SKU; ProducerID = manufacturer reference (→ model); ProviderName = brand-ish manufacturer name; LabelNL/LabelFR = names; BrutPrice = GROSS catalog price (must NOT auto-map to purchase or sale price).
- Current bug: `TabularFileReader::csvRows()` compares only `;` vs `,` counts on line 1 → picks `,` for a tab file → single giant column. `HvacCsvImporter::parse()` has the same weakness.
- Current row cap MAX_ROWS=20000 silently truncates CatalogFR (50,991 rows).

## File Structure

- Create: `app/Services/Hvac/Import/CsvDelimiterDetector.php` — delimiter detection + confidence
- Create: `app/Services/Hvac/Import/CategoryDetector.php` — category column detection + value counts
- Create: `app/Services/Hvac/Import/ImportAnalysis.php` — (plain value object array helpers optional; skip if arrays suffice)
- Modify: `app/Services/Hvac/Import/TabularFileReader.php` — streaming CSV, delimiter override, cp1252, BOM
- Modify: `app/Services/Hvac/Import/ColumnMappingSuggester.php` — CatalogFR aliases, `price` virtual field, no bruto→sale mapping
- Modify: `app/Services/Hvac/Import/HvacGuidedImportService.php` — derivations (supplier/user, name fallback, model←sku), category filter, price-semantics routing, provenance
- Modify: `app/Services/Hvac/HvacCsvImporter.php` — use detector in parse(); `import()` gains optional provenance context merged into product metadata
- Modify: `app/Http/Controllers/Admin/HvacGuidedImportController.php` — new step machine
- Modify: `app/Models/HvacMappingProfile.php` + additive migration — delimiter, category_filter, price_semantics, source_headers
- Views: rewrite `resources/views/admin/hvac/imports/index.blade.php`; new `guided/` views: `delimiter.blade.php`, `categories.blade.php`, `review.blade.php`, `confirm.blade.php`, `result.blade.php`, `partials/progress.blade.php`; keep `sheet.blade.php`/`header.blade.php` (reworded); delete `mapping.blade.php`+`preview.blade.php` from the normal flow (mapping UI folds into review's "Geavanceerd")
- Tests: `tests/Unit/Hvac/CsvDelimiterDetectorTest.php`, `tests/Unit/Hvac/CategoryDetectorTest.php`, `tests/Unit/Hvac/ColumnMappingSuggesterTest.php` (extend), `tests/Feature/Hvac/HvacGuidedImportTest.php` (rework), `tests/Feature/Hvac/HvacCatalogFrImportTest.php` (new end-to-end with fixture), fixture builder `tests/Support/CatalogFrFixture.php`

---

### Task 1: CsvDelimiterDetector + streaming TabularFileReader (root-cause fix)

**Files:** create detector + unit test; rewrite `csvRows` with `fgetcsv` on a stream (handles quoted delimiters and quoted newlines), explicit `?string $delimiter` param through `rows()`; BOM strip; cp1252 rescue kept. `HvacCsvImporter::parse()` uses the detector.

**Interfaces (produced):**
- `CsvDelimiterDetector::detect(string $sampleText): array{delimiter: string|null, confidence: 'high'|'ambiguous', candidates: array<string,int>}` — candidates `;` `,` `\t` `|`; parse up to 25 non-empty lines per candidate via `str_getcsv`; score = modal column count consistency; high confidence when best candidate yields ≥2 modal columns, ≥90% line consistency, and no rival within 1 column of its score; otherwise ambiguous. Ambiguous with zero viable candidates → `delimiter: null`.
- `TabularFileReader::rows(string $path, string $extension, ?string $sheet = null, int $maxRows = 20000, int $maxCols = 64, ?string $delimiter = null, ?callable $rowFilter = null): array{rows, has_formulas, truncated, delimiter: string|null, delimiter_confidence: string}` — `$rowFilter(array $cells, int $index): bool` lets callers stream-filter (used by category filter later) so only kept rows count against `$maxRows`.
- `TabularFileReader::detectDelimiter(string $path): array` (reads first ~64 KB, encodes to UTF-8 first).

Steps: failing unit tests (tab file → `\t` high; semicolon file; comma file; pipe file; quoted `"a;b";c`; quoted newline; single-column ambiguous file; `1,5;2,5` style comma-decimal+semicolon file → `;`), run red, implement, run green, `php artisan test` targeted, commit `fix(hvac): robust CSV delimiter detection (tab/pipe/quoted) with streaming reader`.

### Task 2: CatalogFR fixture + regression test for separate columns

**Files:** `tests/Support/CatalogFrFixture.php` — builds a sanitized tab-delimited cp1252 fixture string/file with the exact 22-column header and ~30 rows across GroupNames `Climatiseurs` (incl. rows inferable as indoor/outdoor + not-inferable), `Pompes vide-cave`, `Barres de douche`, with padded ProductIDs, comma decimals, é-characters, empty RubricID. Unit test: `TabularFileReader::rows()` on the fixture yields 22 separate header cells named exactly ProductID…Piece.

Commit: `test(hvac): CatalogFR.csv structural regression fixture`.

### Task 3: CategoryDetector service

**Interface:** `CategoryDetector::detect(array $headerCells, iterable $dataRows): array{column: int|null, alternatives: int[], values: array<string,int>}` plus `CategoryDetector::NAME_HINTS = ['groupname','group','groupe','familyname','famille','family','rubric','rubriek','categorie','category','soort']` and HVAC preselect helper `hvacLikely(string $value): bool` (keywords: clim, airco, air condition, split, warmtepomp, pompe à chaleur/pompes a chaleur, heat pump, ventilat, koeling, refriger).

Detection: candidate columns = text columns where distinct/nonEmpty ≤ 0.5, 2 ≤ distinct ≤ 500; score = name-hint bonus (+1000 exact hint match) + preference for moderate cardinality (closest to ~50 distinct wins ties); pick GroupName over FamilyName for CatalogFR (name hint `groupname` earlier in hint list → higher bonus order: group hints before family before rubric). Counting streams rows once via reader `rowFilter=null`.

Unit tests with the fixture: detects GroupName column index 13; counts `Climatiseurs` correctly; returns `column: null` for a file with no low-cardinality text column (all-unique names file).

Commit: `feat(hvac): generic category detection for supplier files`.

### Task 4: Suggester v2 — CatalogFR aliases + price virtual field

Changes to `ColumnMappingSuggester`:
- New virtual target `price` (unknown semantics). `ALIASES['price'] = ['brutprice','bruto prijs','brutoprijs','prix brut','catalogusprijs','prix catalogue','list price','tarif','prijs','price','prix']`. REMOVE bruto/catalogus aliases from `sale_price_excl_vat` (gross catalog price is not our sale price). Keep explicit netto aliases on purchase.
- New aliases: `sku`: +`productid`; `model`: +`producerid`,`reference fabricant`; `name`: +`labelnl`,`label nl`; new virtual `name_fallback` (secondary name): `labelfr`,`label fr`,`libelle fr`; `brand`: +`providername`,`provider`,`fabricant`,`merknaam`; `lead_time_days`: +`deliverydelay`,`delai livraison`; `product_type` hints stay; `ean` → new virtual `ean` (stored in metadata provenance, not a product column); `weight` → virtual `ignore` NOT auto-suggested (leave none).
- `suggest()` unchanged shape; add `public const VIRTUAL_FIELDS = ['price', 'name_fallback', 'ean']` and `targets(): array` returning `HvacCsvImporter::COLUMNS + VIRTUAL_FIELDS` for validation lists.
- CatalogFR expectation test: LabelNL→name exact, LabelFR→name_fallback exact, ProducerID→model (fuzzy or exact — assert suggested field), ProductID→sku, ProviderName→brand, BrutPrice→price (NEVER purchase/sale), DeliveryDelay→lead_time_days.

Commit: `feat(hvac): mapping suggestions for wholesaler catalogs; gross price never auto-maps to purchase/sale`.

### Task 5: Guided service v2 — derivations, category filter, price semantics, provenance

`HvacGuidedImportService::normalizeRows(array $rows, int $headerRow, array $columnMap, string $decimalFormat, array $options = []): array` where `$options = ['supplier' => ?string, 'price_meaning' => 'gross'|'net_purchase'|'sale'|'unknown'|null, 'category' => ['column' => int, 'selected' => string[]]|null, 'type_fallback' => ?string]`. Behavior:
- Category filter: skip rows whose trimmed category cell ∉ selected (when filter present).
- Virtual `name_fallback`: when `name` empty for a row, use fallback value; provenance `fields['name']='derived:LabelFR'`.
- Supplier: when no `supplier` column mapped, fill from `$options['supplier']`; provenance `manual`.
- Model←sku fallback: when no model column mapped or empty value, model = sku; provenance `derived`.
- Price routing by `price_meaning`: `net_purchase` → `purchase_price_excl_vat`; `sale` → `sale_price_excl_vat`; `gross`/`unknown` → NOT into price columns; raw value goes to provenance `price: {column_header, raw, meaning}` so the product is not treated as safely priced.
- product_type: when no type column mapped, infer conservatively per row: name/category contains (binnen|indoor|mural|wandunit|cassette) → indoor_unit; (buiten|outdoor|groupe ext) → outdoor_unit; (monosplit set|single split) → single_split_set; else `$options['type_fallback']` if set, else '' (row error "Kolom 'product_type' is verplicht." becomes visible "Type controleren"). Inference only fills the raw field — `HvacCsvImporter` still validates against `PRODUCT_TYPES`. Provenance `derived:GroupName` / `manual:fallback`.
- Return shape becomes `array<int, array{line: int, raw: array<string,string>, provenance: array}>` — `validateRows` ignores extra key (verify), provenance carried alongside by caller.

`HvacCsvImporter::import(array $rows, string $mode, array $context = [])` — `$context = ['source_file' => string, 'profile_id' => ?int, 'price_meaning' => ?string, 'supplier' => ?string, 'provenance_by_line' => array<int,array>]`; when present, merge `metadata['import'] = ['file' => ..., 'at' => now()->toIso8601String(), 'profile_id' => ..., 'price' => ..., 'fields' => ...]` into created/updated product metadata (preserving existing metadata keys, incl. notes).

Unit tests: category filter keeps only Climatiseurs lines; name falls back to FR; model falls back to sku; gross price never lands in purchase/sale; net_purchase lands in purchase; provenance array recorded; import() writes metadata.import and preserves notes.

Commit: `feat(hvac): derivation rules, category filtering, price semantics and provenance in guided import`.

### Task 6: Additive profile migration + auto-recognition

Migration `add_wizard_settings_to_hvac_mapping_profiles`: nullable `delimiter` string(8), `category_filter` json (['column_header' => string, 'selected' => string[]]), `price_semantics` json (['column_header' => string, 'meaning' => string]), `source_headers` json (normalized header list for recognition), `type_fallback` string nullable. Model casts + fillable.

Recognition: on upload, compute normalized headers of detected header row; candidate profiles = active profiles where all `source_headers` ⊆ file headers; pick the one with most matching headers; ties or zero → no profile. Applied profile pre-fills the whole state (delimiter/sheet/header/mapping/category/price) and jumps to review with banner "We herkennen dit bestand als een bestand van {supplier}. Vorige instellingen zijn toegepast."

Tests: profile round-trip (save on result step in Task 8 → re-upload same fixture → lands on review with settings applied); header mismatch → falls back to manual with warning (existing behavior retained).

Commit: `feat(hvac): mapping profiles store wizard settings and auto-recognize supplier files`.

### Task 7: Controller step machine v2 + step views (Bestand → Producten → Controle)

State keys: `step` ∈ sheet|delimiter|header|categories|review|confirm (+ result via flash), plus `delimiter`, `supplier_name`, `category` (column/header/selected/counts), `price_meaning`, `price_column`, `type_fallback`, `provenance`. Upload analysis pipeline (in `upload()`): store file → detect delimiter (csv) → sheets → pick sheet if single → detect header (high confidence → auto) → suggest mapping (exact suggestions auto-applied) → detect categories → profile recognition → set first unresolved step, order: delimiter? sheet? header? categories → review. Wizard progress partial shows `1 Bestand ✓ · 2 Producten · 3 Controle · 4 Importeren` (delimiter/sheet/header questions render inside step 1 chrome "Bestand controleren").

New POST endpoints: `chooseDelimiter` (auto|`;`|`,`|tab|`|`), `chooseCategories` (values[], all/none handled client-side; server validates values against detected counts; stores "n van N geselecteerd"), `saveReview` (uncertain answers: price_meaning required when a `price` column is mapped and profile didn't answer it; model column choice when ambiguous; type_fallback optional; plus optional full mapping override from "Geavanceerde koppelingen"). Categories step skipped (with state note) when no category column or when file is small and homogeneous (≤1 distinct value).

Views: `delimiter.blade.php` ("Hoe zijn de kolommen gescheiden?" with the 5 options, only reachable when ambiguous), `categories.blade.php` ("Welke producten wilt u importeren?" checkbox list with counts, search input filtering client-side via `<script>` include, Alles selecteren / Alles wissen, live "X van Y producten geselecteerd", HVAC-likely values pre-checked, but if none HVAC-likely: none pre-checked), `review.blade.php` (summary rows with ✓ Herkend / ! Controleren / — Niet beschikbaar; prominent question blocks only for unresolved items; "Geavanceerde koppelingen" `<details>` with the full per-column dropdown table; preview table `Importeren|Artikel|Product|Type|Prijs|Status` of 10 representative rows — first N ok + up to 3 problem rows; summary cards Geselecteerd/Klaar/Controleren/Geblokkeerd). No raw JSON anywhere.

Feature tests: tab fixture upload → skips delimiter+sheet (csv single sheet) + header auto → lands on categories listing Climatiseurs 5 (fixture counts); choose Climatiseurs → review shows only those rows and count cards; ambiguous delimiter file → delimiter question shown; review answers persist; advanced override works; validation errors return to form (no 500).

Commit: `feat(hvac): five-step guided import wizard (bestand, producten, controle)`.

### Task 8: Confirm + Result steps, friendly errors, profile save prompt

`confirm` GET-part of review flow: separate `confirm.blade.php` "Klaar om te importeren" with Leverancier / Producten / Nieuwe / Bijwerken / Overgeslagen / Met waarschuwing + [Importeren] + [Terug]; POST `confirm` runs import with provenance context, stores result + friendly per-row problem sentences ("Artikel {sku} kon niet worden geïmporteerd omdat {reden}") in flash, redirects to new GET `result` route (state token already forgotten; result data in flash with fallback redirect to index). `result.blade.php`: "Import voltooid" with ✓/!/— lines, buttons Producten bekijken (admin products index), Problemen bekijken (error report route), Nieuwe import; plus profile prompt form "Deze instellingen onthouden voor volgende bestanden van deze leverancier?" [Ja, onthouden] [Nee] → POST saves enriched profile (Task 6 fields) named "{supplier} — automatisch".

Wrap upload/step handlers: catch `XlsxReadException|Throwable` around analysis → `Log::warning` + friendly Dutch error, never 500.

Feature tests: DB row count unchanged until confirm POST (assert after review + confirm GET); confirm imports only filtered rows (Climatiseurs count); result page shows counts; profile prompt saves profile; malformed xlsx → validation error not 500; corrupt csv encoding → friendly message; huge-file truncation notice when >100k rows (cap raised for CSV streaming to 100000 with `rowFilter`).

Commit: `feat(hvac): confirm and result steps with provenance, friendly errors and profile memory`.

### Task 9: Index page rework + compat rename + responsive/copy sweep

`index.blade.php`: hero card "Productbestand importeren" (upload + optional Leverancier text input + "Bestand analyseren", supported-formats sentence, max size); collapsed `<details>` "Geavanceerd" containing: MAS-sjabloon import (existing form), compat import renamed "Compatibiliteit binnen- en buitenunits" with the exact explanation sentence, profile management table. Remove profile dropdown from the primary form (auto-recognition replaces it), remove decimal-format/worksheet-pattern fields from primary UI. Mode (create/update) moves into the confirm step defaulting to create_and_update? — NO: keep mode out of step 1; default `create_and_update`, expose choice on confirm screen as a simple radio "Bestaande producten ook bijwerken? (aanbevolen) / Enkel nieuwe toevoegen / Enkel bijwerken". Update all wizard copy to simple Dutch; ensure tables wrap in `.admin-table-wrapper` and cards stack on ≤1024px (inline styles consistent with existing admin CSS patterns).

Tests: index shows primary flow and hides technical fields outside Geavanceerd (assertSee/assertDontSee), existing template + compat imports still pass their suites.

Commit: `feat(hvac): simple import landing page; template and compatibility import under Geavanceerd`.

### Task 10: Existing-test migration, full verification, manual QA, docs

Rework `HvacGuidedImportTest` scenarios to the new step flow (upload no longer takes profile_id/mode; sheet/header steps only when needed). Run `php artisan test`, `npm run build`, `php -l` on changed files. Manual QA: copy real `CatalogFR.csv` into scratch, drive the wizard over HTTP against `php artisan serve` with a logged-in admin session (or via feature-test harness if interactive login impractical) and document each screen. Update `CLAUDE.md` sprint history + `docs/hvac/architecture.md` import section + memory files.

Commit: `test(hvac): migrate guided import tests to wizard v2; docs`.

---

## Sprint B (queued mid-sprint): catalog / import-batch hierarchy

User follow-up request: products must be organized per imported catalog (Productlijsten) instead of one flat table. Integrated into this plan because the wizard's confirm/result/provenance steps are where the entity plugs in.

### Task 11: Catalog entity — migrations + models

- `hvac_import_catalogs`: id, name, hvac_supplier_id FK nullable, source_filename, source_type (guided|template), imported_by, imported_at, product_count, status string default 'active' (active|archived|superseded|failed), notes nullable, hvac_mapping_profile_id FK nullable, checksum nullable, timestamps.
- `hvac_import_catalog_product` pivot: hvac_import_catalog_id FK, hvac_product_id FK, source_row nullable int, imported_at, source_price nullable decimal, source_stock nullable int, source_lead_time nullable int; unique (catalog_id, product_id).
- `hvac_import_runs`: id, hvac_import_catalog_id FK, created_count, updated_count, skipped_count, warning_count, deactivated_count default 0, imported_by, source_filename, timestamps — powers per-catalog import history.
- Models: `HvacImportCatalog` (belongsTo supplier/profile, belongsToMany products withPivot, hasMany runs, helper `activeProductCount()`, `needsReviewCount()`), pivot linkage on `HvacProduct::importCatalogs()`.
- Archive semantics: status change only; never auto-deactivates products (a product may live in another current catalog). Optional explicit "deactiveer producten die alleen in deze lijst zitten" action.
- Tests: creation, coexistence, product in multiple catalogs, FK safety (no cascade delete of products).

### Task 12: Wizard integration — catalog naming, new/update choice, runs

- Confirm step asks: "Naam van deze productlijst" (suggested from filename + supplier + detected year, e.g. "TestSupplier — CatalogFR 2026"), and "Nieuwe productlijst of bestaande lijst bijwerken?" (radio: Nieuwe productlijst / Bestaande productlijst bijwerken + select of active catalogs).
- Import (confirm POST) wraps `HvacCsvImporter::import` + catalog upsert + pivot sync (source_row/source_price from provenance) + `HvacImportRun` row in ONE transaction.
- Updating an existing catalog: preview shows nieuw/gewijzigd/ongewijzigd/ontbreekt-in-bestand counts; missing products NEVER auto-deleted; optional explicit checkbox "Ontbrekende producten inactief zetten" (only those not in another active catalog) → counted as deactivated in the run.
- Product metadata.import gains catalog_id; product detail shows admin-only "Bron" block (lijst, leverancier, laatste import, bronrij, bestandsnaam, profiel).
- Tests: import creates catalog+pivot+run; update run appends history; missing product preserved; deactivate flow only via explicit flag; historical references intact.

### Task 13: Catalog overview + detail UI

- `/admin/hvac/products` (existing products index) gets tabs: Productlijsten (default) | Alle producten | Merken | Leveranciers (merken/leveranciers = simple filter links into Alle producten). Overview shows catalog cards (name, supplier, product_count, active count, controleren count, imported_at, status) with actions Openen / Hernoemen / Archiveren / Importrapport; no product rows loaded by default; responsive cards (no horizontal scroll).
- `/admin/hvac/catalogs/{catalog}`: header (name, supplier, counts, imported), import history list, product table scoped to catalog with existing filters + product type/brand/active/missing-price/needs-review filters, pagination. Flat table stays available under "Alle producten".
- Rename/archive POST endpoints (admin middleware), archived catalogs stay readable and linked.
- Tests: overview counts, detail scoping, global view intact, unauthorized blocked, pagination, filters.

### Task 14 (absorbs old Task 10): full verification, manual QA, docs for both sprints

## Self-review notes

- Spec coverage: delimiter (T1), CatalogFR fixture (T2), categories (T3/T7), auto-mapping+price semantics (T4/T5/T7), derivations+provenance (T5), profiles invisible+memory (T6/T8), 5-step wizard+progress (T7/T8), result screen (T8), index/compat/template (T9), robustness/no-500 (T1/T8), row-cap fix (T1/T8), tests+QA+docs (T10).
- Deliberately out of scope (report as "cannot safely automate"): per-row manual type editing UI for thousands of rows (fallback type + review flag instead); automatic indoor/outdoor compatibility (never inferred); currency conversion; supplier auto-creation beyond name normalization.
