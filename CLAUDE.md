# Mastechnics — Claude Code Project Instructions

## Project

Laravel 12 website for Mastechnics, an HVAC company (heating, airco, plumbing, ventilation, water softeners, cold rooms). The site has a multilingual Smart Request Flow form, an admin dashboard, and a customer request storage system.

## Languages

The site is always multilingual: **nl / fr / en**. Every user-facing label, placeholder, error, and helper text must have translations for all three locales. Default fallback is `nl`.

## Non-Negotiable Constraints

These must never break:
- Customer request storage (`CustomerRequest` model, `customer_requests` table)
- File uploads (stored via `attachments[]`, linked via `CustomerRequestAttachment`)
- The request flow form (`/nl/aanvraag`, `/fr/demande`, `/en/request`)
- The admin dashboard (`/admin/requests`)
- Existing mail system (`NewCustomerRequestMail`, `CustomerRequestConfirmationMail`)
- All existing routes in `routes/web.php`

## Shell

This project runs on **Windows**. Use **PowerShell** syntax only. Never use Bash-style commands (`&&` chaining, `$VAR`, `/dev/null`, etc.).

PowerShell equivalents:
- Chain commands: `A; if ($?) { B }` — not `A && B`
- Null device: `$null` — not `/dev/null`
- Env vars: `$env:VAR` — not `$VAR`

## Architecture Rules

- Follow existing Laravel conventions: controllers in `app/Http/Controllers/`, models in `app/Models/`, config in `config/`, views in `resources/views/`.
- The request flow is driven by `config/request-flow.php` — UI and validation are derived from it. Do not hard-code step logic in the controller or blade.
- Admin auth uses `AdminUser` model + `Hash::check()`. Never revert to plain-text passwords or `config/admin.php` password storage.
- Keep the `metadata` JSON column for flexible answer storage alongside dedicated columns for structured fields.

## Security

- Never store plain-text passwords or secrets in config files.
- Validate all user input at the server (controller). Client-side JS is UX only, never trust it for security.
- File uploads: validate MIME type and size server-side (`mimes:jpg,jpeg,png,webp,pdf`, `max:5120`).
- Never expose admin routes without the `admin` middleware.

## Design & UX Approach

- **Mobile-first** — the form is used on mobile. Test mobile layout before claiming done.
- Apply **CRO thinking** to landing pages: clear headlines, trust signals, single focused CTA.
- Apply **form-CRO thinking** to the Smart Request Flow: reduce friction, guide with helper text, show only relevant fields, use option cards not dropdowns where possible.
- Apply **pricing-strategy thinking** when working on quotes or service pricing pages.
- Apply **frontend design skills** (clean spacing, readable type, accessible contrast) and **superpowers** (brainstorming, planning, verification) for any UI work.

## Available Skills

Installed and usable:
- `/copywriting` — for landing page copy, CTAs, email content
- `/seo-audit` — for on-page SEO review
- `/code-reviewer` — code review for security, performance, and correctness (Med Risk rating from Gen scanner — review before using on sensitive code)

**Not installed** — do not invoke these as slash commands:
- `/ui-ux-pro-max` — not installed
- `/page-cro` — not installed
- `/form-cro` — not installed
- `/pricing-strategy` — not installed

If CRO, pricing, or UX thinking is needed, apply it as plain reasoning — do not attempt to invoke missing skills.

## AI & Automation Placeholders

- `ai_summary` and `ai_detected_missing_fields` columns exist on `customer_requests` — leave them `null` for now.
- Do **not** implement OpenAI, Claude API calls, or any LLM integration yet.
- Do **not** implement WhatsApp API yet.
- Do **not** implement Google Calendar API yet.
- Only add these when explicitly requested in a sprint task.

## Task Discipline

- Work **task-by-task**. Do not bleed changes across tasks.
- For any change touching more than one file: stop and summarize changed files, risks, and how to test before continuing.
- After each task: list files changed, exact issue fixed or feature added, any risks, and the next recommended task.
- Use `superpowers:writing-plans` before multi-step implementation. Use `superpowers:subagent-driven-development` to execute plans task-by-task with review gates.
- Mark tasks complete in `TodoWrite` immediately after finishing — do not batch completions.

## SEO Architecture

- All SEO output goes through `App\Services\SeoService` (canonical, hreflang,
  meta fallbacks, schema.org `@graph`). Do not hand-write `<link rel="canonical">`,
  hreflang or JSON-LD in a Blade template.
- The service is a **request-scoped singleton**. Page templates contribute extra
  schema nodes with `addNode()`; `PageController` calls `resetNodes()` before each
  render. Never resolve it with `new SeoService()`.
- Slugs and labels of fixed pages live in `config/site.php` (`page_slugs`,
  `page_labels`). Never re-inline a locale => slug map in a template.
- Structured data must stay **one** `<script type="application/ld+json">` per
  page. `SeoStructureTest` fails the build if a second one appears.
- Never add `aggregateRating` or `review` to the LocalBusiness node — Google
  disallows self-serving review markup and it risks a manual action.
