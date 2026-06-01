# Wizard Polish: Submit Fix, Brand/Model Validation, Summary — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Fix the submit bug (brand/model not validated client-side causing silent server rejection), add clear brand/model-or-unknown UX, style the unknown checkbox as a helper card, build a real live summary on the final step, and add an in-summary submit button.

**Architecture:** All changes are confined to `request-page.blade.php` (Blade template + inline JS) and `request.css` (visual styles). No DB, no routes, no controller logic changes — the server-side brand/model rule (`required` unless `unknown_device_details` checked) already exists in `CustomerRequestController::buildRulesForField()` and is correct. `novalidate` is added to the form so the multi-step JS validation controls UX; Laravel is still the source of truth on submit.

**Tech Stack:** Laravel 12 Blade, vanilla ES5 JS (IIFE in Blade), CSS. No new dependencies.

---

## Root cause of submit bug

`brand` and `device_model` have `required: false` in config → no `data-required="true"` → no client-side block on "Verder". User reaches the summary step without filling them. On form submit, the server validates both as required (unless `unknown_device_details` is checked). Server rejects with validation errors. User sees the form reset to the technical step with "brand is required" errors. This appears as "cannot submit."

Secondary issue: the form has no `novalidate` attribute. `type="email"` and `type="number"` with min/max may trigger browser-native validation popups on some browsers, which also prevents submit.

---

## File Map

| File | What changes |
|------|-------------|
| `resources/views/pages/partials/request-page.blade.php` | (a) Add `novalidate` to `<form>`; (b) Add new label keys to `$labels` array; (c) Special rendering for `unknown_device_details` field; (d) Update `addFieldError()` to accept custom message; (e) Add brand/model validation to `validateCurrentStep()`; (f) Add `summaryLabels` JS object; (g) Add `updateSummary()` JS function; (h) Call `updateSummary()` in `showStep()` when on last step; (i) Add in-summary submit button + `wizardSubmitTop` DOM ref; (j) Add `wizardSummaryContent` div in summary section |
| `resources/css/pages/request.css` | (a) Add `.checkbox-helper-card` styles; (b) Add `.wizard-summary-content`, `.summary-section`, `.summary-row` styles; (c) Add `.summary-submit-wrap` styles |

---

## Task 1: Add `novalidate` + fix form submit

**Files:**
- Modify: `resources/views/pages/partials/request-page.blade.php` (line ~177)

- [ ] **Step 1: Add `novalidate` to the `<form>` element**

  Find (approx. line 177):
  ```html
  <form
      method="POST"
      action="{{ route('customer-requests.store', ['locale' => $locale]) }}"
      enctype="multipart/form-data"
  >
  ```
  Replace with:
  ```html
  <form
      method="POST"
      action="{{ route('customer-requests.store', ['locale' => $locale]) }}"
      enctype="multipart/form-data"
      novalidate
  >
  ```

  **Why `novalidate`:** The wizard uses per-step JS validation on "Verder" (not on final submit), and Laravel handles server-side validation on submit. Browser-native HTML5 validation (triggered by `type="email"` format check, `type="number"` min/max, etc.) would show unexpected popups and block the submit button before our UX has a chance to respond. `novalidate` disables browser popups while keeping Laravel validation fully intact.

- [ ] **Step 2: Commit**

  ```
  git add resources/views/pages/partials/request-page.blade.php
  git commit -m "fix: add novalidate to wizard form — step-validation and Laravel are the sources of truth"
  ```

---

## Task 2: Add new multilingual labels

**Files:**
- Modify: `resources/views/pages/partials/request-page.blade.php` (the `$labels` PHP array, ~lines 39–133)

New keys to add to each locale (nl/fr/en):

