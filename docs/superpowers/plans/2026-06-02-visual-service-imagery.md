# Visual Service Imagery — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add sector-relevant inline SVG icons and visual structure to the homepage hero and all 6 service pages — no real images exist, so all visuals are CSS + inline SVG.

**Architecture:** Four-file change: (1) home-page.blade.php replaces the text-only hero-panel with a visual service-chip grid; (2) service-page.blade.php adds a service-icon badge to each service hero; (3) home.css replaces `.hero-panel` styles with `.hero-services-visual` + `.service-chip` styles; (4) service.css adds `.service-hero-inner` flex layout + `.service-hero-icon` badge styles. No DB changes. No new files. No external image dependencies.

**Tech Stack:** Laravel 12 Blade, PHP inline SVG strings, CSS custom properties. Windows PowerShell for shell commands. All NL/FR/EN preserved.

---

## File Map

| File | What changes |
|------|-------------|
| `resources/views/pages/partials/home-page.blade.php` | Add `$serviceIcons` + preserve `key` in `$services` + add `hero_services_label` + replace `<aside class="hero-panel">` with service chip grid |
| `resources/css/pages/home.css` | Replace `.hero-panel` CSS block with `.hero-services-visual` + `.hero-services-grid` + `.service-chip` styles (including mobile overrides) |
| `resources/views/pages/partials/service-page.blade.php` | Add `$serviceIcons` + wrap service-hero content in `.service-hero-inner` + add `.service-hero-icon` div |
| `resources/css/pages/service.css` | Update `.service-hero` padding + add `.service-hero-inner` / `.service-hero-text` / `.service-hero-icon` styles |

---

## Shared SVG icons reference

These exact SVG strings are used in both Blade files. Each is a 24×24 viewBox, stroke-based, no fill:

```
heating:
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M8.5 14.5A2.5 2.5 0 0 0 11 12c0-1.38-.5-2-1-3-1.072-2.143-.224-4.054 2-6 .5 2.5 2 4.9 4 6.5 2 1.6 3 3.5 3 5.5a7 7 0 1 1-14 0c0-1.153.433-2.294 1-3a2.5 2.5 0 0 0 2.5 2.5z"/></svg>

airco:
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17.7 7.7a2.5 2.5 0 1 1 1.8 4.3H2"/><path d="M9.6 4.6A2 2 0 1 1 11 8H2"/><path d="M12.6 19.4A2 2 0 1 0 14 16H2"/></svg>

plumbing:
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 2.69l5.66 5.66a8 8 0 1 1-11.31 0z"/></svg>

ventilation:
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 2v6h-6"/><path d="M21 13a9 9 0 1 1-3-7.7L21 8"/></svg>

water-softeners:
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7 16.3c2.2 0 4-1.83 4-4.05 0-1.16-.57-2.26-1.71-3.19S7.29 6.75 7 5.3c-.29 1.45-1.14 2.84-2.29 3.76S3 11.1 3 12.25c0 2.22 1.8 4.05 4 4.05z"/><path d="M12.56 6.6A10.97 10.97 0 0 0 14 3.02c.5 2.5 2 4.9 4 6.5s3 3.5 3 5.5a6.98 6.98 0 0 1-11.91 4.97"/></svg>

cold-rooms:
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="12" y1="2" x2="12" y2="22"/><line x1="2" y1="12" x2="22" y2="12"/><path d="m20 16-4-4 4-4"/><path d="m4 8 4 4-4 4"/><path d="m16 4-4 4-4-4"/><path d="m8 20 4-4 4 4"/></svg>
```

---

## Task 1: Update home-page.blade.php

**Files:** Modify `resources/views/pages/partials/home-page.blade.php`

**Three sub-changes in a single edit pass:**

### 1A — Add `$serviceIcons` and update `$services` to preserve config key

Replace the `$services` collect block (lines 3–10 of the @php block, currently):

```php
    $configuredServices = config('services');

    $services = collect($configuredServices)
        ->filter(fn($service) => $service['is_active'] ?? false)
        ->map(function ($service) use ($locale) {
            return $service['translations'][$locale] ?? $service['translations']['nl'];
        })
        ->values();
```

With:

