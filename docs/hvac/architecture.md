# HVAC Pre-Calculation & Recommendation System

Admin-assisted pre-quotation for residential airco installations. **This is a
sales-assistance tool, not certified HVAC engineering software** — every result
carries the disclaimer: *"Automatische voorcalculatie. Controleer vermogen,
compatibiliteit, leidinglengtes, elektrische vereisten en plaatsingssituatie
vóór verzending."*

## Architecture: three strictly separated layers

1. **Deterministic logic** — `app/Services/Hvac/`. Same input + same rule set →
   same output, always. Calculates load, capacity class, diversity, pipe
   equivalents, electrical estimates, accessories, labor, pricing, margin, VAT.
2. **Product database** — `hvac_*` tables. Facts only: brand, model, SKU,
   capacity, price, stock, lead time, technical limits, compatibility.
   Managed via the admin catalog and CSV import. Products referenced by
   history can only be deactivated, never deleted (app guard + DB restrict FK).
3. **AI layer** — `app/Services/Hvac/Explanation/`. Optional; default is the
   null provider. AI may only *explain* already-validated results; output is
   strictly validated (locale, known product IDs only, no HTML, no unexpected
   fields such as prices) and fully logged in `hvac_ai_logs`. The system is
   100 % functional without AI.

## Calculation pipeline

```
CustomerRequest (airco_offerte)
  → HvacInputNormalizer      critical data missing → BLOCKED (never guessed)
  → CoolingLoadCalculator    per room, every factor stored
  → CapacityClassSelector    target class or manual review
  → PipeEstimator / ElectricalEstimator
  → HvacCalculationService   persists immutable snapshot (input + rule set)
  → ProductSelector          catalog facts + explicit compatibility only
  → AccessorySelector / LaborEstimator / HvacPricingService / MarginCalculator
  → HvacRecommendationBuilder  Budget/Aanbevolen/Premium from VALID candidates
  → admin review (approve / reject / override / acknowledge warnings)
  → HvacQuoteConversionService → existing Quote system (draft, manual send)
```

### Formulas (rule set v1 — see rules-to-validate.md)

- `base_watts = area_m2 × insulation_W/m² × (height/2.5) × orientation × window × roof`
- `final_watts = base_watts + occupancy(+120 W/extra person) + equipment(TV 100/PC 150/open kitchen 300)`
- Capacity classes: ≤2.2→2.5, ≤3.2→3.5, ≤4.6→5.0, ≤6.3→6.0, above→7.1 kW or manual review
- Multi-split: `Σ(indoor classes) × diversity (2:0.90 / 3:0.88 / 4:0.85 / 5+:0.82)`
- Pipe equivalent: `L + bends×1.0 + rise×0.5` (warn > 15 m; product limits override)
- Electrical: class → breaker/cable table; manufacturer data overrides; always "verify" warning

### Rule versioning

Rules live in `hvac_rule_sets` (status draft/active/archived), seeded once from
`config/hvac.php` by `HvacRuleSetResolver`. Every calculation stores the **full
rule configuration snapshot** in its result JSON — changing rules later never
changes historical calculations (tested). New rule versions = new row, activate
by status.

### Input snapshot & assumptions

`hvac_calculations.normalized_input` holds the complete immutable input.
Values the form doesn't ask (occupants, equipment, pipe runs, electrical
supply, drainage) come from rule-set assumptions and are **always flagged**
(`*_assumed` + warning). Critical values (dimensions incl. height, insulation)
block the calculation when missing.

## Product selection rules

- Single split: real `single_split_set` products, or indoor+outdoor pairs
  joined **only** via `hvac_product_compatibilities` (`indoor_outdoor`).
  Similar kW values never pair units.
- Multi split: `multi_split_outdoor` must explicitly support every distinct
  indoor model (`multi_split_indoor` rows), the connected-capacity window,
  unit count, pipe totals/per-unit, height difference.
- Unknown limits or missing compatibility → candidate invalid → manual review;
  never auto-valid. Missing prices → no approvable recommendation.
- Preference order: valid > in stock > shortest lead time > price.

## Overrides (post-audit remediation)

Beyond item quantity/price, VAT and catalog-only product changes, the admin
can override a **room's calculated load** (watts → class re-derived from the
same rule snapshot; original values stay in the snapshot; `system_with_overrides`
is stored next to the untouched `system`; recommendations are rebuilt on the
overridden class) and add **discount lines** (mandatory reason, capped at the
subtotal, server-side recompute). AI explanations are generated automatically
after each build (null provider → no-op), deduplicated on input hash, and
displayed in the panel once validated.

## Admin workflow

`/admin/requests/{id}` → panel "Automatische airco-voorcalculatie"
(airco_offerte only): run/recalculate (cache-locked), room cards, factor
table, warnings, per-option items with purchase price + margin (admin-only),
labor detail, audit trail. Actions: acknowledge warnings (reason required,
manual_review→draft), approve (once), reject, VAT override (6/21 + reason),
item overrides and catalog-only product change — every override stores
old/new value, reason, admin, timestamp in `hvac_manual_overrides`.

Catalog: `/admin/hvac/products|brands|suppliers|import` (nav "HVAC-producten").

## CSV import

Upload → parse → validate → preview → confirm → **one transaction** → report.
Identification: supplier + SKU; modes create/update/both; UTF-8 (with
Windows-1252 rescue), comma/point decimals, formula-injection rejection,
row-level errors + downloadable error report. Template:
`docs/hvac/hvac-products-template.csv` or `/admin/hvac/import/template`.

## Quote conversion

Approved recommendations only. Existing quote → hard block (never silent
replace). Items preserve SKU/description/quantity/price/VAT; quote title uses
the **customer request locale** (nl/fr/en, fallback nl); purchase prices and
margin never reach the quote; result is a draft — Martin reviews the quote +
PDF and sends via the existing explicit mail flow. **No automatic email.**

## Security

Admin middleware on all routes; cross-request scope guards (404 on foreign
recommendations/items); catalog-only product choices; mandatory reasons on
overrides; cache locks against duplicate calculations/conversions; Blade
escaping everywhere (no raw JSON rendered); CSV formula-injection defense;
AI output validation + prompt-injection isolation of customer text; DB
transactions on all multi-write operations; restrict FKs against hard deletes.

## Testing

`tests/Unit/Hvac/` + `tests/Feature/Hvac/` (~90 HVAC tests): calculators,
normalizer blockers, rule snapshots, selector inclusion/exclusion rules,
import, admin panel, approval, overrides, conversion protection, AI boundary.
Run: `php artisan test`.

## Deployment

```powershell
php artisan migrate        # 4 additive HVAC migrations
npm run build
```
No new environment variables are required. Optional:
`HVAC_EXPLANATION_PROVIDER` (default `null`) — reserve for a future AI
provider; API keys via env only.

Rollback: `php artisan migrate:rollback --step=4` removes all HVAC tables
(they are self-contained; no existing tables were modified).

## Known limitations / supplier data required

- The production catalog is **empty by design**. Real supplier CSV data is
  required for: products (all types incl. accessories with prices), and
  manufacturer compatibility (indoor↔outdoor, multi-split support tables,
  connected-capacity windows). Without accessories with prices, options stay
  in "manual review".
- Electrical supply, occupancy, equipment, pipe runs and drainage are not
  asked in the form yet — assumptions with warnings (candidate fields for a
  later form sprint).
- Roof/attic correction factor is deliberately neutral (1.00) until Martin
  provides a validated value.
- Rule editing happens in `config/hvac.php` → new rule-set version; an admin
  rule editor is a phase-2 candidate.
