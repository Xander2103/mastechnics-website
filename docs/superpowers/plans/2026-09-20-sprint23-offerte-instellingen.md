# Sprint 23 — Offerte-instellingen + praktijkhandleiding Implementation Plan

> **For agentic workers:** executed inline in the authoring session (user asked for autonomous work). Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Turn the technical "HVAC-berekeningsregels" admin page into an understandable "Offerte-instellingen" environment (guided checklist, five sections, draft editing + activation summary), add an indicative W/m³ quick cooling estimate, and deliver a practical manual + technical appendix + quick start for Martin.

**Architecture:** The rule-set architecture stays as is (`hvac_rule_sets` versioned, `hvac_rule_validations` per key with validated value, snapshots embedded in `hvac_calculations.result`). A new *presentation* layer (`HvacSettingsGuide`) maps catalog rule keys to five plain-language sections with meaning/use/example/consequence texts; the old table survives as "Geavanceerde instellingen". Quick-estimate values live in the rule-set configuration (`quick_estimate.w_per_m3`) and are computed by a pure service that never persists anything and has no path to approval, conversion or mail.

**Tech Stack:** Laravel 12, Blade, plain CSS (`resources/css/pages/`), PHPUnit, dompdf (already installed) or headless Chrome/Edge for the PDF.

**Spec:** Sprint 23 brief (pasted by the user on 2026-09-20; sections 1–13). Summary of hard requirements under Global Constraints.

## Global Constraints

- Do not remove or change the detailed engine (`CoolingLoadCalculator` simple_v1 / engineering_v2). Workbook regression 2572.229 W must stay green.
- Do not bypass the critical-rule approval gate (`HvacRecommendationReadiness`). No automatic validation: a rule is approved only by an explicit admin confirmation (who / when / value / rule version stored).
- No automatic activation, no automatic quote mail, no changes to anti-spam/mail, public services or SEO.
- Historical calculations/quotes keep their snapshots.
- Quick estimate: volume × chosen W/m³; four explicit situations 30/35/40/45 W/m³; configurable + versioned through the rule set; never hard-coded in controllers/views/JS; indicative only.
- Fictitious demo data must be explicitly marked and never stored in the catalog.
- UI wording: no unexplained "placeholder", "rule key", "snapshot", "critical validation". Statuses: Nog instellen / Controleren / Goedgekeurd / Niet beschikbaar.
- Admin UI is Dutch-only (existing convention); public site untouched.
- PowerShell syntax for commands. Commit locally in logical units. **No push, no deploy.**

## Audit findings that shape the plan

1. Rule VALUES cannot be edited in the admin today (`createDraft` comment: "changed by the developer"). Martin cannot set his hourly rate himself → add draft-only editing of simple numeric settings, audited.
2. Activation does not check validations (the gate sits at approval/conversion). Keep that; show missing confirmations in the activation summary instead of inventing a new block.
3. Validation only targets the active set; drafts inherit validations and a changed value invalidates them (`appliesTo`). Allow confirming inside a draft too, so a draft can be "klaar om te activeren".
4. `pricing.fallback_margin_pct_on_purchase` is labelled "marge" but is computed as an **opslag** on purchase (`purchase × (1 + pct/100)`); `margin_percentage` = product margin / subtotal excl. btw (incl. labour + travel). UI and appendix must say so; semantics unchanged.
5. `roof_type_factors` is deliberately neutral; several inputs (occupancy, equipment, pipe run, shading, ACH) are assumptions. These are shown as "aanname", never as validated business rules.
6. 12 critical rules apply to v1: 4× W/m² insulation, capacity classes, diversity factors, electrical per class, hourly rate, travel flat, fallback opslag, material opslag, default VAT.

## File Structure

