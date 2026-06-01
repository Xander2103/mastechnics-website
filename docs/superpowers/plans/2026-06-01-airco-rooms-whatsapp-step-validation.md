# Airco Room Fix, WhatsApp Header Icon, Step Validation — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Fix airco room remove button, make airco_house_age required for airco_offerte, add a mobile-only WhatsApp icon in the header, and add client-side step validation before "Verder".

**Architecture:** Four targeted changes across config, Blade, CSS, and inline JS. No new files. No DB changes. No route changes. All changes are scoped to the request form (Parts 1 & 3), the app layout header (Part 2), and the header CSS. Builds on existing wizard infrastructure.

**Tech Stack:** Laravel 12, Blade, vanilla JS (ES5 IIFE in Blade), CSS (mobile-first, breakpoint 680px). WhatsApp uses `config('site.contact.whatsapp_link')` which already holds `32495121178`.

---

## File Map

| File | What changes |
|------|-------------|
| `config/request-flow.php` | Set `airco_house_age` `required: true` (line ~527) |
| `resources/views/pages/partials/request-page.blade.php` | (a) Always render `.room-remove-btn` in every room entry; (b) Add required-star + `data-required` to `airco_rooms` step fields; (c) Add `data-required="true"` to required inputs in `fields` type steps; (d) Add `validateCurrentStep()` JS + wire to Verder click |
| `resources/views/layouts/app.blade.php` | Wrap hamburger toggle + new WhatsApp `<a>` in `.header-mobile-right` div |
| `resources/css/layout/header.css` | Style `.header-whatsapp-btn` — hidden by default, visible only at ≤680px; adjust mobile grid |

---

## Task 1: Part 1B — Make `airco_house_age` required in config

**Files:**
- Modify: `config/request-flow.php` (line ~527)

- [ ] **Step 1: Change `required: false` → `required: true` for `airco_house_age`**

  In `config/request-flow.php`, find the `airco_house_age` field inside Step 3 (`airco_offerte_details`). It is currently:
  ```php
  [
      'name'     => 'airco_house_age',
      'type'     => 'select',
      'required' => false,
      ...
  ],
  ```
  Change to:
  ```php
  [
      'name'     => 'airco_house_age',
      'type'     => 'select',
      'required' => true,
      ...
  ],
  ```

  **Why this is safe:** `getDynamicFields()` in `CustomerRequestController` only processes the `airco_rooms` step's fields when `service_category === 'airco_offerte'`. So `airco_house_age` only enters validation for that category. `buildRulesForField()` will then emit `['required', 'string', Rule::in(['yes','no','unknown'])]`.

- [ ] **Step 2: Verify server-side behavior is correct**

  Open `app/Http/Controllers/CustomerRequestController.php` and confirm:
  - `getDynamicFields()` checks condition on `airco_rooms` step type (line ~187): `if (!in_array($selectedCategory, $allowedCategories, true)) { continue; }`
  - `buildRulesForField()` at line ~224: `if ($field['required'] ?? false) { $rules[] = 'required'; }`

  No code changes needed — the existing controller logic already handles this correctly.

- [ ] **Step 3: Commit config change**

  ```
  git add config/request-flow.php
  git commit -m "fix: make airco_house_age required for airco_offerte"
  ```

---

## Task 2: Part 1A — Fix room remove button (always render it)

**Files:**
- Modify: `resources/views/pages/partials/request-page.blade.php` (lines ~285–352, the `@foreach ($oldRooms...)` block)

**Root cause:** The first room (index 0) is rendered without a `.room-remove-btn` element due to `@if ($ri > 0)`. When the user has only 1 room and clicks "Kamer toevoegen", the new room is cloned from entry 0 (the only entry), so the clone has no remove button. The existing `renumber()` JS looks for `.room-remove-btn` in each entry but finds nothing — and never inserts one.

**Fix:** Always render the remove button in every room entry. Use inline `style="display:none"` for index 0. `renumber()` already handles show/hide with `removeBtn.style.display = idx === 0 ? 'none' : ''`, so no JS changes are needed.

