# HVAC Pre-Calculation & Recommendation System — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development or superpowers:executing-plans. The authoritative requirements are the 2026-08-06 HVAC spec (phases 1–21); this plan records the architecture decisions and wave breakdown.

**Goal:** Admin-assisted, deterministic HVAC pre-quotation for residential airco installations: normalize form answers → cooling load → capacity class → real-product selection → accessories/labor/pricing → admin-reviewed recommendations → conversion into the existing quote system. AI (optional, null by default) only explains.

**Architecture:** Three strictly separated layers. (A) Deterministic services in `app/Services/Hvac/` driven by a **versioned rule set** (`hvac_rule_sets`, seeded from `config/hvac.php`); every calculation stores an immutable input + rule snapshot. (B) Product catalog in `hvac_*` tables — facts only, admin-managed + CSV import, no hard deletes. (C) `HvacExplanationGeneratorInterface` with `NullHvacExplanationGenerator` default; strict output validation; full function without AI.

**Tech stack:** Laravel 12, PHP 8.2, SQLite (dev+prod — additive, SQLite-safe migrations), Blade admin (existing card/list classes), PHPUnit.

## Global constraints (from spec)
- Deterministic layer never reads prices/capacities from anywhere but the DB catalog; AI never writes into calculations.
- No invented manufacturer data; factories use fictional TestBrand products only; production catalog stays empty.
- Additive migrations, FKs, indexes; deactivate instead of delete; deleting a referenced product must throw.
- All admin routes behind existing `admin` middleware; purchase prices never customer-facing.
- Customer locale (nl/fr/en, fallback nl) drives customer-facing text; admin UI is NL.
- No pushes, no deploys, no real emails; logical commit per wave; tests after each wave.

## Field map (form → normalized)

| Source (metadata.answers) | Type | Normalized | Req | Fallback when missing |
|---|---|---|---|---|
| customer_requests.id / locale | int/string | customer_request_id, locale | R | — |
| rooms[].type | enum | room.type/name | R | blocker if absent |
| rooms[].width/length/height | numeric | width_m/length_m/height_m | R | blocker (legacy rooms without height → blocked with clear message) |
| rooms[].surface | numeric | area_m2 | R | recompute w×l |
| insulation_level (house) | enum | room.insulation | R | other/unknown → 110 W/m² + warning; absent → blocker |
| rooms[].orientation | enum | orientation | R | other/unknown → 1.00 + warning |
| rooms[].roof_type | enum | roof_type | O | neutral 1.00 factor; attic/flat → warning "roof correction not validated" |
| rooms[].windows | enum | window_type | O | factor map (large 1.18 … few_none 1.00); other/unknown → 1.05 + warning |
| airco_has_outdoor_unit | enum | has_existing_outdoor_unit | O | unknown + warning |
| airco_house_age | enum | house_older_than_10y | O | drives 6% VAT *suggestion* only |
| airco_installation_timing(+notes) | enum/text | timeframe, comments | O | — |
| customer_type | enum | customer_type | O | residential assumed w/ warning |
| attachments | rel | photos_count | O | — |
| — (not asked) | — | occupants | O | rule-set default per room type + warning |
| — (not asked) | — | tv/pc/open_kitchen | O | rule-set default per room type + warning |
| — (not asked) | — | pipe_length_m/bends/vertical_rise_m | O | rule-set install assumptions + warning |
| — (not asked) | — | electrical_supply | O | unknown → voltage filter skipped + warning |
| — (not asked) | — | drainage/preferred_brand/budget/noise/wifi prefs | O | unknown/none + warning where relevant |

No required form input is missing → no form changes this sprint; missing-optional list documented for a later form iteration.

## Database (11 additive migrations)
`hvac_brands`, `hvac_suppliers`, `hvac_products` (all spec columns + `wifi_included` bool, `sound_level_db`, unique (hvac_supplier_id, sku), indexes on product_type/is_active/cooling_capacity_kw), `hvac_product_compatibilities` (parent/compatible FK + type index), `hvac_rule_sets` (configuration JSON, status draft/active/archived), `hvac_calculations` (normalized_input JSON, result JSON incl. full rule snapshot, warnings JSON, status pending/calculated/blocked/superseded, FKs), `hvac_recommendations` (option_type budget/recommended/premium/single, status draft/approved/rejected/converted/superseded, money columns, explanation_{nl,fr,en}, converted_quote_id FK), `hvac_recommendation_items`, `hvac_manual_overrides`, `hvac_ai_logs` (provider/model/prompt_version/input_hash/output/validation_status).
Product model boot: `deleting` throws when referenced by items/compatibilities (+ tests). Only deactivate routes exist.

