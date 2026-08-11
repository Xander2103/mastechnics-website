# HVAC v2 Cooling Load + Import Improvements Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** (1) Reverse-engineer the "Belgian Deterministic Air-Conditioning Calculator" workbook into an audited, versioned v2 cooling-load rule set; (2) make the HVAC import upload limit configurable (25 MB); (3) build a guided worksheet/header/column-mapping import workflow with reusable mapping profiles.

**Architecture:** Three independent workstreams in one sprint. v2 is a new *draft* `HvacRuleSet` (never auto-activated; v1 untouched) with an engineering-style load method dispatched inside `CoolingLoadCalculator` on `configuration['load_method']`. The import limit moves to `config/hvac.php` → `import.max_upload_mb`. The guided importer adds a safe native XLSX reader (ZipArchive + XML, no formula evaluation, macro files rejected), a cache-token wizard (sheet → header row → mapping → preview → confirm) and an `HvacMappingProfile` model; final row validation/writes reuse `HvacCsvImporter`.

**Tech Stack:** Laravel 12, PHP 8.2+, PHPUnit 11, no new composer packages (native ZipArchive/SimpleXML instead of PhpSpreadsheet — avoids ext-gd dependency and keeps the parse surface auditable).

## Global Constraints

- v1 rule set and historical calculation snapshots must remain byte-identical; v2 is a NEW rule set, created as `draft`, activated only by Martin via the existing activate flow.
- Do not silently mix v1 and v2 methods: one calculation runs entirely under one rule set's `load_method`.
- Workbook regression example must reproduce: design load ≈ 2572.229 W (tolerance ≤ 0.01 W) with all intermediates asserted.
- Excel `IFS` recommended-size cell is broken (#NAME?) — Laravel uses `CapacityClassSelector`, never a replicated IFS.
- Upload limit: `HVAC_IMPORT_MAX_MB` env, default 25; used by product AND compatibility import; UI shows "Maximale bestandsgrootte: 25 MB"; never disable MIME/extension/content validation.
- XLSX handling: reject macro-enabled workbooks, never execute/evaluate formulas (cached values only, flagged), zip-bomb guards, temp files cleaned up after processing.
- Mapping: never silently accept ambiguous mappings; required internal fields must be explicitly confirmed; profile mismatch → warning + back to mapping screen, never blind import.
- Windows/PowerShell only. All UI text nl (admin is Dutch-first, consistent with existing admin).
- Commit per logical unit; never push.

---

## Workbook facts (fully decoded — source of truth for Task 1 and 2)

Single visible sheet "AC Calculator", no defined names, no macros, no hidden sheets.
Inputs B3–B13; helper constants E3–E5; outputs B16–B30. Data validation lists:
- B7 Orientation: North, East, South, West
- B8 Shading: None, Internal blinds, External screen / shutter
- B9 Building quality: New, Recent insulated, Older insulated, Old uninsulated

Formulas (cell → formula):
- E3 qsol = IF(B7="North",120,IF(B7="East",230,IF(B7="South",280,300)))  [W/m²]
- E4 Fs = IF(B8="None",1,IF(B8="Internal blinds",0.75,0.35))
- E5 Ueq = IF(B9="New",0.35,IF(B9="Recent insulated",0.6,IF(B9="Older insulated",0.9,1.4)))  [W/m²K]
- B16 Area = B3*B4
- B17 Volume = B3*B4*B5
- B18 Envelope = 2*B5*(B3+B4)+(B3*B4)   (walls + ceiling, no floor)
- B19 Qtrans = E5*B18*8                  (fixed ΔT = 8 K)
- B20 Qsol = B6*E3*E4
- B21 PeopleSens = B10*75
- B22 PeopleLat = B10*55
- B23 QventSens = 2.67*B12*B17           (2.67 ≈ 0.334 Wh/m³K × 8 K)
- B24 QventLat = 1.3*B12*B17
- B25 Qs = B19+B20+B21+B11+B23
- B26 Ql = B22+B24
- B27 Qt = B25+B26
- B28 Design W = B27*B13
- B29 Design kW = B28/1000
- B30 = IFS(B29<=2.5,"2.5 kW / 9000 BTU",B29<=3.5,"3.5 kW / 12000 BTU",B29<=5,"5.0 kW / 18000 BTU",B29<=6.8,"6.8 kW / 24000 BTU",B29<=8,"8.0 kW",TRUE,"10.0 kW") → #NAME? because IFS is unavailable in the generating/viewing app (pre-2019 Excel / LibreOffice compat).

Example verification (6×5×2.6, win 5 m², West, Internal blinds, Recent insulated, 3 p, 250 W, ACH 0.5, SF 1.1):
Area 30, Vol 78, Env 87.2, Qtrans 418.56, Qsol 1125, Ps 225, Pl 165, Qvs 104.13, Qvl 50.7, Qs 2122.69, Ql 215.7, Qt 2338.39, Design 2572.229 W = 2.572229 kW. All reproduce exactly.

---

### Task 1: Excel calculator audit report

**Files:**
- Create: `docs/hvac/excel-calculator-audit.md`
- Keep: `docs/hvac/Belgian_AC_Quotation_Calculator.xlsx` (already copied as reference)

- [ ] **Step 1:** Write the full audit: workbook structure, every formula (cell, math form, variables, units, lookups, assumptions), constants table, validation lists, dependency graph, #NAME? root cause, v1-vs-Excel comparison table with classifications (Equivalent / More detailed in Excel / More detailed in Laravel / Conflict / Missing in X), recommended v2 architecture, form input gap analysis (classify: required from customer / optional / safely derived / admin-only assumption / rule-set default), validation example, migration plan.
- [ ] **Step 2:** Commit `docs(hvac): audit of Belgian AC calculator workbook + v2 migration plan`.

### Task 2: v2 rule set config + calculator + snapshot + admin breakdown + regression tests

**Files:**
- Modify: `config/hvac.php` (add `cooling_load_v2_rule_set`)
- Modify: `app/Services/Hvac/CoolingLoadCalculator.php` (method dispatch + `calculateEngineeringV2`)
- Modify: `app/Services/Hvac/HvacInputNormalizer.php` (only if needed — no v2-specific changes expected; window-area derivation lives in calculator)
- Create: `app/Console/Commands/SeedHvacV2RuleSet.php` (`hvac:seed-v2-rule-set`, creates DRAFT)
- Modify: `app/Services/Hvac/HvacRuleCatalog.php` (v2 rule entries so the validation dashboard covers them)
- Modify: `resources/views/admin/requests/partials/hvac-panel.blade.php` (v2 breakdown; v1 table kept for old snapshots via `method` key)
- Modify: `docs/hvac/rules-to-validate.md` (v2 values to validate)
- Test: `tests/Feature/Hvac/HvacCoolingLoadV2Test.php`

**Interfaces:**
- `CoolingLoadCalculator::calculateRoom(HvacRoomInput, array $rules): array` — unchanged signature. Returns v1 shape when `load_method` absent/`simple_v1`; v2 shape adds `'method' => 'engineering_v2'` plus keys: `area_m2, volume_m3, envelope_area_m2, u_equivalent, design_delta_t_k, q_transmission_w, window_area_m2, window_area_assumed, solar_gain_w_per_m2, shading, shading_assumed, shading_factor, q_solar_w, people_sensible_w, people_latent_w, equipment_heat_w, ach, ach_assumed, q_vent_sensible_w, q_vent_latent_w, q_sensible_total_w, q_latent_total_w, q_total_w, safety_factor, design_load_w, final_watts, final_kw` (final_* keep v1 names so ClassSelector/pipe/electrical/override/product-selection code is untouched).
- v2 config keys (all under `configuration`): `load_method`, `u_equivalent_by_insulation` (excellent .35, good .6, average .9, poor 1.4, other/average fallback), `design_delta_t_k` 8, `solar_gain_w_per_m2_by_orientation` (north 120, east 230, south 280, west 300, other/unknown 300), `shading_factors` (none 1.0, internal_blinds .75, external_screen .35), `assumed_shading` 'none', `window_area_ratio_by_window_type` (large .25, mixed .15, small .10, few_none .03, other/.10 fallback), `people_sensible_w_per_person` 75, `people_latent_w_per_person` 55, `ventilation_ach_default` 0.5, `ventilation_sensible_w_per_m3_ach` 2.67, `ventilation_latent_w_per_m3_ach` 1.3, `safety_factor` 1.1, `occupants_by_room_type` (reuse v1 map, all occupants count — no included_persons offset in v2) + everything else copied from v1 (capacity classes, pipe, electrical, accessories, labor, pricing, diversity).

- [ ] **Step 1:** Write failing regression test (workbook example, explicit `window_area_m2: 5`, insulation `good`→Ueq 0.6, orientation `west`, rules overridden to `shading` internal_blinds via room-level? No — shading comes from rules `assumed_shading`; test builds a v2 rules array with `assumed_shading => 'internal_blinds'` and equipment map `['workbook_equipment' => 250]`, room equipment `['workbook_equipment']`, occupants 3). Assert every intermediate above ±0.01 and `design_load_w` 2572.229 ±0.01, `final_kw` 2.572 (rounded 2dp) and class 3.5 via `CapacityClassSelector`.
- [ ] **Step 2:** Run test — expect failure (`method` key missing).
- [ ] **Step 3:** Implement v2 config block + calculator branch + seed command + rule catalog entries.
- [ ] **Step 4:** Run test — expect pass. Also `php artisan test tests/Feature/Hvac` green.
- [ ] **Step 5:** Admin panel: v2 per-room breakdown (Geometrie / Transmissie / Zonnewinst / Interne winsten / Ventilatie / Totalen groups, Dutch labels, assumption badges) rendered when `method === 'engineering_v2'`; v1 table untouched otherwise. Feature test asserts key labels appear for a v2 calculation and v1 view still renders.
- [ ] **Step 6:** Update `docs/hvac/rules-to-validate.md` with v2 placeholder values (window-area ratios, assumed shading, ACH, ΔT are engine assumptions Martin must validate).
- [ ] **Step 7:** Commit `feat(hvac): Belgian residential cooling load v2 rule set (draft) with engineering load model`.

### Task 3: Configurable import upload limit (25 MB)

**Files:**
- Modify: `config/hvac.php` (add `import.max_upload_mb`), `.env.example`
- Modify: `app/Http/Controllers/Admin/HvacImportController.php` (both `preview` and `compatPreview`: `max:` from config; friendly `uploaded` message)
- Modify: `bootstrap/app.php` (PostTooLargeException → redirect back to import page with Dutch error, admin-only, no server details)
- Modify: `resources/views/admin/hvac/imports/index.blade.php` (show "Maximale bestandsgrootte: X MB" on both forms)
- Create: `docs/hvac/import-deployment.md` (effective limit = min(Laravel, upload_max_filesize, post_max_size, client_max_body_size); recommended prod values PHP 30M/32M, nginx 32M; troubleshooting path)
- Test: `tests/Feature/Hvac/HvacImportLimitTest.php`

- [ ] **Step 1:** Failing tests: file under limit accepted (fake 5 MB csv passes validation), file over configured limit rejected with size message, config override respected (`config(['hvac.import.max_upload_mb' => 1])` → 2 MB file rejected; also asserts not hardcoded 4096), both endpoints share the limit, index view displays configured size.
- [ ] **Step 2:** Implement config + controller + view + exception handler + docs.
- [ ] **Step 3:** Tests green; commit `feat(hvac): configurable import upload limit (default 25 MB)`.

### Task 4: Guided mapping import workflow + profiles

**Files:**
- Create: `app/Services/Hvac/Import/XlsxWorkbookReader.php` (ZipArchive+SimpleXML; `sheets(): array{name,hidden}`, `rows(string $sheetName, int $maxRows, int $maxCols): array`; rejects macro workbooks (`vbaProject.bin`/`.xlsm` content-type), zip-bomb guards (entry count/uncompressed size caps), shared+inline strings, cached formula values with `has_formulas` flag, never evaluates)
- Create: `app/Services/Hvac/Import/TabularFileReader.php` (CSV/TXT delegate + XLSX delegate → uniform `{sheets, rows}`)
- Create: `app/Services/Hvac/Import/HeaderRowDetector.php` (scores first 20 rows: non-empty ratio, distinct strings, alias hits; returns best row index + confidence)
- Create: `app/Services/Hvac/Import/ColumnMappingSuggester.php` (alias map incl. Dutch supplier vocab: Artikelnummer→sku, Omschrijving→name, Koelvermogen→cooling_capacity_kw, Netto prijs→purchase_price_excl_vat, Voorraad→stock_quantity, Modelcode→model, Merk→brand, Leverancier→supplier, …; returns per-column `{field, confidence: exact|fuzzy|none}`; duplicate targets & fuzzy ⇒ needs confirmation, never auto-accepted)
- Create: `app/Models/HvacMappingProfile.php` + migration `hvac_mapping_profiles` (supplier_name, name, worksheet_name_pattern nullable, header_row, column_map json, decimal_format enum auto/comma/point, currency_format nullable string, is_active bool, timestamps; NO file contents)
- Create: `app/Http/Controllers/Admin/HvacGuidedImportController.php` (upload → sheet → header → mapping → preview(first 10 normalized + validation) → confirm; state via cache token; uploaded file stored in `storage/app/hvac-imports/` random name, deleted on confirm/cancel/expired-token access; profile select on upload; profile mismatch ⇒ warning + mapping screen)
- Modify: `app/Services/Hvac/HvacCsvImporter.php` (extract public `validateRows(array $assocRows, int $firstLine): array` reused by both `parse()` and the guided flow; `import()` unchanged)
- Create: views `resources/views/admin/hvac/imports/guided/*.blade.php` (sheet.blade.php, header.blade.php, mapping.blade.php, preview.blade.php)
- Modify: `resources/views/admin/hvac/imports/index.blade.php` (guided-import card + profile list), `routes/web.php`, `resources/views/admin/hvac/partials/nav.blade.php` if needed
- Modify: `HvacImportController::compatPreview` + `HvacCompatibilityCsvImporter` — accept xlsx via `TabularFileReader` (template headers, first sheet, detected header row) so csv/txt/xlsx all work there too
- Test: `tests/Feature/Hvac/HvacXlsxReaderTest.php` (fixtures built with ZipArchive in-test: multi-sheet, title rows, formulas, macro rejection, shared strings), `tests/Feature/Hvac/HvacGuidedImportTest.php` (full wizard walk-through, ambiguous mapping requires confirmation, required-field enforcement, profile save/reuse, profile mismatch warning, temp-file cleanup)

- [ ] **Step 1:** XLSX reader + tests (TDD: fixture builder first).
- [ ] **Step 2:** Header detection + mapping suggester + tests.
- [ ] **Step 3:** Profile model + migration.
- [ ] **Step 4:** Wizard controller + views + routes; `HvacCsvImporter::validateRows` refactor.
- [ ] **Step 5:** Compat importer xlsx acceptance.
- [ ] **Step 6:** Feature tests green; full suite + `npm run build` + `php -l` changed files.
- [ ] **Step 7:** Commits: `feat(hvac): safe native XLSX reader for supplier imports`, `feat(hvac): guided mapping import wizard with reusable supplier profiles`.

## Self-Review notes

- Excel spec fully covered by Tasks 1–2; upload spec by Task 3; mapping spec by Task 4.
- v2 keeps `final_watts`/`final_kw` names so `CapacityClassSelector`, overrides, product selection, quote conversion all keep working — verified against `HvacCalculationService::run` which only consumes `final_kw`.
- Recommended size: CapacityClassSelector after design load (spec §8), Excel IFS documented as broken, not replicated.