- Page types dispatch in `resources/views/pages/show.blade.php`: `home`,
  `service`, `services`, `location`, `service_area`, `request`, `contact`,
  `privacy`.
- Local content lives in `config/service-areas.php` (per municipality) and
  `config/service-faqs.php` (per service). Any new municipality page must say
  something genuinely specific about that place, otherwise it is a doorway page.

## Sprint History

- **Sprint 1** ✅ — Admin auth migrated to `AdminUser` DB model with `Hash::check()` and `Hash::make()`.
- **Sprint 2** 🔄 — Smart Request Flow: `service_category_selection` step, 4 conditional flows (CV onderhoud, Lek/dringend, Airco offerte, Airco onderhoud), new workflow DB columns.
  - Task 1 ✅ Migration fixed
  - Task 2 ✅ Model fixed
  - Tasks 3–8 pending
- **Sprint 11 (SEO)** ✅ — Full technical + local SEO rebuild. `SeoService`,
  single `@graph`, services hub, service-area hub, six municipality landing
  pages, FAQ content + FAQPage, visible breadcrumbs, meta rewrite, image/CWV
  work, custom 404, crawl controls. Committed locally, **not pushed**.
- **Sprint 12 (request form) ✅** — Quote-flow rework. Device-technical step
  now conditional (skipped for `airco_offerte` + `waterverzachter`); dedicated
  water-softener quote flow (`installation_timeframe`, household/usage,
  installation location + upload); airco rooms ask width/length/height,
  `roof_type`, `windows`, `orientation` per room and house `insulation_level`
  (normalized English values for the future HVAC engine, defined in
  `room_fields` config); admin detail renders everything as labels with
  legacy fallbacks. Committed locally, **not pushed**.
- **Sprint 13 (HVAC) ✅** — Deterministic pre-quotation system for airco
  installations: versioned rule sets (`config/hvac.php` → `hvac_rule_sets`),
  immutable calculation snapshots, catalog + compatibility-driven product
  selection, CSV import, accessories/labor/pricing/margin, admin panel
  "Automatische airco-voorcalculatie" with approval + audited overrides,
  conversion of approved options into draft quotes (never auto-mailed), and a
  null-by-default AI explanation boundary with strict output validation.
  Production catalog is empty until real supplier data is imported. See
  `docs/hvac/architecture.md` and `docs/hvac/rules-to-validate.md`.
  Committed locally, **not pushed**.

- **Sprint 15 (import wizard UX + productlijsten) ✅** — Guided import rebuilt
  as a 5-step Dutch wizard (Bestand → Producten → Controle → Importeren →
  Resultaat): robust delimiter detection (`CsvDelimiterDetector`: ; , tab |,
  quote-aware, asks only when ambiguous — root cause of the CatalogFR.csv
  one-column bug), streaming CSV reader (50k+ rows, cp1252, BOM, quoted
  newlines), generic category detection + filtering (`CategoryDetector`),
  auto-mapping with ask-first price semantics (gross/unknown prices never
  land in price columns), explicit derivations (supplier/brand from wizard,
  NL→FR name fallback, model←SKU, conservative type inference, review-flagged
  capacity-from-name), per-product provenance in `metadata.import`, profiles
  auto-recognized by header signature. New catalog hierarchy: `HvacImportCatalog`
  (Productlijsten, default product view) + pivot with source facts +
  `HvacImportRun` history; archive is status-only, missing products are never
  auto-deleted (explicit deactivate checkbox, only when not in another active
  list). Fixture `tests/Support/CatalogFrFixture.php` mirrors the real file.
  Committed locally, **not pushed**.
- **Sprint 14 (HVAC v2 + import) ✅** — Excel reference calculator fully
  reverse-engineered (`docs/hvac/excel-calculator-audit.md`); new DRAFT rule
  set "Belgische residentiële koellast" v2 (`hvac:seed-v2-rule-set`,
  `load_method: engineering_v2` in `CoolingLoadCalculator`, workbook
  regression test 2572.229 W) — v1 untouched, activation only after Martin's
  validation. Import upload limit configurable via `HVAC_IMPORT_MAX_MB`
  (default 25 MB, `docs/hvac/import-deployment.md`). Guided mapping import
  wizard for arbitrary supplier CSV/XLSX (native safe XLSX reader, header
  detection, alias suggestions, `hvac_mapping_profiles`); classic template
  imports also accept .xlsx. Committed locally, **not pushed**.

## HVAC Architecture

- Deterministic services live in `app/Services/Hvac/`; rules only via
  `HvacRuleSetResolver` (never read `config/hvac.php` directly in services).
- Never hard-delete `HvacProduct` rows — deactivate. Model + FK guards throw.
- AI (`Explanation/`) may only explain validated results; output is validated
  by `AiExplanationValidator` and logged in `hvac_ai_logs`. The system must
  keep working with `NullHvacExplanationGenerator`.
- Do not seed real-brand products or invent manufacturer compatibility;
  factories/tests use fictional TestBrand data only.
- Rule values are placeholders until validated — see
  `docs/hvac/rules-to-validate.md` before touching them.