## Rule set v1 (config/hvac.php → seeded by HvacRuleSetResolver)
Exactly the spec defaults: insulation 70/90/110/140 (other/unknown 110+warn); ceiling h/2.5; orientation .95/1.00/1.08/1.12 (other/unknown 1.00+warn); window-type factors large 1.18, mixed 1.10, small 1.05, few_none 1.00, other/unknown 1.05+warn (window-ratio table also present for future window_area input); roof factors **all 1.00 + warning** (not invented — Martin must set); occupancy first incl., +120 W/person, defaults per room type (slaapkamer 2, woonkamer 3, bureau 1, keuken 2, zolderkamer 2, andere 2 — warn); equipment TV 100 / PC 150 / open kitchen 300, defaults per room type (woonkamer TV, bureau PC, keuken open-kitchen — warn); capacity classes ≤2.2→2.5, ≤3.2→3.5, ≤4.6→5.0, ≤6.3→6.0, else 7.1 or manual review; diversity 2:0.90 3:0.88 4:0.85 5+:0.82; pipe equiv = L + bends×1.0 + rise×0.5, generic threshold 15 m, install assumptions L 5 m / 4 bends / rise 2.5 m (warn); electrical 2.5→16A/3G2.5, 3.5→16A/3G2.5, 5.0→20A/3G2.5-of-3G4, 6.0→20A/3G2.5-of-3G4, 7.1→25A/3G4 (+ dedicated-circuit warning, manufacturer overrides); capacity match: product cooling ≥ room load and ≤ class×1.30 (validate); labor base 6 h, +3 h/extra indoor, 0.25 h/m pipe >5 m, pump 1 h, drilling 0.5 h/room, roof surcharge 2 h, rate €65/h, travel €35 (ALL placeholder — validate); pricing margin 35 % fallback when no sale price, material markup 25 %, VAT default 21 % with deterministic 6 %-suggestion when house >10 y + residential (manual confirm required).

## Services (app/Services/Hvac)
- `Input/HvacRequestInput`, `Input/HvacRoomInput`, `Input/HvacInputValidationResult` (readonly DTOs, toArray/fromArray for snapshots)
- `HvacInputNormalizer` — CustomerRequest → ValidationResult (input, warnings[], blockers[]); trims, casts, rejects impossible values, preserves source values
- `HvacRuleSetResolver` — active rule set or seed v1 from config
- `CoolingLoadCalculator` — per room, returns every intermediate factor
- `CapacityClassSelector` — target class or manual_review
- `PipeEstimator` + `ElectricalEstimator` — equivalent length, breaker/cable estimates, warnings
- `ProductSelector` — single-split (prefer real sets, else compat-linked pairs) & multi-split (unit count, per-model compat, connected-capacity range, pipe/height/electrical, active, stock/lead-time preference); no compat data → warning + manual review, never auto-valid
- `AccessorySelector`, `LaborEstimator`, `HvacPricingService`, `MarginCalculator`
- `HvacCalculationService` (orchestrator: normalize → calculate → store snapshot; recalc = new row, old superseded) and `HvacRecommendationBuilder` (budget/recommended/premium only when genuinely distinct valid systems)
- `HvacManualOverrideService` (reason + admin + old/new, applied on top, original kept)
- `HvacQuoteConversionService` (transaction; blocks when a quote exists; quote items from recommendation items incl. SKU/model in description; VAT preserved; locale from request; status draft; no mail)
- `Explanation/HvacExplanationGeneratorInterface`, `Explanation/NullHvacExplanationGenerator`, `Explanation/AiExplanationValidator` (+ `hvac_ai_logs`)

## Admin
Routes (admin group): `hvac/products` CRUD-less-delete + duplicate + deactivate, `hvac/brands`, `hvac/suppliers`, `hvac/compatibilities` (managed on product edit), `hvac/import` (form/preview/confirm/template/error-report), request-detail actions: `requests/{r}/hvac/calculate`, `.../hvac/recommendations/{rec}/approve|reject|convert`, `.../hvac/overrides`, `.../hvac/warnings/ack`. Panel partial `admin/requests/partials/hvac-panel.blade.php` — only for `airco_offerte`; room cards, factor table, product cards, materials, pricing incl. margin (admin-only), warnings block, audit block, disclaimer text on every result: "Automatische voorcalculatie. Controleer vermogen, compatibiliteit, leidinglengtes, elektrische vereisten en plaatsingssituatie vóór verzending." Nav gains "HVAC-producten".

## CSV import
Steps upload→parse→validate→preview (session-cached parsed rows keyed by hash)→confirm→DB transaction→report; identify by supplier+SKU; explicit create/update mode; row errors downloadable; formula-injection guard (leading = + - @ tab), UTF-8 validation, decimal comma+point accepted; template at `docs/hvac/hvac-products-template.csv` + download route.

## Waves & commits (tests every wave; fictional TestBrand data only)
1. **Foundation** — config, 11 migrations, 10 models, DTOs, normalizer, resolver, calculators, pipe/electrical estimators; unit tests. Commit.
2. **Catalog** — brands/suppliers/products admin + nav, compatibility mgmt, CSV import, ProductSelector; selection + import tests. Commit (2 logical commits if large).
3. **Recommendation** — accessories, labor, pricing, margin, builder, overrides; unit tests. Commit.
4. **Admin panel** — calculation controller + HVAC panel UI + approval; feature tests (auth, no-raw-JSON, warnings, snapshot immutability, recalculation). Commit.
5. **Quote conversion** — conversion service + route + protection tests + locale. Commit.
6. **AI boundary + docs** — interface, null provider, validator, logging, failure-safety tests; docs (`docs/hvac/architecture.md`, `docs/hvac/rules-to-validate.md`, CSV template); CLAUDE.md sprint record. Commit.

Self-review: every spec phase 2–21 maps to a wave above; no form redesign needed (phase 1 field map complete); supplier-data gap handled per spec (architecture + catalog + import complete, production catalog empty, required CSV documented).