- [ ] **Step 1: Update Blade — always render remove button**

  Find this block inside the `@foreach ($oldRooms as $ri => $room)` loop (around line 287):
  ```php
  <div class="room-entry-header">
      <h4 class="room-entry-title">{{ $text['room_label'] }} {{ $ri + 1 }}</h4>
      @if ($ri > 0)
          <button type="button" class="room-remove-btn button button-ghost button-small">{{ $text['room_remove'] }}</button>
      @endif
  </div>
  ```

  Replace with:
  ```php
  <div class="room-entry-header">
      <h4 class="room-entry-title">{{ $text['room_label'] }} {{ $ri + 1 }}</h4>
      <button type="button" class="room-remove-btn button button-ghost button-small"
          style="{{ $ri === 0 ? 'display:none' : '' }}">{{ $text['room_remove'] }}</button>
  </div>
  ```

  This ensures every room entry (including the server-rendered first room) has the remove button in the DOM. `renumber()` will correctly set `style.display` based on index.

- [ ] **Step 2: Verify no JS changes needed**

  Open the JS `renumber()` function (around line 790). Confirm it already does:
  ```js
  var removeBtn = entry.querySelector('.room-remove-btn');
  if (removeBtn) {
      removeBtn.style.display = idx === 0 ? 'none' : '';
      removeBtn.textContent = removeLabel;
  }
  ```
  No changes needed here.

- [ ] **Step 3: Verify add-room cloning now works**

  Trace through `addBtn` click handler: it does `entries[entries.length - 1].cloneNode(true)`. After our fix, even if there is only 1 entry (index 0), the clone now contains the remove button (hidden). `renumber()` is called, which sets `display = ''` on the newly added entry (now index 1). The remove button becomes visible on entry 1. ✓

- [ ] **Step 4: Commit**

  ```
  git add resources/views/pages/partials/request-page.blade.php
  git commit -m "fix: always render room remove button so cloning preserves it"
  ```

---

## Task 3: Part 2 — Mobile-only WhatsApp icon in header

**Files:**
- Modify: `resources/views/layouts/app.blade.php` (lines ~130–167, the `<header>` block)
- Modify: `resources/css/layout/header.css`

**Implementation strategy:** Wrap the existing `.mobile-menu-toggle` button inside a new `.header-mobile-right` flex div. Add a `.header-whatsapp-btn` anchor before the toggle inside that div. Hide the WhatsApp button at all sizes by default. Show it only at ≤680px. Keep desktop layout identical — the `.header-mobile-right` wrapper on desktop just renders as the toggle button (which is already `display:none` there).

The WhatsApp number comes from `config('site.contact.whatsapp_link')` = `32495121178`.

- [ ] **Step 1: Update app.blade.php — wrap toggle + add WhatsApp anchor**

  Find in `resources/views/layouts/app.blade.php`:
  ```html
  <button class="mobile-menu-toggle" type="button" aria-label="Menu openen">
      <span></span>
      <span></span>
      <span></span>
  </button>
  ```

  Replace with:
  ```html
  <div class="header-mobile-right">
      <a class="header-whatsapp-btn"
         href="https://wa.me/{{ config('site.contact.whatsapp_link') }}"
         target="_blank"
         rel="noopener noreferrer"
         aria-label="{{ ($locale ?? 'nl') === 'fr' ? 'Discuter via WhatsApp' : (($locale ?? 'nl') === 'en' ? 'Chat via WhatsApp' : 'Chat via WhatsApp') }}">
          <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" focusable="false">
              <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
          </svg>
      </a>

      <button class="mobile-menu-toggle" type="button" aria-label="Menu openen">
          <span></span>
          <span></span>
          <span></span>
      </button>
  </div>
  ```

