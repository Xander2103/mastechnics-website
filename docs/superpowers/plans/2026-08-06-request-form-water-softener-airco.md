# Request-Form Sprint: Water Softener Quote + Airco Installation Quote — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Rework the dynamic request wizard so the water-softener quote flow and airco-installation quote flow ask the right commercial/technical questions, drop the irrelevant device-technical step, and render everything visually in the admin — NL/FR/EN, backward compatible.

**Architecture:** Everything flows from `config/request-flow.php` (steps, fields, labels, options). The controller derives validation from config (`getDynamicFields` + `buildRulesForField`); the Blade wizard renders steps generically and hides/disables non-matching conditional steps client-side. We extend config (new steps, new field keys `help_text`, `decimal`, `min`, `max`, `visible_when`, `room_fields`), extend the field renderer + controller rule builder to honor the new keys, and extend the admin detail view with NL label maps and legacy fallbacks.

**Tech Stack:** Laravel 12, PHP 8.2, Blade, vanilla JS wizard, Vite build, PHPUnit (SQLite in tests).

## Global Constraints

- Windows / PowerShell syntax only for shell commands (`A; if ($?) { B }`).
- All user-facing labels must exist in **nl / fr / en**; fallback `nl`.
- Do not break: `CustomerRequest` storage, `attachments[]` uploads, `/nl/aanvraag` `/fr/demande` `/en/request`, `/admin/requests`, mail system, existing routes.
- Do not hard-code step logic in controller/Blade — config-driven (`config/request-flow.php`).
- Do not change non-target service flows (cv, lek, sanitair, ventilatie, koeling, andere, airco_onderhoud keep their behavior).
- Do not misuse `urgency` for the water-softener timeframe — new field `installation_timeframe`.
- Never render raw JSON in the admin for the new values.
- New submissions use new structure; historical requests must still render (legacy `attic_or_flat_roof`, `large_windows`, `urgency` values).
- Do not push. Do not deploy. Logical commits.

---

### Task 1: Remove the technical-device step from the two quote flows + relocate photo upload

**Files:**
- Modify: `config/request-flow.php` (technical_details step: add `condition`; airco_offerte helper_box → real upload)
- Modify: `resources/views/pages/partials/request-page.blade.php` (airco_rooms helper_box upload rendering; generalize attachment-preview JS)
- Modify: `app/Models/CustomerRequest.php` (`getMissingInfoChecklist`: brand/model + description items category-aware)
- Test: `tests/Feature/RequestFlowDeviceStepTest.php` (new)

**Interfaces:**
- Produces: `technical_details` step condition = `['service_categories' => ['airco_onderhoud','onderhoud_cv','herstelling_cv','dringend_lek','sanitair','ventilatie','koeling','andere']]` (i.e. everything except `airco_offerte` and `waterverzachter`).
- Produces: reusable Blade upload-box markup with `class="js-attachment-input"` on file inputs and `class="js-attachment-list selected-attachments"` on preview containers; one delegated JS initializer for all of them.

- [ ] **Step 1: Write failing tests** — `RequestFlowDeviceStepTest`:
  - `airco_offerte` submission **without** `brand`/`device_model`/`unknown_device_details` succeeds (currently fails: brand required).
  - `waterverzachter` submission without device fields succeeds.
  - `sanitair` submission without brand/model and without the unknown checkbox **fails** on `brand`.
  - `airco_onderhoud` keeps the device step (submission without brand and without checkbox fails).
  - Wizard render: the `technical_details` section carries a `data-condition-service-categories` attribute that excludes `airco_offerte`/`waterverzachter`.
- [ ] **Step 2: Run, verify failure.**
- [ ] **Step 3: Implement**: add the condition to `technical_details` in config; change the `airco_offerte_details` helper_box to `render_upload => true` with updated text (photos upload directly here now); render an upload input inside the airco_rooms helper-box branch in the Blade; refactor the two duplicated attachment-preview IIFEs into one `initAttachmentPreview(input)` applied to `.js-attachment-input` (keep old IDs working); make checklist items 5 (description) and 7 (brand/model) skip `airco_offerte` and `waterverzachter`.
- [ ] **Step 4: Run tests → pass. Run full `php artisan test`.**
- [ ] **Step 5: Commit** `feat(form): drop the device-technical step from quote flows, upload photos in the airco step`

### Task 2: Water-softener quote flow

