# Sprint 3 — Copy/UI Polish, GDPR Audit, SEO/Copy Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Polish the Smart Request Flow UX copy, add practical GDPR privacy notices, and improve SEO meta tags for the Mastechnics multilingual Laravel 12 site.

**Architecture:** All copy changes live in `config/request-flow.php` (option data) and the Blade partials (labels/copy). CSS changes only for the new description sub-label in option cards. GDPR fixes are small notices inline. SEO improvements are meta tag additions in the layout and optional OG tags.

**Tech Stack:** Laravel 12, Blade templates, Vite/CSS, PowerShell on Windows.

---

## Sprint 3A — Copy/UI Polish

### Task 3A-1: Update request-flow.php step 0 title, helper text, option labels, and descriptions

**Files:**
- Modify: `config/request-flow.php` (step 0 section, lines 20–111, and service_categories labels lines 913–1014)

- [ ] **Step 1: Change step 0 title in all locales**

In `config/request-flow.php`, step 0 `labels`:
```php
'labels' => [
    'nl' => 'Waarmee kunnen we u helpen?',
    'fr' => 'Comment pouvons-nous vous aider ?',
    'en' => 'How can we help you?',
],
```

- [ ] **Step 2: Add `helper_text` key to step 0**

After `labels`, add:
```php
'helper_text' => [
    'nl' => 'Kies wat het best past. Twijfelt u? Kies \'Ik weet het niet\', dan bekijken we het voor u.',
    'fr' => 'Choisissez ce qui correspond le mieux. Vous hésitez ? Choisissez \'Je ne sais pas\', nous verrons cela pour vous.',
    'en' => 'Choose what fits best. Not sure? Choose \'I\'m not sure\', we will look into it for you.',
],
```

- [ ] **Step 3: Update option labels and add descriptions for all 10 options**

Replace the options array in step 0 with updated labels + descriptions for nl/fr/en.
See Task 3A-1 execution note for full option values.

- [ ] **Step 4: Verify config PHP syntax**

Run: `php artisan config:clear`
Expected: no errors.

### Task 3A-2: Update Blade to render helper_text and option descriptions

**Files:**
- Modify: `resources/views/pages/partials/request-page.blade.php`

- [ ] **Step 1: Add helper text rendering under step 0 h2**

After `<h2>{{ $getLabel($step) }}</h2>` for the service_category_selection type, add:
```blade
@if (isset($step['helper_text']))
    <p class="step-helper-text">{{ $step['helper_text'][$locale] ?? $step['helper_text']['nl'] }}</p>
@endif
```

- [ ] **Step 2: Add description to option cards**

Change the option-card span to include description:
```blade
<span class="option-card-label">{{ $getLabel($option) }}</span>
@if (isset($option['description']))
    <span class="option-card-desc">{{ $option['description'][$locale] ?? $option['description']['nl'] }}</span>
@endif
```

### Task 3A-3: Update CSS for option-card descriptions and step helper text

**Files:**
- Modify: `resources/css/pages/request.css`

- [ ] **Step 1: Make option-card a column layout and add description styles**

Update `.option-card` to flex-direction: column and add `.option-card-label`, `.option-card-desc`.

- [ ] **Step 2: Add `.step-helper-text` style**

Small muted text below the step title.

- [ ] **Step 3: Run build**

Run: `npm run build`
Expected: no errors.

- [ ] **Step 4: Commit Sprint 3A**

```
git commit -m "feat(3a): improve step-0 copy, option labels, helper descriptions, and card layout"
```

---

## Sprint 3B — GDPR/Privacy Audit + Fixes

### Task 3B-1: Audit and implement small privacy fixes

**Files:**
- Modify: `resources/views/pages/partials/request-page.blade.php` (privacy notice near submit)
- Modify: `resources/views/layouts/app.blade.php` (privacy link in footer)

- [ ] **Step 1: Add small privacy notice near the form submit button**

Before `<button ... type="submit">`, add:
```blade
<p class="form-privacy-notice">...</p>
```

- [ ] **Step 2: Add privacy policy link placeholder in footer**

In `layouts/app.blade.php` footer-bottom, add a privacy policy link.

- [ ] **Step 3: Commit Sprint 3B fixes**

```
git commit -m "fix(3b): add GDPR privacy notice near form and footer privacy link"
```

---

## Sprint 3C — SEO Meta Tags

### Task 3C-1: Add OG meta tags, canonical, and hreflang to layout

**Files:**
- Modify: `resources/views/layouts/app.blade.php`

- [ ] **Step 1: Add OG tags and canonical**

Add to `<head>`:
- `<meta property="og:title">`
- `<meta property="og:description">`
- `<meta property="og:type">`
- `<link rel="canonical">`

- [ ] **Step 2: Add hreflang for nl/fr/en**

- [ ] **Step 3: Run build and final checks**

Run: `php artisan config:clear && npm run build && php artisan test`

- [ ] **Step 4: Commit Sprint 3C**

```
git commit -m "feat(3c): add OG meta tags, canonical URL, and hreflang to layout"
```
