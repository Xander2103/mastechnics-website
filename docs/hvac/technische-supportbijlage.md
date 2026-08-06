# Technische supportbijlage — HVAC-module (voor Xander / ontwikkelaar)

Deze bijlage hoort bij `klantenhandleiding-mastechnics.md` maar is bedoeld
voor technische ondersteuning, niet voor Martin. Geen geheimen of sleutels
hier opnemen.

## Architectuur in één alinea

Drie strikt gescheiden lagen: deterministische services
(`app/Services/Hvac/`, regels uitsluitend via `HvacRuleSetResolver` uit de
geversioneerde `hvac_rule_sets`), de productcatalogus (feiten, admin + CSV,
nooit hard deleten) en een optionele AI-uitleglaag
(`app/Services/Hvac/Explanation/`, standaard `NullHvacExplanationGenerator`,
uitvoer gevalideerd door `AiExplanationValidator`, gelogd in
`hvac_ai_logs`). Volledige beschrijving: `docs/hvac/architecture.md`.

## Kernservices

| Klasse | Rol |
|---|---|
| `HvacInputNormalizer` | Aanvraag → immutabel invoermodel; blockers/warnings |
| `CoolingLoadCalculator`, `CapacityClassSelector`, `PipeEstimator`, `ElectricalEstimator` | Deterministische berekening |
| `HvacCalculationService` | Orkestratie + snapshot; herberekenen = superseden |
| `ProductSelector` | Catalogus + compatibiliteit; override-bewust |
| `AccessorySelector`, `LaborEstimator`, `HvacPricingService`, `MarginCalculator` | Materialen, uren, prijs, marge |
| `HvacRecommendationBuilder` | Budget/Aanbevolen/Premium uit geldige kandidaten |
| `HvacManualOverrideService` | Overrides (items, btw, product, kamerlast, korting) + audit |
| `HvacRecommendationReadiness` | Goedkeurings-gating (technisch/prijs/regels, demo-detectie) |
| `HvacQuoteConversionService` | Goedgekeurd → conceptofferte; bestaande offerte blokkeert |
| `HvacCsvImporter`, `HvacCompatibilityCsvImporter` | Transactionele imports |
| `HvacRuleCatalog` | Regelcatalogus (labels, categorieën, kritieke set) |
| `HvacRuleSetResolver` | Actieve regelset; seed v1 uit `config/hvac.php` |

## Databank

`hvac_brands`, `hvac_suppliers`, `hvac_products` (incl. `seer`, `scop`),
`hvac_product_compatibilities`, `hvac_rule_sets`, `hvac_rule_validations`,
`hvac_calculations`, `hvac_recommendations` (+`metadata`),
`hvac_recommendation_items`, `hvac_manual_overrides`, `hvac_ai_logs`.
Migraties: `2026_08_06_1000*` en `2026_08_06_110000_*` — additief en
reversibel; rollback verwijdert uitsluitend HVAC-tabellen/kolommen.

## Routes (allemaal achter `admin`-middleware)

`admin/hvac/products*` (CRUD-zonder-delete, duplicate, toggle),
`admin/hvac/brands*`, `admin/hvac/suppliers*`, `admin/hvac/compatibilities*`,
`admin/hvac/import*` (producten + `compat/*`, templates, foutenrapport),
`admin/hvac/rules*` (index, validate, unvalidate, draft, {ruleSet}/activate),
`admin/hvac/checklist`, en per aanvraag
`admin/requests/{r}/hvac/{calculate | rooms/override-load |
recommendations/{rec}/(approve|reject|acknowledge|vat|convert|discount) |
items/{item}/(override|change-product)}`.

## Configuratie & data

- `config/hvac.php` — regelset v1 (seed) + `explanation_provider`
  (`HVAC_EXPLANATION_PROVIDER`, standaard `null`; binding in
  `AppServiceProvider`).
- CSV-sjablonen: `docs/hvac/hvac-products-template.csv`,
  `docs/hvac/hvac-compatibiliteit-sjabloon.csv` (ook downloadbaar in admin).
- Demodata: `php artisan db:seed --class=HvacDemoCatalogSeeder`
  (weigert in production; alles TEST-gemerkt; banner in admin zolang actieve
  TEST-producten bestaan).

## Regelwijziging (procedure)

1. Nieuwe conceptversie laten aanmaken (admin, "Nieuwe conceptversie") of
   `HvacRuleSet` dupliceren.
2. Waarden aanpassen in de `configuration`-JSON van de **draft** (nooit de
   actieve rij muteren — snapshots beschermen de historiek sowieso, maar
   houd de audit zuiver).
3. Martin laat valideren (`hvac_rule_validations`), daarna activeren met
   bevestiging. Alleen nieuwe berekeningen volgen de nieuwe versie.

## Diagnose

- Tests: `php artisan test --filter Hvac` (±120 tests) of volledig.
- Logs: `storage/logs/laravel.log`; AI-runs: tabel `hvac_ai_logs`
  (input_hash, validation_status); mails: `mail_logs`.
- Snel databankzicht: `php artisan tinker` →
  `HvacCalculation::latest()->first()->result` /
  `HvacRecommendation::with('items')->latest()->first()`.
- Cache-locks bij "er loopt al een berekening": verlopen automatisch na 30 s.

## Deploy & rollback

Deploy: `php artisan migrate` + `npm run build`; geen nieuwe verplichte
env-variabelen. Rollback HVAC-only: `php artisan migrate:rollback` voor de
vijf HVAC-migraties (controleer batchnummers eerst); code via git revert
van de `feat(hvac)`/`fix(hvac)`-commits.