```php
    $configuredServices = config('services');

    $services = collect($configuredServices)
        ->filter(fn($service) => $service['is_active'] ?? false)
        ->map(function ($service, $key) use ($locale) {
            $trans = $service['translations'][$locale] ?? $service['translations']['nl'];
            return array_merge($trans, ['key' => $key]);
        })
        ->values();

    $serviceIcons = [
        'heating' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M8.5 14.5A2.5 2.5 0 0 0 11 12c0-1.38-.5-2-1-3-1.072-2.143-.224-4.054 2-6 .5 2.5 2 4.9 4 6.5 2 1.6 3 3.5 3 5.5a7 7 0 1 1-14 0c0-1.153.433-2.294 1-3a2.5 2.5 0 0 0 2.5 2.5z"/></svg>',
        'airco' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17.7 7.7a2.5 2.5 0 1 1 1.8 4.3H2"/><path d="M9.6 4.6A2 2 0 1 1 11 8H2"/><path d="M12.6 19.4A2 2 0 1 0 14 16H2"/></svg>',
        'plumbing' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 2.69l5.66 5.66a8 8 0 1 1-11.31 0z"/></svg>',
        'ventilation' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 2v6h-6"/><path d="M21 13a9 9 0 1 1-3-7.7L21 8"/></svg>',
        'water-softeners' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7 16.3c2.2 0 4-1.83 4-4.05 0-1.16-.57-2.26-1.71-3.19S7.29 6.75 7 5.3c-.29 1.45-1.14 2.84-2.29 3.76S3 11.1 3 12.25c0 2.22 1.8 4.05 4 4.05z"/><path d="M12.56 6.6A10.97 10.97 0 0 0 14 3.02c.5 2.5 2 4.9 4 6.5s3 3.5 3 5.5a6.98 6.98 0 0 1-11.91 4.97"/></svg>',
        'cold-rooms' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="12" y1="2" x2="12" y2="22"/><line x1="2" y1="12" x2="22" y2="12"/><path d="m20 16-4-4 4-4"/><path d="m4 8 4 4-4 4"/><path d="m16 4-4 4-4-4"/><path d="m8 20 4-4 4 4"/></svg>',
    ];
```

### 1B — Add `hero_services_label` to the `$labels` array

In the `nl` locale array, add after `'hero_badge' => ...`:
```php
            'hero_services_label' => 'Onze diensten',
```

In the `fr` locale array, add after `'hero_badge' => ...`:
```php
            'hero_services_label' => 'Nos services',
```

In the `en` locale array, add after `'hero_badge' => ...`:
```php
            'hero_services_label' => 'Our services',
```

### 1C — Replace the `<aside class="hero-panel">` HTML

Replace the entire `<aside class="hero-panel">` block (currently lines ~269–279):

```html
            <aside class="hero-panel">
                <p class="panel-label">{{ $text['panel_label'] }}</p>

                <h2>{{ $text['panel_title'] }}</h2>

                <ul>
                    @foreach ($text['panel_points'] as $point)
                        <li>{{ $point }}</li>
                    @endforeach
                </ul>
            </aside>
```

With:

```html
            <aside class="hero-services-visual">
                <p class="hero-services-visual-label">{{ $text['hero_services_label'] }}</p>

                <div class="hero-services-grid">
                    @foreach ($services as $service)
                        <a
                            class="service-chip"
                            href="{{ route('pages.show', [
                                'locale' => $locale,
                                'slug' => $service['slug'],
                            ]) }}"
                        >
                            <span class="service-chip-icon">
                                {!! $serviceIcons[$service['key']] ?? '' !!}
                            </span>
                            <span class="service-chip-name">{{ $service['title'] }}</span>
                        </a>
                    @endforeach
                </div>
            </aside>
```

- [ ] **Step 1: Read the current file**

Read `resources/views/pages/partials/home-page.blade.php` to confirm current content before editing.

- [ ] **Step 2: Apply sub-change 1A** — update `$services` collect and add `$serviceIcons` array

- [ ] **Step 3: Apply sub-change 1B** — add `hero_services_label` to nl/fr/en in `$labels`

- [ ] **Step 4: Apply sub-change 1C** — replace `<aside class="hero-panel">` with chip grid

- [ ] **Step 5: Verify the file looks correct** — read lines 1–20 (PHP block) and lines 265–300 (hero aside) to confirm both changes