Create:
- `app/Services/Hvac/QuickCoolingEstimator.php` — pure W/m³ estimate from rule-set config; returns steps, warnings, non-binding class indication.
- `app/Services/Hvac/HvacSettingsGuide.php` — five sections, plain-language guide per rule key (meaning, use, example builder, consequence, edit bounds), friendly status mapping.
- `app/Services/Hvac/HvacSettingsOverview.php` — dashboard data: progress, active version, recommendation status, catalog gaps, next action, draft diff.
- `app/Services/Hvac/HvacSettingsExample.php` — in-memory demo walk-through (fictitious product/prices, real services, zero DB writes).
- `app/Models/HvacRuleChange.php` + migration `hvac_rule_changes` — audit of draft value edits.
- migration adding `quick_estimate` to existing non-archived rule sets (additive key, no existing key touched).
- `app/Http/Controllers/Admin/HvacQuickEstimateController.php` — GET-only tool.
- views `resources/views/admin/hvac/settings/{index,section,advanced,example,quick-estimate}.blade.php` + partials.
- `resources/css/pages/admin-hvac-settings.css`.
- tests `tests/Unit/Hvac/QuickCoolingEstimatorTest.php`, `tests/Feature/Hvac/HvacQuoteSettingsTest.php`, `tests/Feature/Hvac/HvacQuickEstimateTest.php`.
- docs `docs/martin-hvac-praktijkhandleiding.md`, `docs/martin-hvac-technische-bijlage.md`, `docs/martin-hvac-snelstart.md`, PDF build script + PDFs under `docs/pdf/`.

Modify:
- `config/hvac.php` (add `quick_estimate` to v1 config → flows into v2).
- `app/Services/Hvac/HvacRuleCatalog.php` (4 quick-estimate entries, non-critical).
- `app/Http/Controllers/Admin/HvacRuleController.php` (sections, advanced, example, draft value update, confirm-in-draft, activation summary, explicit confirm).
- `routes/web.php` (new routes inside the existing admin group; existing route names kept).
- `resources/views/admin/hvac/partials/nav.blade.php`, `checklist.blade.php`, readiness/resolver messages ("Berekeningsregels" → "Offerte-instellingen").
- existing tests that assert the old page text or post validation without confirmation.
- `CLAUDE.md` sprint history; older docs get a pointer banner to the new manual.

## Tasks

### Task 1 — Quick estimate engine (TDD)
- [ ] Unit tests: 5×4×2.5 @30 → 50 m³ / 1500 W / 1.5 kW; all four situations; invalid/zero/negative/absurd dimensions; missing input; unknown situation; config missing → "niet beschikbaar"; values read from the passed rule configuration (changing config changes result); result carries `indicative = true`, warnings and `class_indication.binding = false`.
- [ ] Add `quick_estimate` to `config/hvac.php`, catalog entries, migration for existing sets; implement `QuickCoolingEstimator`.
- [ ] Regression: detailed engine tests + workbook test untouched and green. Commit.

### Task 2 — Settings guide + overview services
- [ ] `HvacSettingsGuide`: sections koelvermogen / toestellen / installatie / werkuren / verkoopprijzen; full guide text for every critical rule (v1 + v2) and the four quick values; generic fallback for the rest; edit bounds (numeric scalars only; VAT limited to 0/6/12/21).
- [ ] `HvacSettingsOverview`: counts, recommendation status, catalog gaps (reuse quality definitions), next action, draft-vs-active diff.
- [ ] Feature tests for status mapping and diff. Commit.

### Task 3 — Admin UI
- [ ] Dashboard, section pages with guided checklist cards (explicit confirmation checkbox + button), "Geavanceerde instellingen" (old table, kept), "Bekijk voorbeeld" page, quick-estimate tool, draft editing, activation summary with explicit confirmation.
- [ ] Controller: `confirm` required for validation; validation allowed on active or draft set; value update draft-only + audit row; activation unchanged in semantics.
- [ ] Update existing tests for renamed page / confirm parameter. Feature tests: authorization (guest → login) on every new route, no auto-activation, draft edit never touches active set or historical calculation, new calculation records the newly activated version, quick estimate creates no rows (calculations, recommendations, quotes, mail), example page writes nothing, no TEST/fictitious products created. Commit.

### Task 4 — Visual check
- [ ] Run locally, check desktop / tablet / mobile widths, long values, no page-level horizontal overflow, focus states, error messages. Fix. Commit.

### Task 5 — Documentation
- [ ] Praktijkhandleiding (12 chapters), technische bijlage (status legend: geïmplementeerd / bevestigd / nog te valideren / startwaarde / niet geïmplementeerd), snelstart. Every screen name, button and status verified against code.
- [ ] Pointer banners in superseded docs; in-app checklist links to the new manual.
- [ ] PDF build (title page, TOC, page numbers, callouts, tables kept together); visually inspect rendered pages. If tooling is insufficient: report honestly, ship Markdown only. Commit.

### Task 6 — Verification + report
- [ ] Full `php artisan test`, `npm run build`. Fix regressions.
- [ ] Update `CLAUDE.md` + memory. Final report with GO/NO-GO and Martin's concrete checklist.