**Files:**
- Modify: `config/request-flow.php` — step-0 option label; three new conditional steps `waterverzachter_timing`, `waterverzachter_household`, `waterverzachter_installation`; remove `waterverzachter` from `customer_context` + `description` conditions.
- Modify: `app/Http/Controllers/CustomerRequestController.php` — `buildRulesForField`: honor `decimal`, `min`, `max` on number fields; `preferred_time` chain adds `installation_timeframe`.
- Modify: `resources/views/pages/partials/request-page.blade.php` — field renderer: `help_text` under label; `visible_when` reveal wrapper (`data-visible-when-field/value`, hidden+disabled until matched) + delegated JS; waterverzachter summary section; `qVal`/`qSelectText` skip disabled fields.
- Modify: `resources/css/app.css` (or the project's main css) — `.is-reveal-hidden{display:none}`, `.field-help-text` style.
- Test: `tests/Feature/WaterSoftenerFlowTest.php` (new)

**Interfaces / stored answer keys (metadata.answers):**
- `customer_type` (residential|business, required — lives in `waterverzachter_timing` step)
- `installation_timeframe` (within_1_month | within_3_months | undecided | other, required) + `installation_timeframe_other` (text, revealed)
- `water_usage_m3` (nullable numeric 1–2000) + `water_usage_unknown` (checkbox)
- `bathrooms_count` (required int 1–20), `household_size` (required int 1–20)
- `softener_type_preference` (salt | other | no_preference, required) + `softener_type_other`
- `drain_distance` (within_1m | within_2_5m | more_than_5m | none | unknown), `power_socket_available` / `free_space_available` (yes|no|unknown)
- `preferred_time` column ← `installation_timeframe` when no other source.

Step-0 labels: NL "Offerte voor een waterverzachter" (+desc "Voor advies, plaatsing en een vrijblijvende prijsinschatting."), FR "Demande de devis pour un adoucisseur d'eau" (+ "Pour un conseil, l'installation et une estimation de prix sans engagement."), EN "Request a quote for a water softener" (+ "For advice, installation and a no-obligation price estimate.").

- [ ] Step 1: failing tests — NL/FR/EN render of the new option label and new step fields; submission stores normalized values; `urgency` not required for water; `other` timeframe stores custom text; usage-unknown works; bathrooms/household validated (0 rejected); drain options render; photo upload optional.
- [ ] Step 2: run → fail. Step 3: implement config + controller + blade + css. Step 4: tests pass + full suite. Step 5: Commit `feat(form): dedicated water-softener quote flow with timeframe, household and installation steps`.

### Task 3: Airco installation room rework

**Files:**
- Modify: `config/request-flow.php` — `airco_offerte_details` step gains `room_fields` (width/length/height numbers with unit labels; `roof_type`, `windows`, `orientation` selects with `visible_when` other-texts) and house-level `insulation_level` (+`insulation_level_other`) in `fields`.
- Modify: `resources/views/pages/partials/request-page.blade.php` — room entry rendered from `room_fields` config; summary JS includes height/roof/windows/orientation/insulation.
- Modify: `app/Http/Controllers/CustomerRequestController.php` — rooms validation + `processedRooms` for the new keys; drop `attic_or_flat_roof`/`large_windows` from new-submission processing.
- Modify: `app/Models/CustomerRequest.php` — room completeness check accepts new structure or legacy pair.
- Test: `tests/Feature/AircoInstallationFlowTest.php` (new)

**Normalized room keys:** `type, width, length, height, surface, roof_type (flat_roof|attic_no_roof_window|attic_with_roof_window|none|other), roof_type_other, windows (large|small|mixed|few_none|other), windows_other, orientation (north|east|west|south|other|unknown), orientation_other`. House: `insulation_level (excellent|good|average|poor|other|unknown)`, `insulation_level_other`.

Validation: width/length required numeric 1–50; height required numeric 1.5–8; selects nullable + `in:`; `*_other` nullable string max 255; surface computed server-side (`round(w*l,1)`).

- [ ] Step 1: failing tests — render shows Breedte (m)/Lengte (m)/Hoogte (m), roof-type options, windows options, orientation options, insulation options in NL/FR/EN; old labels ("Zolderkamer of onder plat dak?", "Veel grote ramen?") gone; submission stores surface + new keys; zero/absent height rejected; attic/large_windows no longer accepted keys in stored rooms.
- [ ] Steps 2–4: run/implement/pass + full suite. Step 5: Commit `feat(form): airco rooms ask height, roof type, windows, orientation and insulation`.

### Task 4: Admin visual display + backward compatibility

**Files:**
- Modify: `resources/views/admin/requests/show.blade.php` — waterverzachter card (timeframe/usage/bathrooms/household/type/drain/socket/space with NL labels); airco room cards (dimensions with units, computed surface fallback, new labels, legacy attic/windows fallback rows, orientation, per-room other-texts); insulation row; "Gewenst moment" label map; "Alle antwoorden" details renders arrays as flattened `key: value` lines (no `json_encode`).
- Modify: `app/Models/CustomerRequest.php` — water-softener missing-info items (household/bathrooms/drain missing).
- Test: `tests/Feature/Admin/RequestDetailNewFieldsTest.php` (new)

- [ ] Step 1: failing tests — admin shows 'Binnen 1 maand', 'Met zout', '2 badkamers'-style labels for a water request; airco request shows height/surface/roof/orientation labels; legacy request (old attic yes/no + large_windows + urgency) still renders 'Ja'/'Nee' and urgency badge; response does not contain `json_encode`-style `"attic_or_flat_roof"` raw keys.
- [ ] Steps 2–4: implement, pass, full suite. Step 5: Commit `feat(admin): visual labels for water-softener and airco quote answers with legacy fallbacks`.

### Task 5: Final verification

- [ ] `php artisan test` (full), `npm run build`, `php -l` on every changed PHP file, `git status` clean review.
- [ ] Final report: old fields removed, new fields added, normalized values, flow-step changes, files changed, commits, test/build results, backward-compat behavior, manual NL/FR/EN checklist.

## Self-Review notes

- Spec A covered by Task 1 (incl. regression tests for maintenance flows keeping the step).
- Spec B covered by Task 2 (label, timing, household, location; optional socket/space fields included as small+safe).
- Spec C covered by Task 3 (dimensions+height, roof type replaces yes/no, windows replaces large-windows, insulation, orientation, internal normalized English values).
- Spec D: step visibility/ordering handled by existing condition machinery; duplicate hidden fields already disabled by wizard JS; summary JS updated to skip disabled fields (Task 2).
- Spec E covered by Task 4. Spec F: legacy fallbacks in Task 4 + compat-aware checklist in Tasks 1/3. Spec G: tests in every task. Spec H: config-driven, centralized validation/translations, logical commits, no push.