- [ ] **Step 6: PHP lint**

```
php -l resources/views/pages/partials/home-page.blade.php
```
Expected: `No syntax errors detected`

- [ ] **Step 7: Commit**

```
git add resources/views/pages/partials/home-page.blade.php
git commit -m "feat: add service icon chips to homepage hero"
```

---

## Task 2: Update home.css — hero visual CSS

**Files:** Modify `resources/css/pages/home.css`

**What to change:**

The existing `.hero-panel` and related CSS rules need to be replaced with `.hero-services-visual` rules, and new `.service-chip` rules added.

### 2A — Replace the `.hero-panel` CSS block

Find and replace the entire `.hero-panel` rules block:

Old (find this exact block):
```css
.hero-panel {
    padding: 32px;
    border: 1px solid rgba(255, 255, 255, 0.8);
    border-radius: 32px;
    background: rgba(255, 255, 255, 0.82);
    box-shadow: var(--shadow-strong);
    backdrop-filter: blur(14px);
}

.panel-label {
    margin-bottom: 8px;
    color: var(--color-primary);
    font-size: 0.8rem;
    font-weight: 900;
    letter-spacing: 0.06em;
    text-transform: uppercase;
}

.hero-panel h2 {
    margin-bottom: 20px;
    color: var(--color-primary-dark);
    font-size: 1.55rem;
}

.hero-panel ul {
    margin: 0;
    padding-left: 20px;
    color: var(--color-muted);
}

.hero-panel li + li {
    margin-top: 10px;
}
```

New (replace with this exact block):
```css
.hero-services-visual {
    padding: 24px;
    border: 1px solid rgba(255, 255, 255, 0.8);
    border-radius: 28px;
    background: rgba(255, 255, 255, 0.82);
    box-shadow: var(--shadow-strong);
    backdrop-filter: blur(14px);
}

.hero-services-visual-label {
    margin-bottom: 14px;
    color: var(--color-primary);
    font-size: 0.75rem;
    font-weight: 900;
    letter-spacing: 0.06em;
    text-transform: uppercase;
}

.hero-services-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 10px;
}

.service-chip {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
    padding: 14px 8px;
    border: 1px solid var(--color-border);
    border-radius: var(--radius-small);
    background: var(--color-white);
    color: var(--color-primary-dark);
    text-decoration: none;
    font-size: 0.8rem;
    font-weight: 700;
    text-align: center;
    line-height: 1.2;
    transition:
        transform 0.18s ease,
        box-shadow 0.18s ease,
        border-color 0.18s ease;
}

.service-chip:hover {
    transform: translateY(-3px);
    border-color: rgba(15, 102, 194, 0.35);
    box-shadow: 0 8px 20px rgba(15, 53, 87, 0.12);
}

.service-chip-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 38px;
    height: 38px;
    border-radius: 10px;
    background: linear-gradient(135deg, #dbeafe, #eff6ff);
    color: var(--color-primary);
    flex-shrink: 0;
}

.service-chip-icon svg {
    width: 20px;
    height: 20px;
}

.service-chip-name {
    font-size: 0.76rem;
    font-weight: 700;
    color: var(--color-primary-dark);
    line-height: 1.2;
}
```

### 2B — Update the mobile media query for the hero panel

In the `@media (max-width: 680px)` block, find and replace:

Old:
```css
    .hero-panel {
        padding: 22px;
        border-radius: 24px;
    }

    .hero-panel h2 {
        font-size: 1.35rem;
    }
```

New:
```css
    .hero-services-visual {
        padding: 18px;
        border-radius: 22px;
    }

    .hero-services-grid {
        gap: 8px;
    }

    .service-chip {
        padding: 12px 6px;
    }

    .service-chip-icon {
        width: 32px;
        height: 32px;
    }

    .service-chip-icon svg {
        width: 17px;
        height: 17px;
    }

    .service-chip-name {
        font-size: 0.7rem;
    }
```

- [ ] **Step 1: Read `resources/css/pages/home.css`** — confirm the `.hero-panel` block location

- [ ] **Step 2: Apply sub-change 2A** — replace the `.hero-panel` block with `.hero-services-visual` + `.service-chip` rules

- [ ] **Step 3: Apply sub-change 2B** — update the mobile media query