- [ ] **Step 2: Update header.css — add wrapper and WhatsApp button styles**

  At the end of the desktop section (before the `@media (max-width: 680px)` block), add:

  ```css
  /* Mobile right action group — wraps toggle + WhatsApp on mobile */
  .header-mobile-right {
      display: contents; /* desktop: transparent wrapper, toggle stays display:none */
  }

  /* WhatsApp header button — hidden everywhere except mobile */
  .header-whatsapp-btn {
      display: none;
  }
  ```

  Inside the existing `@media (max-width: 680px)` block, add:

  ```css
  .header-mobile-right {
      display: flex;
      align-items: center;
      gap: 8px;
  }

  .header-whatsapp-btn {
      display: inline-flex;
      justify-content: center;
      align-items: center;
      width: 42px;
      height: 42px;
      border: 1px solid var(--color-border);
      border-radius: 999px;
      background: #f0fdf4;
      color: #16a34a;
      box-shadow: 0 8px 22px rgba(15, 53, 87, 0.06);
      text-decoration: none;
      flex-shrink: 0;
  }

  .header-whatsapp-btn:hover,
  .header-whatsapp-btn:focus {
      background: #dcfce7;
      color: #15803d;
  }
  ```

  **Note:** `display: contents` on `.header-mobile-right` at desktop makes it a transparent wrapper — the `.mobile-menu-toggle` inside it remains `display:none` as before, and the WhatsApp button stays `display:none`. On mobile, `.header-mobile-right` becomes `display:flex`, creating the side-by-side layout. The `header-container` grid on mobile (`grid-template-columns: 1fr auto`) treats the `.header-mobile-right` as the `auto` column.

- [ ] **Step 3: Run npm run build**

  ```
  npm run build
  ```

  Expected: build succeeds, no errors.

- [ ] **Step 4: Commit**

  ```
  git add resources/views/layouts/app.blade.php resources/css/layout/header.css
  git commit -m "feat: add mobile-only WhatsApp icon to header"
  ```

---

## Task 4: Part 3 — Client-side step validation before "Verder"

**Files:**
- Modify: `resources/views/pages/partials/request-page.blade.php`

**Strategy:**
1. Add `data-required="true"` to inputs/selects/textareas that must be validated:
   - In `fields` type steps: on the input/select/textarea where `$isRequiredField($field)` is true
   - In `airco_rooms` step: on room type select, width input, length input, attic_or_flat_roof select, large_windows select (hardcoded — room fields have no individual required config)
   - In `airco_rooms` step regular fields (airco_has_outdoor_unit, airco_house_age, preferred_time): on the select where `$isRequiredField($field)` is true (will match airco_house_age after Task 1)
2. Add `data-step-type` attribute on each `<section class="form-section">` so JS can handle special cases (not strictly required, but handy)
3. Add `validateCurrentStep()` JS function inside the existing IIFE
4. Call `validateCurrentStep()` in the `wizardVerder` click handler before advancing
5. Add a delegated `input`/`change` listener on `formCard` to clear individual JS errors as user types

**Error message:** Passed as a PHP-rendered JS variable based on `$locale`.

### Step 1: Add `data-required` to fields-type step inputs

- [ ] **Step 1a: Add `data-step-type` on the `<section>` element**

  Find the `<section class="form-section ..."` element (around line 231). Currently:
  ```html
  <section
      class="form-section {{ $isVisibleByDefault ? '' : 'is-condition-hidden' }}"
      data-step="{{ $stepIndex }}"
      data-fields="{{ $sectionFields }}"
      ...
  >
  ```

  Add `data-step-type="{{ $step['type'] ?? 'fields' }}"`:
  ```html
  <section
      class="form-section {{ $isVisibleByDefault ? '' : 'is-condition-hidden' }}"
      data-step="{{ $stepIndex }}"
      data-step-type="{{ $step['type'] ?? 'fields' }}"
      data-fields="{{ $sectionFields }}"
      ...
  >
  ```

- [ ] **Step 1b: Add `data-required` to textarea fields in `fields` type steps**

  Find the textarea rendering block (around line 430):
  ```php
  <textarea
      name="{{ $field['name'] }}"
      placeholder="{{ $getPlaceholder($field) }}"
  >{{ old($field['name']) }}</textarea>
  ```

  Change to:
  ```php
  <textarea
      name="{{ $field['name'] }}"
      placeholder="{{ $getPlaceholder($field) }}"
      @if ($isRequiredField($field)) data-required="true" @endif
  >{{ old($field['name']) }}</textarea>
  ```

