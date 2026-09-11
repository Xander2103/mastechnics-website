# Schoorsteenvegen Service Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add "Schoorsteenvegen / Ramonage / Chimney Sweeping" as a seventh public service: service page (nl/fr/en), wizard flow, admin rendering, SEO, tests.

**Architecture:** Services are registered in `config/services.php` (key, slugs, hero image). Everything config-driven (nav, hub, sitemap, OfferCatalog, location pages, 404, admin filter) picks the new key up automatically. Hardcoded per-service maps (icons, content, accent CSS, FAQ, seeder meta, home hex pyramid) get one new entry each. Pages live in the DB, so existing installs get the page via a migration; fresh installs via `PageSeeder`. The wizard gets one new category and two config-driven steps that reuse the existing `attachments[]` upload box.

**Tech Stack:** Laravel 12, Blade, vanilla CSS/JS, PHPUnit, Vite.

**Spec:** the user prompt of 2026-09-11 (this plan is its design record).

## Global Constraints

- Locales nl/fr/en for every label.
- No prices on the public site.
- Hero image is `public/assets/images/schoorsteenveger.webp` (already present, 2172×724). No other image may be generated/downloaded.
- Existing services, routes, mail, storage, admin must not change behaviour. Existing condition lists in `config/request-flow.php` (`description`, `technical_details`, `customer_context`) stay untouched; `RequestFlowDeviceStepTest` asserts their literal value.
- Uploads reuse `attachments[]` + existing validation (`mimes:jpg,jpeg,png,webp,pdf`, `max:5120`, max 8).
- Nothing pushed or deployed. Local commits per logical group.

## Identifiers (used by every task)

| Thing | Value |
|---|---|
| service key / page code | `chimney-sweeping` |
| slugs | nl `schoorsteenvegen`, fr `ramonage`, en `chimney-sweeping` |
| titles | nl `Schoorsteenvegen`, fr `Ramonage`, en `Chimney sweeping` |
| hero_image | `assets/images/schoorsteenveger.webp` |
| wizard category value | `schoorsteenvegen` (service_key `chimney-sweeping`, request_type `maintenance`) |
| wizard steps | `schoorsteen_installatie`, `schoorsteen_aansluiting` |
| fields | `chimney_appliance_type` (select, required), `chimney_appliance_type_other` (text, visible_when other), `description` (textarea, optional, "Eventuele opmerkingen") |
| appliance values | `freestanding_wood_stove`, `insert_cassette`, `built_in_fireplace`, `suspended_fireplace`, `soapstone_stove`, `central_heating_stove`, `open_fireplace`, `other` |
| accent | `#8C4A2B` / rgb `140, 74, 43` |

---

### Task 1: Service registry, content, SEO, page data (service page works in 3 locales)

**Files:**
- Modify: `config/services.php` (new key), `config/service-faqs.php` (3 FAQs × 3 locales)
- Modify: `resources/views/pages/partials/service-page.blade.php` (`$serviceIcons`, `$serviceContent`)
- Modify: `resources/css/pages/service.css` (accent tokens)
- Modify: `database/seeders/PageSeeder.php` (`serviceMeta()` + real content for this key)
- Create: `database/migrations/2026_09_11_000001_add_chimney_sweeping_service_page.php` (insert page + 3 translations; update services-hub intro/meta "zes" → "zeven")
- Modify: `config/site.php` tagline, `resources/views/layouts/app.blade.php` footer text, `resources/views/pages/partials/home-page.blade.php` hero/why intro prose, `public/site.webmanifest`
- Test: `tests/Feature/ChimneySweepingTest.php` (page section), `tests/Feature/ServicePageTest.php:69` (7 slugs)

- [ ] Write failing tests: nl/fr/en page 200 with title, hero `<img ... src=".../schoorsteenveger.webp"`, `Service` eyebrow, CTA text "Vraag een offerte of interventie aan", no `€`; sitemap lists 3 URLs + hero image; nav dropdown + hub + home contain the title.
- [ ] Run → fail (404).
- [ ] Implement config/content/migration/seeder/CSS.
- [ ] Run → pass. Run `SeoStructureTest`, `ServicePageTest`, `SitemapTest`, `HomepageTest`.
- [ ] Commit `feat(services): add Schoorsteenvegen service page (nl/fr/en) with SEO + content`.

### Task 2: Homepage hex pyramid + service-card accent

**Files:**
- Modify: `resources/views/pages/partials/home-page.blade.php` (hex rows 2-3-2, new hex with icon)
- Modify: `resources/css/pages/home.css` (`.hero-hex--soot`)
- Modify: `resources/views/pages/partials/services-page.blade.php`, `home-page.blade.php` (heat class list gets `chimney-sweeping`)

- [ ] Test: homepage nl shows `hero-hex--soot` + Schoorsteenvegen link.
- [ ] Implement, run `HomepageTest` + new test.
- [ ] Commit `feat(home): chimney sweeping in hero hex cluster`.

### Task 3: Wizard flow

**Files:**
- Modify: `config/request-flow.php` (step-0 option, two steps, `service_categories` entry)
- Modify: `resources/views/pages/partials/request-page.blade.php` (helper box `position => before_fields` support via new partial; summary labels + JS section)
- Create: `resources/views/pages/partials/request-upload-box.blade.php`
- Modify: `app/Models/CustomerRequest.php` (`getMissingInfoChecklist`: chimney skips brand/model + description flags)
- Test: `tests/Feature/ChimneySweepingTest.php` (wizard section)

- [ ] Tests: option visible in 3 locales; appliance options rendered; both upload boxes rendered; valid POST stores `service_category=schoorsteenvegen`, `service_slug=schoorsteenvegen`, `request_type=maintenance`, answers, 2 attachments on `local` disk; invalid appliance value → error; `.exe` upload → error `attachments.0`; missing appliance type → error; `other` stores free text.
- [ ] Implement, run new tests + `WaterSoftenerFlowTest`, `RequestFlowDeviceStepTest`, `RequestWizardRenderTest`, `CustomerRequestSubmissionTest`, `AircoInstallationFlowTest`.
- [ ] Commit `feat(request-flow): chimney sweeping flow with appliance type and photo uploads`.

### Task 4: Admin detail

**Files:**
- Modify: `resources/views/admin/requests/show.blade.php` (label map + "Schoorsteenvegen" card)
- Test: admin detail shows "Schoorsteen laten vegen" category label, appliance label, other text, notes.

- [ ] Test → implement → run `tests/Feature/Admin` → commit `feat(admin): chimney sweeping answers as labels`.

### Task 5: Verification, docs

- [ ] `php -l` on changed PHP files, `php artisan test`, `npm run build`.
- [ ] CLAUDE.md sprint entry; commit `docs: sprint 22 chimney sweeping`.