| Key | nl | fr | en |
|-----|-----|-----|-----|
| `unknown_device_helper` | Geen probleem, dan bekijken we dit op basis van uw beschrijving en foto's. | Pas de problème, nous nous baserons sur votre description et vos photos. | No problem, we'll assess this based on your description and photos. |
| `brand_model_error` | Vul merk en model in, of vink aan dat u dit niet weet. | Indiquez la marque et le modèle, ou cochez que vous ne les connaissez pas. | Enter the brand and model, or check that you don't know them. |
| `summary_service` | Dienst | Service | Service |
| `summary_rooms` | Kamers | Pièces | Rooms |
| `summary_customer` | Klant en urgentie | Client et urgence | Customer and urgency |
| `summary_description` | Beschrijving | Description | Description |
| `summary_technical` | Technische gegevens | Informations techniques | Technical details |
| `summary_location` | Locatie | Lieu | Location |
| `summary_contact` | Contact | Contact | Contact |
| `summary_files` | Bijlagen | Pièces jointes | Attachments |
| `summary_files_count` | {n} bestand(en) geselecteerd | {n} fichier(s) sélectionné(s) | {n} file(s) selected |
| `technical_unknown_label` | Merk/model/serienummer niet gekend | Marque/modèle/numéro de série inconnus | Brand/model/serial number unknown |

- [ ] **Step 1: Add new keys to `nl` section** (after `'room_windows_label'`)

  ```php
  'unknown_device_helper'  => 'Geen probleem, dan bekijken we dit op basis van uw beschrijving en foto\'s.',
  'brand_model_error'      => 'Vul merk en model in, of vink aan dat u dit niet weet.',
  'summary_service'        => 'Dienst',
  'summary_rooms'          => 'Kamers',
  'summary_customer'       => 'Klant en urgentie',
  'summary_description'    => 'Beschrijving',
  'summary_technical'      => 'Technische gegevens',
  'summary_location'       => 'Locatie',
  'summary_contact'        => 'Contact',
  'summary_files'          => 'Bijlagen',
  'summary_files_count'    => '{n} bestand(en) geselecteerd',
  'technical_unknown_label'=> 'Merk/model/serienummer niet gekend',
  ```

- [ ] **Step 2: Add new keys to `fr` section** (after `'room_windows_label'`)

  ```php
  'unknown_device_helper'  => 'Pas de problème, nous nous baserons sur votre description et vos photos.',
  'brand_model_error'      => 'Indiquez la marque et le modèle, ou cochez que vous ne les connaissez pas.',
  'summary_service'        => 'Service',
  'summary_rooms'          => 'Pièces',
  'summary_customer'       => 'Client et urgence',
  'summary_description'    => 'Description',
  'summary_technical'      => 'Informations techniques',
  'summary_location'       => 'Lieu',
  'summary_contact'        => 'Contact',
  'summary_files'          => 'Pièces jointes',
  'summary_files_count'    => '{n} fichier(s) sélectionné(s)',
  'technical_unknown_label'=> 'Marque/modèle/numéro de série inconnus',
  ```

- [ ] **Step 3: Add new keys to `en` section** (after `'room_windows_label'`)

  ```php
  'unknown_device_helper'  => 'No problem, we\'ll assess this based on your description and photos.',
  'brand_model_error'      => 'Enter the brand and model, or check that you don\'t know them.',
  'summary_service'        => 'Service',
  'summary_rooms'          => 'Rooms',
  'summary_customer'       => 'Customer and urgency',
  'summary_description'    => 'Description',
  'summary_technical'      => 'Technical details',
  'summary_location'       => 'Location',
  'summary_contact'        => 'Contact',
  'summary_files'          => 'Attachments',
  'summary_files_count'    => '{n} file(s) selected',
  'technical_unknown_label'=> 'Brand/model/serial number unknown',
  ```

- [ ] **Step 4: Commit**

  ```
  git add resources/views/pages/partials/request-page.blade.php
  git commit -m "feat: add multilingual labels for brand/model validation and summary"
  ```

---

## Task 3: Brand/model OR unknown — client-side validation

**Files:**
- Modify: `resources/views/pages/partials/request-page.blade.php` (JS section — `addFieldError`, `validateCurrentStep`, auto-clear listener)