- [ ] **Step 4: Commit**

```
git add resources/css/pages/home.css
git commit -m "feat: add service chip styles, replace hero-panel CSS"
```

---

## Task 3: Update service-page.blade.php — add service icon to hero

**Files:** Modify `resources/views/pages/partials/service-page.blade.php`

### 3A — Add `$serviceIcons` to the @php block

After line 12 (after the `$currentServiceKey` detection loop ends with `}`), add this block:

```php
    $serviceIcons = [
        'heating' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M8.5 14.5A2.5 2.5 0 0 0 11 12c0-1.38-.5-2-1-3-1.072-2.143-.224-4.054 2-6 .5 2.5 2 4.9 4 6.5 2 1.6 3 3.5 3 5.5a7 7 0 1 1-14 0c0-1.153.433-2.294 1-3a2.5 2.5 0 0 0 2.5 2.5z"/></svg>',
        'airco' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17.7 7.7a2.5 2.5 0 1 1 1.8 4.3H2"/><path d="M9.6 4.6A2 2 0 1 1 11 8H2"/><path d="M12.6 19.4A2 2 0 1 0 14 16H2"/></svg>',
        'plumbing' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 2.69l5.66 5.66a8 8 0 1 1-11.31 0z"/></svg>',
        'ventilation' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 2v6h-6"/><path d="M21 13a9 9 0 1 1-3-7.7L21 8"/></svg>',
        'water-softeners' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7 16.3c2.2 0 4-1.83 4-4.05 0-1.16-.57-2.26-1.71-3.19S7.29 6.75 7 5.3c-.29 1.45-1.14 2.84-2.29 3.76S3 11.1 3 12.25c0 2.22 1.8 4.05 4 4.05z"/><path d="M12.56 6.6A10.97 10.97 0 0 0 14 3.02c.5 2.5 2 4.9 4 6.5s3 3.5 3 5.5a6.98 6.98 0 0 1-11.91 4.97"/></svg>',
        'cold-rooms' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="12" y1="2" x2="12" y2="22"/><line x1="2" y1="12" x2="22" y2="12"/><path d="m20 16-4-4 4-4"/><path d="m4 8 4 4-4 4"/><path d="m16 4-4 4-4-4"/><path d="m8 20 4-4 4 4"/></svg>',
    ];
```

### 3B — Update the service-hero HTML

Replace the current `<section class="service-hero">` block:

Old:
```html
<section class="service-hero">
    <div class="container">
        <span class="eyebrow">{{ $text['type'] }}</span>

        <h1>{{ $translation->title }}</h1>

        @if ($translation->intro)
            <p class="service-intro">{{ $translation->intro }}</p>
        @endif
    </div>
</section>
```

New:
```html
<section class="service-hero">
    <div class="container">
        <div class="service-hero-inner">
            <div class="service-hero-text">
                <span class="eyebrow">{{ $text['type'] }}</span>

                <h1>{{ $translation->title }}</h1>

                @if ($translation->intro)
                    <p class="service-intro">{{ $translation->intro }}</p>
                @endif
            </div>

            @if ($currentServiceKey && isset($serviceIcons[$currentServiceKey]))
                <div class="service-hero-icon" aria-hidden="true">
                    {!! $serviceIcons[$currentServiceKey] !!}
                </div>
            @endif
        </div>
    </div>
</section>
```

- [ ] **Step 1: Read lines 1–15 and lines 329–345 of `resources/views/pages/partials/service-page.blade.php`** — confirm current structure before editing

- [ ] **Step 2: Apply sub-change 3A** — insert `$serviceIcons` after the `$currentServiceKey` detection block (after line 12)

- [ ] **Step 3: Apply sub-change 3B** — replace the `<section class="service-hero">` block

- [ ] **Step 4: PHP lint**

```
php -l resources/views/pages/partials/service-page.blade.php
```
Expected: `No syntax errors detected`

- [ ] **Step 5: Commit**

```
git add resources/views/pages/partials/service-page.blade.php
git commit -m "feat: add service icon badge to service page hero"
```

---

## Task 4: Update service.css — service hero icon styles

**Files:** Modify `resources/css/pages/service.css`

### 4A — Update `.service-hero` to remove own padding