- [ ] **Step 1c: Add `data-required` to select fields in `fields` type steps**

  Find the select rendering block in `fields` type steps (around line 449):
  ```php
  <select name="{{ $field['name'] }}">
  ```

  Change to:
  ```php
  <select name="{{ $field['name'] }}" @if ($isRequiredField($field)) data-required="true" @endif>
  ```

- [ ] **Step 1d: Add `data-required` to text/email/tel/number inputs in `fields` type steps**

  Find the `@else` branch input (around line 473):
  ```php
  <input
      type="{{ $field['type'] ?? 'text' }}"
      name="{{ $field['name'] }}"
      value="{{ old($field['name']) }}"
      placeholder="{{ $getPlaceholder($field) }}"
  >
  ```

  Change to:
  ```php
  <input
      type="{{ $field['type'] ?? 'text' }}"
      name="{{ $field['name'] }}"
      value="{{ old($field['name']) }}"
      placeholder="{{ $getPlaceholder($field) }}"
      @if ($isRequiredField($field)) data-required="true" @endif
  >
  ```

### Step 2: Add `data-required` + required star to `airco_rooms` step fields

- [ ] **Step 2a: Add required star and `data-required` to the airco_rooms step's regular `$step['fields']` rendering**

  Find the regular fields section inside `airco_rooms` (around line 362):
  ```php
  @foreach ($step['fields'] as $field)
      <label class="{{ $errors->has($field['name']) ? 'field-has-error' : '' }}">
          <span>{{ $getLabel($field) }}</span>
          @if (($field['type'] ?? '') === 'select')
              <select name="{{ $field['name'] }}">
                  ...
              </select>
          @else
              <input type="{{ $field['type'] ?? 'text' }}"
                     name="{{ $field['name'] }}"
                     value="{{ old($field['name']) }}"
                     placeholder="{{ $getPlaceholder($field) }}">
          @endif
          @error($field['name'])
              <p class="field-error-text">{{ $message }}</p>
          @enderror
      </label>
  @endforeach
  ```

  Replace with:
  ```php
  @foreach ($step['fields'] as $field)
      <label class="{{ $errors->has($field['name']) ? 'field-has-error' : '' }}">
          <span>
              {{ $getLabel($field) }}
              @if ($isRequiredField($field))
                  <span class="required-star">*</span>
              @endif
          </span>
          @if (($field['type'] ?? '') === 'select')
              <select name="{{ $field['name'] }}" @if ($isRequiredField($field)) data-required="true" @endif>
                  <option value="">{{ $text['choose_option'] }}</option>
                  @foreach ($field['options'] ?? [] as $option)
                      <option value="{{ $option['value'] }}"
                          {{ old($field['name']) === $option['value'] ? 'selected' : '' }}>
                          {{ $getLabel($option) }}
                      </option>
                  @endforeach
              </select>
          @else
              <input type="{{ $field['type'] ?? 'text' }}"
                     name="{{ $field['name'] }}"
                     value="{{ old($field['name']) }}"
                     placeholder="{{ $getPlaceholder($field) }}"
                     @if ($isRequiredField($field)) data-required="true" @endif>
          @endif
          @error($field['name'])
              <p class="field-error-text">{{ $message }}</p>
          @enderror
      </label>
  @endforeach
  ```

### Step 3: Add `data-required` to room entry inputs

- [ ] **Step 3a: Mark room type select as required**

  Find the room type select (around line 296):
  ```php
  <select name="rooms[{{ $ri }}][type]">
  ```
  Change to:
  ```php
  <select name="rooms[{{ $ri }}][type]" data-required="true">
  ```

- [ ] **Step 3b: Mark room width input as required**

  Find (around line 308):
  ```php
  <input type="number" name="rooms[{{ $ri }}][width]"
         value="{{ $room['width'] ?? '' }}"
         min="1" max="50" step="0.1"
         class="room-dim-input room-width">
  ```
  Add `data-required="true"`:
  ```php
  <input type="number" name="rooms[{{ $ri }}][width]"
         value="{{ $room['width'] ?? '' }}"
         min="1" max="50" step="0.1"
         class="room-dim-input room-width"
         data-required="true">
  ```