**Context:** `unknown_device_details` checkbox is in the `technical_details` step (step 7, always shown). `brand` and `device_model` are text inputs in the same step. `serial_number` stays optional (server-side it's nullable).

- [ ] **Step 1: Update `addFieldError()` to accept optional custom message**

  Find (approx. line 943):
  ```js
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
  ```
  Replace with:
  ```js
  function addFieldError(field, message) {
      var label = field.closest('label');
      if (!label) return;
      label.classList.add('field-has-error', 'js-has-error');
      if (!label.querySelector('.js-field-error')) {
          var msg = document.createElement('p');
          msg.className = 'field-error-text js-field-error';
          msg.textContent = message || stepFieldErrorMsg;
          label.appendChild(msg);
      }
  }
  ```

- [ ] **Step 2: Add `brandModelErrorMsg` variable after `stepFieldErrorMsg`**

  After the `stepFieldErrorMsg` block (approx. line 929), add:
  ```js
  var brandModelErrorMsg = @json(
      $locale === 'fr' ? 'Indiquez la marque et le modèle, ou cochez que vous ne les connaissez pas.' :
      ($locale === 'en' ? 'Enter the brand and model, or check that you don\'t know them.' :
      'Vul merk en model in, of vink aan dat u dit niet weet.')
  );
  ```

- [ ] **Step 3: Add brand/model validation block inside `validateCurrentStep()`**

  Find (approx. line 985), right before `return valid;` at the end of `validateCurrentStep()`:
  ```js
      if (firstInvalid) {
          firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
          try { firstInvalid.focus(); } catch (e) {}
      }

      return valid;
  }
  ```
  Replace with:
  ```js
      // Brand/model OR unknown check (technical_details step)
      var unknownCb = section.querySelector('input[name="unknown_device_details"]');
      if (unknownCb && !unknownCb.checked) {
          var brandInput  = section.querySelector('input[name="brand"]');
          var modelInput  = section.querySelector('input[name="device_model"]');
          if (brandInput && !isFieldFilled(brandInput)) {
              addFieldError(brandInput, brandModelErrorMsg);
              if (!firstInvalid) firstInvalid = brandInput;
              valid = false;
          }
          if (modelInput && !isFieldFilled(modelInput)) {
              addFieldError(modelInput, brandModelErrorMsg);
              if (!firstInvalid) firstInvalid = modelInput;
              valid = false;
          }
      }

      if (firstInvalid) {
          firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
          try { firstInvalid.focus(); } catch (e) {}
      }

      return valid;
  }
  ```

- [ ] **Step 4: Add `change` listener for `unknown_device_details` inside the `formCard` delegated handler**

  Find the existing auto-clear `change` listener block (approx. lines 756–765):
  ```js
      formCard.addEventListener('change', function (e) {
          var field = e.target;
          if (!field.dataset || field.dataset.required !== 'true') return;
          if (!isFieldFilled(field)) return;
          var label = field.closest('label');
          if (!label) return;
          var err = label.querySelector('.js-field-error');
          if (err) err.remove();
          label.classList.remove('js-has-error', 'field-has-error');
      });
  ```
  Replace with:
  ```js
      formCard.addEventListener('change', function (e) {
          var field = e.target;
          // When unknown_device_details is checked, clear brand/model errors
          if (field.name === 'unknown_device_details') {
              if (field.checked) {
                  var sec = field.closest('.form-section');
                  if (sec) {
                      ['input[name="brand"]', 'input[name="device_model"]'].forEach(function (sel) {
                          var inp = sec.querySelector(sel);
                          if (!inp) return;
                          var lbl = inp.closest('label');
                          if (!lbl) return;
                          var err = lbl.querySelector('.js-field-error');
                          if (err) err.remove();
                          lbl.classList.remove('js-has-error', 'field-has-error');
                      });
                  }
              }
              return;
          }
          if (!field.dataset || field.dataset.required !== 'true') return;
          if (!isFieldFilled(field)) return;
          var label = field.closest('label');
          if (!label) return;
          var err = label.querySelector('.js-field-error');
          if (err) err.remove();
          label.classList.remove('js-has-error', 'field-has-error');
      });
  ```

- [ ] **Step 5: Commit**

  ```
  git add resources/views/pages/partials/request-page.blade.php
  git commit -m "feat: add brand/model-or-unknown client-side validation in technical details step"
  ```

---

## Task 4: Style the unknown checkbox as a helper card

**Files:**
- Modify: `resources/views/pages/partials/request-page.blade.php` (Blade section, the `fields` checkbox rendering, ~line 412)
- Modify: `resources/css/pages/request.css`

**Context:** The `unknown_device_details` field renders as a `checkbox` type in the `fields` type step rendering. Currently uses the generic `checkbox-field` label class.

- [ ] **Step 1: Update checkbox rendering in Blade — detect `unknown_device_details` and apply helper card class**

  Find the checkbox rendering block (approx. line 412):
  ```php
  @if (($field['type'] ?? '') === 'checkbox')
      <label class="checkbox-field {{ $errors->has($field['name']) ? 'field-has-error' : '' }}">
          <input
              type="checkbox"
              name="{{ $field['name'] }}"
              value="1"
              {{ old($field['name']) ? 'checked' : '' }}
          >

          <span>{{ $getLabel($field) }}</span>

          @error($field['name'])
              <p class="field-error-text">{{ $message }}</p>
          @enderror
      </label>
  ```
  Replace with:
  ```php
  @if (($field['type'] ?? '') === 'checkbox')
      @php $isUnknownDevice = $field['name'] === 'unknown_device_details'; @endphp
      <label class="{{ $isUnknownDevice ? 'checkbox-helper-card' : 'checkbox-field' }} {{ $errors->has($field['name']) ? 'field-has-error' : '' }}">
          <input
              type="checkbox"
              name="{{ $field['name'] }}"
              value="1"
              {{ old($field['name']) ? 'checked' : '' }}
          >

          <span class="{{ $isUnknownDevice ? 'checkbox-helper-card-text' : '' }}">
              {{ $getLabel($field) }}
              @if ($isUnknownDevice)
                  <small class="checkbox-helper-hint">{{ $text['unknown_device_helper'] }}</small>
              @endif
          </span>

          @error($field['name'])
              <p class="field-error-text">{{ $message }}</p>
          @enderror
      </label>
  ```

- [ ] **Step 2: Add CSS for `.checkbox-helper-card` in `resources/css/pages/request.css`**

  Append after the `.button-small` rule (end of the Room manager section, approx. line 648):

  ```css
  /* ================================
     Unknown device helper card
  ================================ */

  .checkbox-helper-card {
      display: flex !important;
      align-items: flex-start;
      gap: 14px;
      padding: 16px 18px;
      border: 1.5px solid var(--color-border);
      border-radius: 16px;
      background: #f0f7ff;
      cursor: pointer;
      grid-column: 1 / -1;
      transition: border-color 0.15s ease, background 0.15s ease;
  }

  .checkbox-helper-card:hover {
      border-color: rgba(15, 102, 194, 0.35);
      background: #e8f2ff;
  }

  .checkbox-helper-card input[type="checkbox"] {
      flex-shrink: 0;
      width: 18px;
      height: 18px;
      margin-top: 3px;
      accent-color: var(--color-primary);
  }

  .checkbox-helper-card-text {
      display: flex;
      flex-direction: column;
      gap: 4px;
      font-weight: 800;
      color: var(--color-primary-dark);
  }

  .checkbox-helper-hint {
      display: block;
      font-size: 0.83rem;
      font-weight: 500;
      color: var(--color-muted);
      line-height: 1.45;
  }
  ```

- [ ] **Step 3: Commit**

  ```
  git add resources/views/pages/partials/request-page.blade.php resources/css/pages/request.css
  git commit -m "feat: style unknown device checkbox as helper card with hint text"
  ```

---

## Task 5: Summary overview + in-summary submit

**Files:**
- Modify: `resources/views/pages/partials/request-page.blade.php` (summary Blade section + JS)
- Modify: `resources/css/pages/request.css` (summary styles)

### Part A — Blade: Replace static summary content with live container

- [ ] **Step 1: Replace the summary section Blade content**

  Find (approx. line 535):
  ```php
  @elseif (($step['type'] ?? '') === 'summary')
      <div class="request-summary-box">
          <h2>{{ $text['estimate_title'] }}</h2>
          <p>{{ $text['estimate_text'] }}</p>
      </div>

      <div class="summary-card">
          <h3>{{ $text['summary_title'] }}</h3>
          <p>{{ $text['summary_text'] }}</p>
      </div>
  ```
  Replace with:
  ```php
  @elseif (($step['type'] ?? '') === 'summary')
      <h2>{{ $text['summary_title'] }}</h2>

      <div class="summary-submit-wrap">
          <button type="submit" id="wizardSubmitTop"
                  class="button button-primary button-large"
                  data-label-general="{{ $text['submit'] }}"
                  data-label-offerte="{{ $text['submit_offerte'] }}">
              {{ $text['submit'] }}
          </button>
      </div>

      <div id="wizardSummaryContent" class="wizard-summary-content"></div>

      <div class="request-summary-box" style="margin-top: 20px;">
          <h3>{{ $text['estimate_title'] }}</h3>
          <p>{{ $text['estimate_text'] }}</p>
      </div>
  ```

### Part B — JS: Add `summaryLabels` and `updateSummary()`

- [ ] **Step 2: Add `wizardSubmitTop` DOM ref**

  Find the DOM refs block at the top of the IIFE (approx. line 588):
  ```js
  var wizardSubmit   = document.getElementById('wizardSubmit');
  ```
  Add directly after it:
  ```js
  var wizardSubmitTop = document.getElementById('wizardSubmitTop');
  ```

- [ ] **Step 3: Update `showStep()` submit label sync to also update `wizardSubmitTop`**

  Find (approx. line 681):
  ```js
  // Submit label (airco offerte → different CTA)
  var cat = getSelectedCategory();
  wizardSubmit.textContent = (cat === 'airco_offerte')
      ? (wizardSubmit.dataset.labelOfferte || 'Vraag mijn offerte aan')
      : (wizardSubmit.dataset.labelGeneral || 'Verstuur mijn aanvraag');
  ```
  Replace with:
  ```js
  // Submit label (airco offerte → different CTA)
  var cat = getSelectedCategory();
  var submitLabel = (cat === 'airco_offerte')
      ? (wizardSubmit.dataset.labelOfferte || 'Vraag mijn offerte aan')
      : (wizardSubmit.dataset.labelGeneral || 'Verstuur mijn aanvraag');
  wizardSubmit.textContent = submitLabel;
  if (wizardSubmitTop) wizardSubmitTop.textContent = submitLabel;

  // Update summary when landing on last step
  if (isLast) { updateSummary(); }
  ```

- [ ] **Step 4: Add `summaryLabels` JS object (PHP-rendered) before the `// ── Init` comment**

  Add this block right before `// ── Init ──` (approx. line 988):
  ```js
  // ── Summary labels ────────────────────────────────────────────────────────
  var summaryLabels = @json([
      'service'          => $text['summary_service'],
      'rooms'            => $text['summary_rooms'],
      'customer'         => $text['summary_customer'],
      'description'      => $text['summary_description'],
      'technical'        => $text['summary_technical'],
      'location'         => $text['summary_location'],
      'contact'          => $text['summary_contact'],
      'files'            => $text['summary_files'],
      'filesCount'       => $text['summary_files_count'],
      'techUnknown'      => $text['technical_unknown_label'],
      'roomLabel'        => $text['room_label'],
      'atticLabel'       => $text['room_attic_label'],
      'windowsLabel'     => $text['room_windows_label'],
  ]);
  ```

- [ ] **Step 5: Add `updateSummary()` function right after `summaryLabels`**

  ```js
  // ── Summary renderer ──────────────────────────────────────────────────────
  function updateSummary() {
      var container = document.getElementById('wizardSummaryContent');
      if (!container) return;

      // Clear
      while (container.firstChild) { container.removeChild(container.firstChild); }

      var form     = container.closest('form');
      var category = getSelectedCategory();

      function qVal(name) {
          var el = form ? form.querySelector('[name="' + name + '"]') : null;
          return el ? el.value.trim() : '';
      }

      function qSelectText(name) {
          var el = form ? form.querySelector('select[name="' + name + '"]') : null;
          if (!el || !el.value) return '';
          var opt = Array.from(el.options).find(function (o) { return o.value === el.value; });
          return opt ? opt.text : '';
      }

      function addSection(titleText, items) {
          var filtered = items.filter(function (it) { return it.value; });
          if (!filtered.length) return;
          var sec = document.createElement('div');
          sec.className = 'summary-section';
          var h = document.createElement('h4');
          h.textContent = titleText;
          sec.appendChild(h);
          filtered.forEach(function (it) {
              var row = document.createElement('p');
              row.className = 'summary-row';
              if (it.label) {
                  var lbl = document.createElement('span');
                  lbl.className = 'summary-row-label';
                  lbl.textContent = it.label + ': ';
                  row.appendChild(lbl);
              }
              var val = document.createElement('span');
              val.className = 'summary-row-value';
              val.textContent = it.value;
              row.appendChild(val);
              sec.appendChild(row);
          });
          if (sec.querySelectorAll('p').length > 0) {
              container.appendChild(sec);
          }
      }

      // 1. Service
      var catRadio = form ? form.querySelector('input[name="service_category"]:checked') : null;
      if (catRadio) {
          var catLabel = catRadio.closest('label');
          var catSpan  = catLabel ? catLabel.querySelector('.option-card-label') : null;
          var catText  = catSpan ? catSpan.textContent.trim() : catRadio.value;
          addSection(summaryLabels.service, [{ value: catText }]);
      }

      // 2. Rooms (airco_offerte only)
      if (category === 'airco_offerte') {
          var roomEntries = document.querySelectorAll('.room-entry');
          var roomItems = [];
          roomEntries.forEach(function (entry, i) {
              var typeEl    = entry.querySelector('select[name*="[type]"]');
              var surfaceEl = entry.querySelector('.room-surface');
              var atticEl   = entry.querySelector('select[name*="[attic_or_flat_roof]"]');
              var windowsEl = entry.querySelector('select[name*="[large_windows]"]');
              var parts = [];
              if (typeEl && typeEl.value) {
                  var tOpt = Array.from(typeEl.options).find(function (o) { return o.value === typeEl.value; });
                  if (tOpt) parts.push(tOpt.text);
              }
              if (surfaceEl && surfaceEl.value) parts.push(surfaceEl.value + ' m²');
              if (atticEl && atticEl.value) {
                  var aOpt = Array.from(atticEl.options).find(function (o) { return o.value === atticEl.value; });
                  if (aOpt) parts.push(summaryLabels.atticLabel + ': ' + aOpt.text);
              }
              if (windowsEl && windowsEl.value) {
                  var wOpt = Array.from(windowsEl.options).find(function (o) { return o.value === windowsEl.value; });
                  if (wOpt) parts.push(summaryLabels.windowsLabel + ': ' + wOpt.text);
              }
              if (parts.length) {
                  roomItems.push({ label: summaryLabels.roomLabel + ' ' + (i + 1), value: parts.join(' · ') });
              }
          });
          var outdoorText = qSelectText('airco_has_outdoor_unit');
          var houseAgeText = qSelectText('airco_house_age');
          var timingText = qVal('preferred_time');
          if (outdoorText) roomItems.push({ label: 'Buitenunit', value: outdoorText });
          if (houseAgeText) roomItems.push({ label: 'Woning > 10 jaar', value: houseAgeText });
          if (timingText) roomItems.push({ label: 'Timing', value: timingText });
          addSection(summaryLabels.rooms, roomItems);
      }

      // 3. Customer / urgency
      addSection(summaryLabels.customer, [
          { label: 'Type', value: qSelectText('customer_type') },
          { label: 'Urgentie', value: qSelectText('urgency') },
      ]);

      // 4. Description
      var descVal = qVal('description');
      if (descVal) {
          addSection(summaryLabels.description, [{ value: descVal.length > 200 ? descVal.slice(0, 197) + '…' : descVal }]);
      }

      // 5. Technical details
      var unknownCb = form ? form.querySelector('input[name="unknown_device_details"]') : null;
      if (unknownCb && unknownCb.checked) {
          addSection(summaryLabels.technical, [{ value: summaryLabels.techUnknown }]);
      } else {
          addSection(summaryLabels.technical, [
              { label: 'Merk',        value: qVal('brand') },
              { label: 'Model',       value: qVal('device_model') },
              { label: 'Serienummer', value: qVal('serial_number') },
          ]);
      }

      // 6. Location
      var street  = qVal('street');
      var postal  = qVal('postal_code');
      var city    = qVal('city');
      var address = [street, postal, city].filter(Boolean).join(', ');
      addSection(summaryLabels.location, [
          { value: address },
          { label: 'Beschikbaarheid', value: qVal('availability') },
      ]);

      // 7. Contact
      addSection(summaryLabels.contact, [
          { value: qVal('customer_name') },
          { value: qVal('customer_email') },
          { value: qVal('customer_phone') },
      ]);

      // 8. Files
      var fileItems = document.querySelectorAll('.selected-attachment-item');
      if (fileItems.length > 0) {
          var countText = summaryLabels.filesCount.replace('{n}', fileItems.length);
          addSection(summaryLabels.files, [{ value: countText }]);
      }
  }
  ```

### Part C — CSS: Add summary + submit-wrap styles

- [ ] **Step 6: Add CSS to `resources/css/pages/request.css`** (append to end of file before final `@media (max-width: 330px)` block)

  ```css
  /* ================================
     Wizard summary overview
  ================================ */

  .summary-submit-wrap {
      display: flex;
      justify-content: flex-end;
      margin: 0 0 20px;
  }

  .wizard-summary-content {
      display: flex;
      flex-direction: column;
      gap: 14px;
  }

  .summary-section {
      padding: 16px 18px;
      border: 1px solid var(--color-border);
      border-radius: 16px;
      background: #f8fbff;
  }

  .summary-section h4 {
      margin: 0 0 10px;
      font-size: 0.85rem;
      font-weight: 900;
      text-transform: uppercase;
      letter-spacing: 0.04em;
      color: var(--color-primary);
  }

  .summary-row {
      margin: 0 0 5px;
      font-size: 0.95rem;
      color: var(--color-text);
      line-height: 1.45;
  }

  .summary-row:last-child {
      margin-bottom: 0;
  }

  .summary-row-label {
      font-weight: 800;
      color: var(--color-primary-dark);
  }

  .summary-row-value {
      color: var(--color-text);
  }
  ```

- [ ] **Step 7: Commit**

  ```
  git add resources/views/pages/partials/request-page.blade.php resources/css/pages/request.css
  git commit -m "feat: add live summary overview and in-summary submit button"
  ```

---

## Task 6: Build + test + verify

- [ ] **Step 1: Run build**

  ```
  npm run build
  ```
  Expected: exits 0, no errors.

- [ ] **Step 2: Run tests**

  ```
  php artisan test
  ```
  Expected: 2 passed (3 assertions).

- [ ] **Step 3: Final commit (if any uncommitted changes remain)**

  ```
  git add -A
  git commit -m "fix: improve technical details validation and request summary"
  ```

---

## Self-Review: Spec Coverage

| Spec requirement | Task covering it |
|-----------------|-----------------|
| Brand/model required unless unknown checked (client) | Task 3 |
| Brand/model required unless unknown checked (server) | Already correct in controller — no change needed |
| Serial optional | No change needed (already optional client + server) |
| Clear error message for brand/model | Task 2 (new label key) + Task 3 |
| Checkbox clears brand/model errors immediately | Task 3 (change listener) |
| Style unknown checkbox as helper card | Task 4 |
| Helper hint text under checkbox | Task 4 |
| Real summary on final step | Task 5 |
| Summary updates when entering final step | Task 5 (called in showStep when isLast) |
| In-summary submit button | Task 5 |
| Submit button CTA label (airco_offerte vs other) | Task 5 (synced in showStep) |
| Submit bug fixed | Task 1 (novalidate) + Task 3 (client-side brand/model blocks before reaching submit) |
| `novalidate` with explanation | Task 1 |
| Hidden fields not blocking submit | Task 1 (novalidate + `validateCurrentStep` already scoped to current section) |
| nl/fr/en throughout | Task 2 (labels) + Tasks 3/4/5 (all use `$text` or `@json`) |
| No innerHTML with user data | Task 5 (all `textContent` in `updateSummary`) |
| Server-side validation unchanged | Confirmed — no controller changes in this plan |