Find:
```css
.service-hero {
    padding: 15px 0 0;
    background:
        radial-gradient(circle at top right, rgba(15, 102, 194, 0.12), transparent 34%),
        linear-gradient(135deg, #ffffff 0%, #f3f7fb 100%);
    border-bottom: 1px solid var(--color-border);
}
```

Replace with:
```css
.service-hero {
    padding: 0;
    background:
        radial-gradient(circle at top right, rgba(15, 102, 194, 0.12), transparent 34%),
        linear-gradient(135deg, #ffffff 0%, #f3f7fb 100%);
    border-bottom: 1px solid var(--color-border);
}
```

### 4B — Add new `.service-hero-inner`, `.service-hero-text`, `.service-hero-icon` rules

Append after the existing `.service-hero + .section` rule:

```css
/* ================================
   Service hero layout (with icon)
================================ */

.service-hero-inner {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 32px;
    padding: 48px 0 40px;
}

.service-hero-text {
    flex: 1;
    min-width: 0;
}

.service-hero-icon {
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 108px;
    height: 108px;
    border-radius: 28px;
    background:
        linear-gradient(135deg, rgba(15, 102, 194, 0.08) 0%, rgba(15, 53, 87, 0.04) 100%);
    border: 1px solid rgba(15, 102, 194, 0.14);
    color: var(--color-primary);
}

.service-hero-icon svg {
    width: 56px;
    height: 56px;
}
```

### 4C — Update the mobile media query for `.service-hero`

In the `@media (max-width: 680px)` block, find:
```css
    .service-hero {
        padding: 52px 0 44px;
    }
```

Replace with:
```css
    .service-hero {
        padding: 0;
    }

    .service-hero-inner {
        flex-direction: column;
        align-items: flex-start;
        gap: 20px;
        padding: 36px 0 28px;
    }

    .service-hero-icon {
        width: 78px;
        height: 78px;
        border-radius: 20px;
    }

    .service-hero-icon svg {
        width: 40px;
        height: 40px;
    }
```

- [ ] **Step 1: Read `resources/css/pages/service.css`** — confirm full file contents

- [ ] **Step 2: Apply sub-change 4A** — change `.service-hero` padding to 0

- [ ] **Step 3: Apply sub-change 4B** — append `.service-hero-inner` block after `.service-hero + .section`

- [ ] **Step 4: Apply sub-change 4C** — update mobile media query

- [ ] **Step 5: Commit**

```
git add resources/css/pages/service.css
git commit -m "feat: add service hero icon layout and styles"
```

---

## Task 5: Build, test, final commit

- [ ] **Step 1: Run build**

```
npm run build
```
Expected: exits 0, no errors.

- [ ] **Step 2: Run tests**

```
php artisan test
```
Expected: all tests pass.

- [ ] **Step 3: Run route list spot-check**

```
php artisan route:list
```
Expected: `pages.home`, `pages.show`, `customer-requests.store`, admin routes all present.

- [ ] **Step 4: Final commit if any remaining untracked/unstaged changes**

```
git add -A
git commit -m "feat: add visual service imagery and stronger homepage hero"
```

---

## Self-Review: Spec Coverage

| Requirement | Task covering it |
|-------------|----------------|
| Homepage hero has sector-relevant visual cues | Task 1C + Task 2 (service chip grid with SVG icons) |
| Heating/airco/sanitair/ventilation/cooling chips | Task 1A ($serviceIcons) + Task 1C (chip grid) |
| Subtle CSS hover/motion only, no heavy JS | Task 2 (.service-chip:hover with CSS transition) |
| Service pages support one relevant image per service | Task 3 + Task 4 (icon badge per page) |
| Mobile remains clean | Task 2 (mobile overrides) + Task 4 (mobile overrides) |
| NL/FR/EN preserved | Task 1 ($services still uses locale) + hero_services_label in all 3 locales |
| No DB changes | Confirmed |
| No Smart Request Flow touched | Confirmed — only home-page.blade.php, service-page.blade.php, home.css, service.css |
| No admin touched | Confirmed |
| No real images required — layout doesn't break without them | Task 3/4 use conditional rendering (`@if`) + CSS fallback gradients |
| `npm run build` passes | Task 5 |
| `php artisan test` passes | Task 5 |