- [ ] **Step 3c: Mark room length input as required**

  Find (around line 314):
  ```php
  <input type="number" name="rooms[{{ $ri }}][length]"
         value="{{ $room['length'] ?? '' }}"
         min="1" max="50" step="0.1"
         class="room-dim-input room-length">
  ```
  Add `data-required="true"`:
  ```php
  <input type="number" name="rooms[{{ $ri }}][length]"
         value="{{ $room['length'] ?? '' }}"
         min="1" max="50" step="0.1"
         class="room-dim-input room-length"
         data-required="true">
  ```

- [ ] **Step 3d: Mark attic_or_flat_roof select as required**

  Find (around line 328):
  ```php
  <select name="rooms[{{ $ri }}][attic_or_flat_roof]">
  ```
  Change to:
  ```php
  <select name="rooms[{{ $ri }}][attic_or_flat_roof]" data-required="true">
  ```

- [ ] **Step 3e: Mark large_windows select as required**

  Find (around line 340):
  ```php
  <select name="rooms[{{ $ri }}][large_windows]">
  ```
  Change to:
  ```php
  <select name="rooms[{{ $ri }}][large_windows]" data-required="true">
  ```

  **Note on cloning:** When new rooms are added via `addBtn`, the entry is cloned via `cloneNode(true)`. `data-required="true"` is preserved on cloned DOM attributes. ✓

### Step 4: Add `validateCurrentStep()` JS + wire to Verder

- [ ] **Step 4a: Inject the locale-specific error message variable at the top of the `<script>` block**

  Find the `<script>` opening (around line 575):
  ```js
  (function () {
      'use strict';

      // ── DOM refs ─────────────────────────────────────────────────────────────
  ```

  Add the error message variable after `'use strict';`:
  ```js
  'use strict';

  var stepFieldErrorMsg = @json(
      $locale === 'fr' ? 'Veuillez remplir ce champ pour continuer.' :
      ($locale === 'en' ? 'Please fill in this field to continue.' :
      'Vul dit veld in om verder te gaan.')
  );
  ```

- [ ] **Step 4b: Add `validateCurrentStep()` and helper functions**

  Add the following functions into the IIFE, right before the `// ── Init ──` comment (around line 883). Insert after the `findErrorStep` function:

  ```js
  // ── Client-side step validation ───────────────────────────────────────────
  function isFieldFilled(field) {
      var tag = field.tagName.toLowerCase();
      if (tag === 'select') return field.value !== '';
      if (field.type === 'checkbox') return field.checked;
      return field.value.trim() !== '';
  }

  function addFieldError(field) {
      var label = field.closest('label');
      if (!label) return;
      label.classList.add('field-has-error', 'js-has-error');
      if (!label.querySelector('.js-field-error')) {
          var msg = document.createElement('p');
          msg.className = 'field-error-text js-field-error';
          msg.textContent = stepFieldErrorMsg;
          label.appendChild(msg);
      }
  }

  function clearStepErrors(section) {
      section.querySelectorAll('.js-field-error').forEach(function (el) { el.remove(); });
      section.querySelectorAll('.js-has-error').forEach(function (el) {
          el.classList.remove('js-has-error', 'field-has-error');
      });
  }

  function validateCurrentStep() {
      var section = visibleSections[currentIndex];
      if (!section) return true;

      clearStepErrors(section);

      var requiredFields = Array.from(section.querySelectorAll('[data-required="true"]'));
      var firstInvalid = null;
      var valid = true;

      requiredFields.forEach(function (field) {
          if (!isFieldFilled(field)) {
              addFieldError(field);
              if (!firstInvalid) firstInvalid = field;
              valid = false;
          }
      });

      if (firstInvalid) {
          firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
          firstInvalid.focus();
      }

      return valid;
  }
  ```

- [ ] **Step 4c: Wire `validateCurrentStep()` into the Verder click handler**

  Find the Verder click handler (around line 715):
  ```js
  wizardVerder.addEventListener('click', function () {
      if (currentIndex < visibleSections.length - 1) {
          showStep(visibleSections, currentIndex + 1);
      }
  });
  ```

  Replace with:
  ```js
  wizardVerder.addEventListener('click', function () {
      if (currentIndex === 0) {
          // Step 0: category selection — Verder is already disabled until selection; just advance
          if (currentIndex < visibleSections.length - 1) {
              showStep(visibleSections, currentIndex + 1);
          }
          return;
      }
      if (!validateCurrentStep()) return;
      if (currentIndex < visibleSections.length - 1) {
          showStep(visibleSections, currentIndex + 1);
      }
  });
  ```

- [ ] **Step 4d: Add delegated listener to clear individual field errors on user input**

  Add this block right after the Verder click handler (after the Terug handler):

  ```js
  // Auto-clear JS field error when user fills in the field
  if (formCard) {
      formCard.addEventListener('input', function (e) {
          var field = e.target;
          if (!field.hasAttribute('data-required')) return;
          if (!isFieldFilled(field)) return;
          var label = field.closest('label');
          if (!label) return;
          var err = label.querySelector('.js-field-error');
          if (err) err.remove();
          label.classList.remove('js-has-error', 'field-has-error');
      });

      formCard.addEventListener('change', function (e) {
          var field = e.target;
          if (!field.hasAttribute('data-required')) return;
          if (!isFieldFilled(field)) return;
          var label = field.closest('label');
          if (!label) return;
          var err = label.querySelector('.js-field-error');
          if (err) err.remove();
          label.classList.remove('js-has-error', 'field-has-error');
      });
  }
  ```

- [ ] **Step 4e: Commit Blade changes**

  ```
  git add resources/views/pages/partials/request-page.blade.php
  git commit -m "feat: add client-side step validation before Verder + data-required attributes"
  ```

---

## Task 5: Build, test, verify

- [ ] **Step 1: Run npm build**

  ```
  npm run build
  ```
  Expected: exits 0, no errors.

- [ ] **Step 2: Run PHP tests**

  ```
  php artisan test
  ```
  Expected: all tests pass (or only pre-existing failures).

- [ ] **Step 3: Final commit (if any uncommitted changes)**

  ```
  git status
  ```
  Commit anything remaining.

---

## Self-Review: Spec Coverage

| Spec requirement | Task covering it |
|-----------------|-----------------|
| Extra rooms removable | Task 2 |
| First/only room not removable | Task 2 (renumber hides btn at idx=0) |
| Room renumbering correct after remove | Task 2 (renumber already correct) |
| Input names updated after remove | Task 2 (renumber already correct) |
| Surface calc survives remove | Task 2 (delegated listener survives) |
| Old input recovery after remove | Task 2 (Blade renders old rooms correctly; renumber runs on init) |
| airco_house_age required (config) | Task 1 |
| airco_house_age required (controller) | Task 1 (inherits from config via buildRulesForField) |
| airco_house_age options: yes/no/unknown | No change — already in config |
| Mobile WhatsApp icon | Task 3 |
| Desktop header unchanged | Task 3 (display:contents wrapper; btn display:none on desktop) |
| WhatsApp deep link format | Task 3 (uses config whatsapp_link = 32495121178) |
| Accessible WhatsApp aria-label (nl/fr/en) | Task 3 |
| Mobile breakpoint 680px | Task 3 |
| validateCurrentStep on Verder | Task 4 |
| Step 0 keeps existing disabled-button behavior | Task 4 (early return for idx=0, no validation) |
| Airco room fields validated (type, width, length, attic, windows, house_age) | Task 4 (data-required on those inputs) |
| Required fields in other steps validated | Task 4 (data-required via $isRequiredField) |
| Error shows near field | Task 4 (addFieldError appends to label) |
| Scroll/focus to first invalid | Task 4 (scrollIntoView + focus) |
| Error clears automatically on fix | Task 4 (delegated input/change listener) |
| Error clears on next validation | Task 4 (clearStepErrors at start of validateCurrentStep) |
| Server-side validation unchanged | No server changes beyond config required flag |
| nl/fr/en error messages | Task 4 (PHP-rendered stepFieldErrorMsg) |
| No innerHTML with user data | All JS uses textContent / classList ✓ |
| novalidate not needed | No native HTML required attributes added ✓ |
