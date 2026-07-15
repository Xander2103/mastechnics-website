# Sprint 8: Services Nav Link + Reviews Section Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Fix the services nav so "Diensten" is a real navigable link while the dropdown still works on hover; add a config-driven reviews carousel section to the homepage with a nav link.

**Architecture:** Nav change splits the single `<button>` into an `<a>` (text/link) + `<button>` (chevron toggle only) inside a `.services-dropdown-trigger` wrapper. Reviews data lives in `config/reviews.php`; the section is appended to `home-page.blade.php`; a vanilla JS carousel is added to `app.js`. Nav labels for "Reviews" are added to the `$navLabels` map in `app.blade.php`.

**Tech Stack:** Laravel 12 · Blade · Vanilla JS (ES5 compat) · CSS custom properties · PHPUnit feature tests

## Global Constraints

- PowerShell only — no Bash `&&` chaining; use `;` or `if ($?) { ... }`
- All user-facing text must have nl / fr / en translations
- Default fallback locale: `nl`
- No external JS libraries (no jQuery, no Swiper, no Glide)
- Mobile-first CSS; test at ≤680 px breakpoint
- No `prefers-reduced-motion` auto-advance for carousel
- No aggregateRating JSON-LD (placeholder data)
- Do not break existing routes, request form, quote system, or admin dashboard
- File uploads, CustomerRequest model, admin auth must remain untouched

---

## File Map

| Action | Path | Responsibility |
|--------|------|----------------|
| Modify | `resources/views/layouts/app.blade.php` | Nav: split services trigger; add Reviews link |
| Modify | `resources/css/layout/header.css` | Styles for `.services-dropdown-trigger`, `.services-dropdown-link`, mobile layout |
| Modify | `resources/js/app.js` | Add `initReviewsCarousel()`; call it in DOMContentLoaded |
| Modify | `resources/views/pages/partials/home-page.blade.php` | Add `#reviews` section with carousel markup; add review text labels to `$labels` |
| Modify | `resources/css/pages/home.css` | Append reviews section CSS |
| Create | `config/reviews.php` | Reviews data, Google URL, enabled flag |
| Create | `tests/Feature/HomepageTest.php` | Blade rendering assertions for reviews + nav |

---

## Task A: Baseline Verification

**Files:** None (read-only)

- [ ] **Step 1: Confirm quote-fix commit exists**

```powershell
git log --oneline -3
```

Expected: first line starts with `8d30db3`

- [ ] **Step 2: Run full test suite**

```powershell
php artisan test
```

Expected: all tests pass (look for `Tests: N passed`)

- [ ] **Step 3: PHP-lint the quote edit blade**

```powershell
php -r "echo 'Blade syntax OK';"
php artisan view:clear
```

Expected: no errors

---

## Task B: Services Nav — Clickable Link + Chevron Toggle

**Files:**
- Modify: `resources/views/layouts/app.blade.php` (lines 172–192 — the `<div class="services-dropdown">` block)
- Modify: `resources/css/layout/header.css` (`.services-dropdown-toggle` block + mobile block)

**No JS changes needed** — `initServicesDropdown()` already attaches hover to the `.services-dropdown` container and mobile click to `.services-dropdown-toggle`. Splitting the HTML keeps both selectors working as-is.

- [ ] **Step 1: Replace the services dropdown button block in app.blade.php**

Replace this entire block (lines 172–192 in `resources/views/layouts/app.blade.php`):

```blade
<div class="services-dropdown">
    <button
        type="button"
        class="services-dropdown-toggle"
        aria-haspopup="true"
        aria-expanded="false"
        aria-label="{{ $nav['services'] }}"
    >
        {{ $nav['services'] }}
        <svg class="services-dropdown-chevron" xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 12 12" fill="none" aria-hidden="true" focusable="false">
            <path d="M2 4l4 4 4-4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
    </button>
    <div class="services-dropdown-menu" role="menu">
        @foreach ($serviceNav as $service)
            <a href="{{ route('pages.show', ['locale' => $currentLocale, 'slug' => $service['slug']]) }}" role="menuitem">
                {{ $service['title'] }}
            </a>
        @endforeach
    </div>
</div>
```

With this new block:

```blade
<div class="services-dropdown">
    <div class="services-dropdown-trigger">
        <a
            class="services-dropdown-link"
            href="{{ route('pages.home', ['locale' => $currentLocale]) }}#diensten"
        >
            {{ $nav['services'] }}
        </a>
        <button
            type="button"
            class="services-dropdown-toggle"
            aria-haspopup="true"
            aria-expanded="false"
            aria-controls="servicesDropdownMenu"
            aria-label="{{ $currentLocale === 'fr' ? 'Afficher le menu des services' : ($currentLocale === 'en' ? 'Show services menu' : 'Toon dienstenmenu') }}"
        >
            <svg class="services-dropdown-chevron" xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 12 12" fill="none" aria-hidden="true" focusable="false">
                <path d="M2 4l4 4 4-4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        </button>
    </div>
    <div class="services-dropdown-menu" id="servicesDropdownMenu" role="menu">
        @foreach ($serviceNav as $service)
            <a href="{{ route('pages.show', ['locale' => $currentLocale, 'slug' => $service['slug']]) }}" role="menuitem">
                {{ $service['title'] }}
            </a>
        @endforeach
    </div>
</div>
```

- [ ] **Step 2: Update header.css — replace the `.services-dropdown-toggle` desktop block**

Find and replace the entire desktop `.services-dropdown-toggle` rule-set (inside `@media (min-width: 681px)`) in `resources/css/layout/header.css`.

**Current** (approx lines 145–166):
```css
@media (min-width: 681px) {
    .services-dropdown-toggle {
        position: relative;
    }

    .services-dropdown-toggle::after {
        content: "";
        position: absolute;
        left: 0;
        bottom: -3px;
        width: 0;
        height: 2px;
        background: var(--color-primary);
        border-radius: 999px;
        transition: width 0.20s ease;
    }

    .services-dropdown-toggle:hover::after,
    .services-dropdown:focus-within .services-dropdown-toggle::after {
        width: 100%;
    }
}
```

**Replace with:**
```css
@media (min-width: 681px) {
    .services-dropdown-link {
        position: relative;
    }

    .services-dropdown-link::after {
        content: "";
        position: absolute;
        left: 0;
        bottom: -3px;
        width: 0;
        height: 2px;
        background: var(--color-primary);
        border-radius: 999px;
        transition: width 0.20s ease;
    }

    .services-dropdown-link:hover::after,
    .services-dropdown:focus-within .services-dropdown-link::after {
        width: 100%;
    }
}
```

- [ ] **Step 3: Add new rules for `.services-dropdown-trigger` and `.services-dropdown-link` to header.css**

Add these rules directly after the `.services-dropdown-chevron` block (around line 170, before `.services-dropdown-menu`):

```css
.services-dropdown-trigger {
    display: inline-flex;
    align-items: center;
    gap: 2px;
}

.services-dropdown-link {
    color: var(--color-muted);
    font-size: 0.96rem;
    font-weight: 900;
    text-decoration: none;
    transition: color 0.18s ease;
}

.services-dropdown-link:hover {
    color: var(--color-primary-dark);
}
```

And update the existing `.services-dropdown-toggle` rule (the non-media-query one) to remove text-display properties and keep only button-reset + chevron sizing:

**Current `.services-dropdown-toggle`:**
```css
.services-dropdown-toggle {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    cursor: pointer;
    background: none;
    border: none;
    padding: 0;
    font: inherit;
    font-size: 0.96rem;
    font-weight: 900;
    color: var(--color-muted);
    text-decoration: none;
    transition: color 0.18s ease;
}

.services-dropdown-toggle:hover {
    color: var(--color-primary-dark);
}
```

**Replace with:**
```css
.services-dropdown-toggle {
    display: inline-flex;
    align-items: center;
    cursor: pointer;
    background: none;
    border: none;
    padding: 4px 2px;
    color: var(--color-muted);
    transition: color 0.18s ease;
}

.services-dropdown-toggle:hover {
    color: var(--color-primary-dark);
}
```

- [ ] **Step 4: Update mobile block in header.css**

In the `@media (max-width: 680px)` block, replace the mobile `.services-dropdown-toggle` rule:

**Current:**
```css
.services-dropdown-toggle {
    display: flex;
    justify-content: center;
    align-items: center;
    width: 100%;
    min-height: 42px;
    padding: 10px 12px;
    border: 1px solid var(--color-border);
    border-radius: 999px;
    background: var(--color-white);
    color: var(--color-primary-dark);
    font-size: 0.92rem;
    font-weight: 900;
}
```

**Replace with:**
```css
.services-dropdown-trigger {
    display: flex;
    align-items: center;
    width: 100%;
    min-height: 42px;
    border: 1px solid var(--color-border);
    border-radius: 999px;
    background: var(--color-white);
    overflow: hidden;
}

.services-dropdown-link {
    flex: 1;
    display: flex;
    justify-content: center;
    align-items: center;
    padding: 10px 16px;
    color: var(--color-primary-dark);
    font-size: 0.92rem;
    font-weight: 900;
    text-decoration: none;
}

.services-dropdown-toggle {
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    width: 46px;
    min-height: 42px;
    padding: 0;
    border: 0;
    border-left: 1px solid var(--color-border);
    border-radius: 0;
    background: transparent;
    color: var(--color-primary-dark);
}
```

- [ ] **Step 5: Clear view cache and do a quick smoke check**

```powershell
php artisan view:clear
php -l resources/views/layouts/app.blade.php
```

Expected: `No syntax errors detected`

- [ ] **Step 6: Run the test suite**

```powershell
php artisan test
```

Expected: all pass (no tests directly cover this yet — we add them in Task D)

- [ ] **Step 7: Commit**

```powershell
git add resources/views/layouts/app.blade.php resources/css/layout/header.css
git commit -m "fix(site): keep services nav main link clickable with dropdown"
```

---

## Task C: Reviews Config

**Files:**
- Create: `config/reviews.php`

- [ ] **Step 1: Create config/reviews.php**

```php
<?php

return [
    'enabled' => true,

    'google_review_url' => 'https://www.google.com/search?sca_esv=ec2bff8bd1e2ef21&cs=0&sxsrf=ANbL-n6DQwM8rkU6sXsHhz5rWC_EznkXXQ:1781595564528&q=Mas+Technics+Reviews&rflfq=1&num=20&stick=H4sIAAAAAAAAAONgkxI2MjQyMzA3MjAxNLU0N7IwNTEw3MDI-IpRxDexWCEkNTkjLzO5WCEotSwztbx4EStWYQA4mFqKSAAAAA&rldimm=2126072041597285401&tbm=lcl&hl=nl-BE&sa=X&ved=2ahUKEwiYiYbWoIuVAxW6V6QEHTAlO8YQ9fQKegQIEhAG&biw=2276&bih=1197&dpr=1.13#lkt=LocalPoiReviews',

    'reviews' => [
        [
            'name'     => 'Thomas V.',
            'location' => 'Antwerpen',
            'rating'   => 5,
            'text'     => 'Snel geholpen na mijn CV-ketelstoring. De technici waren punctueel, netjes en duidelijk in hun uitleg. Aanrader.',
            'service'  => null,
            'date'     => '2025-10',
        ],
        [
            'name'     => 'Nathalie D.',
            'location' => 'Gent',
            'rating'   => 5,
            'text'     => 'Airco laten installeren voor twee slaapkamers. Vlotte communicatie vooraf, een correcte offerte en een nette afwerking. Zeer tevreden.',
            'service'  => null,
            'date'     => '2025-09',
        ],
        [
            'name'     => 'Marc L.',
            'location' => 'Bruxelles',
            'rating'   => 5,
            'text'     => 'Service rapide et professionnel pour la maintenance de ma chaudière. Bon contact, travail soigné. Je recommande sans hésiter.',
            'service'  => null,
            'date'     => '2025-11',
        ],
        [
            'name'     => 'Lieselotte B.',
            'location' => 'Leuven',
            'rating'   => 5,
            'text'     => 'Waterverzachter geïnstalleerd met duidelijke uitleg over gebruik en onderhoud. Precies wat we nodig hadden, vlot en proper uitgevoerd.',
            'service'  => null,
            'date'     => '2025-08',
        ],
    ],
];
```

- [ ] **Step 2: Verify config loads**

```powershell
php artisan tinker --execute="var_dump(count(config('reviews.reviews')));"
```

Expected: `int(4)`

- [ ] **Step 3: Commit**

```powershell
git add config/reviews.php
git commit -m "feat(site): add reviews config with placeholder testimonials"
```

---

## Task D: Reviews Section — Tests, Blade, CSS, JS, Nav Link

**Files:**
- Create: `tests/Feature/HomepageTest.php`
- Modify: `resources/views/pages/partials/home-page.blade.php` (append reviews section + labels)
- Modify: `resources/views/layouts/app.blade.php` (add reviews nav label + nav link)
- Modify: `resources/css/pages/home.css` (append reviews CSS)
- Modify: `resources/js/app.js` (add `initReviewsCarousel`, call it in DOMContentLoaded)

### Step 1-3: Write and run failing tests first

- [ ] **Step 1: Create tests/Feature/HomepageTest.php**

```php
<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class HomepageTest extends TestCase
{
    use RefreshDatabase;

    public function test_homepage_nl_renders_reviews_section(): void
    {
        $this->get(route('pages.home', ['locale' => 'nl']))
            ->assertOk()
            ->assertSee('id="reviews"', false)
            ->assertSee('Wat klanten zeggen');
    }

    public function test_homepage_fr_renders_reviews_section(): void
    {
        $this->get(route('pages.home', ['locale' => 'fr']))
            ->assertOk()
            ->assertSee('Ce que disent les clients');
    }

    public function test_homepage_en_renders_reviews_section(): void
    {
        $this->get(route('pages.home', ['locale' => 'en']))
            ->assertOk()
            ->assertSee('What customers say');
    }

    public function test_homepage_leave_review_button_has_google_url(): void
    {
        $this->get(route('pages.home', ['locale' => 'nl']))
            ->assertOk()
            ->assertSee(config('reviews.google_review_url'), false);
    }

    public function test_homepage_nl_nav_contains_reviews_link(): void
    {
        $this->get(route('pages.home', ['locale' => 'nl']))
            ->assertOk()
            ->assertSee('Reviews')
            ->assertSee('#reviews', false);
    }

    public function test_homepage_fr_nav_contains_avis_link(): void
    {
        $this->get(route('pages.home', ['locale' => 'fr']))
            ->assertOk()
            ->assertSee('Avis');
    }

    public function test_services_nav_has_clickable_anchor_link(): void
    {
        $this->get(route('pages.home', ['locale' => 'nl']))
            ->assertOk()
            ->assertSee('class="services-dropdown-link"', false)
            ->assertSee('#diensten', false);
    }

    public function test_homepage_reviews_hidden_when_disabled(): void
    {
        config(['reviews.enabled' => false]);

        $this->get(route('pages.home', ['locale' => 'nl']))
            ->assertOk()
            ->assertDontSee('id="reviews"', false);
    }
}
```

- [ ] **Step 2: Run to confirm tests fail**

```powershell
php artisan test --filter=HomepageTest
```

Expected: multiple failures — `reviews` section and nav items not yet implemented.

### Step 3-8: Implement

- [ ] **Step 3: Add reviews labels to `$labels` array in home-page.blade.php**

In `resources/views/pages/partials/home-page.blade.php`, append these keys to each locale inside the `$labels` array (before the closing `]` of each locale):

**nl bloc** — add after `'nav_contact' => 'Contact',`:
```php
'reviews_eyebrow' => 'Klantenervaringen',
'reviews_title'   => 'Wat klanten zeggen',
'reviews_intro'   => 'Ervaringen van klanten die Mastechnics inschakelden voor installatie, onderhoud of herstelling.',
'reviews_cta'     => 'Laat een review achter',
```

**fr bloc** — add after `'nav_contact' => 'Contact',`:
```php
'reviews_eyebrow' => 'Avis clients',
'reviews_title'   => 'Ce que disent les clients',
'reviews_intro'   => "Expériences de clients ayant fait appel à Mastechnics pour une installation, un entretien ou une réparation.",
'reviews_cta'     => 'Laisser un avis',
```

**en bloc** — add after `'nav_contact' => 'Contact',`:
```php
'reviews_eyebrow' => 'Customer experiences',
'reviews_title'   => 'What customers say',
'reviews_intro'   => 'Experiences from customers who called on Mastechnics for installation, maintenance or repair.',
'reviews_cta'     => 'Leave a review',
```

- [ ] **Step 4: Append reviews section to home-page.blade.php (after section-werkwijze, before section-cta)**

Insert this block between the closing `</section>` of `.section-werkwijze` and the opening `<section class="section section-cta"`:

```blade
@if (config('reviews.enabled'))
<section class="section section-reviews" id="reviews">
    <div class="container">
        <div class="section-header">
            <span class="eyebrow">{{ $text['reviews_eyebrow'] }}</span>
            <h2>{{ $text['reviews_title'] }}</h2>
            <p>{{ $text['reviews_intro'] }}</p>
        </div>

        <div class="reviews-carousel" aria-label="{{ $text['reviews_title'] }}">
            <div class="reviews-track-wrapper">
                <div class="reviews-track" id="reviewsTrack">
                    @foreach (config('reviews.reviews', []) as $review)
                        <article class="review-card">
                            <div class="review-stars" aria-label="{{ $review['rating'] }} op 5 sterren">
                                @for ($s = 1; $s <= 5; $s++)
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="{{ $s <= $review['rating'] ? '#f59e0b' : 'none' }}" stroke="#f59e0b" stroke-width="1.5" aria-hidden="true" focusable="false">
                                        <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
                                    </svg>
                                @endfor
                            </div>
                            <blockquote class="review-text">
                                <p>{{ $review['text'] }}</p>
                            </blockquote>
                            <footer class="review-footer">
                                <strong>{{ $review['name'] }}</strong>
                                @if (!empty($review['location']))
                                    <span class="review-location">— {{ $review['location'] }}</span>
                                @endif
                            </footer>
                        </article>
                    @endforeach
                </div>
            </div>

            <div class="reviews-controls">
                <button class="reviews-prev" aria-label="{{ $locale === 'fr' ? 'Précédent' : ($locale === 'en' ? 'Previous' : 'Vorige') }}">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="15 18 9 12 15 6"/></svg>
                </button>
                <div class="reviews-dots" id="reviewsDots" role="tablist" aria-label="Review navigatie"></div>
                <button class="reviews-next" aria-label="{{ $locale === 'fr' ? 'Suivant' : ($locale === 'en' ? 'Next' : 'Volgende') }}">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="9 18 15 12 9 6"/></svg>
                </button>
            </div>
        </div>

        <div class="reviews-cta-row">
            <a
                href="{{ config('reviews.google_review_url') }}"
                target="_blank"
                rel="noopener noreferrer"
                class="button button-secondary"
            >
                {{ $text['reviews_cta'] }}
            </a>
        </div>
    </div>
</section>
@endif
```

- [ ] **Step 5: Add reviews nav label to `$navLabels` in app.blade.php**

In `resources/views/layouts/app.blade.php`, add `'reviews'` key to all three locales in `$navLabels`:

**nl** — after `'request' => 'Start aanvraag',`:
```php
'reviews' => 'Reviews',
```

**fr** — after `'request' => 'Démarrer ma demande',`:
```php
'reviews' => 'Avis',
```

**en** — after `'request' => 'Start request',`:
```php
'reviews' => 'Reviews',
```

Then add the nav link in the `<nav class="site-nav">` section of `app.blade.php`, between the "Werkwijze" anchor and the "Contact" anchor:

```blade
<a href="{{ route('pages.home', ['locale' => $locale ?? 'nl']) }}#reviews">
    {{ $nav['reviews'] }}
</a>
```

- [ ] **Step 6: Append reviews CSS to resources/css/pages/home.css**

Append to the end of `resources/css/pages/home.css`:

```css
/* ================================
   Reviews carousel
================================ */

.section-reviews {
    background: #f8fbff;
}

.reviews-carousel {
    position: relative;
    max-width: 860px;
    margin: 0 auto;
}

.reviews-track-wrapper {
    overflow: hidden;
    border-radius: 22px;
}

.reviews-track {
    display: flex;
    transition: transform 0.45s cubic-bezier(0.4, 0, 0.2, 1);
    will-change: transform;
}

@media (prefers-reduced-motion: reduce) {
    .reviews-track {
        transition: none;
    }
}

.review-card {
    flex: 0 0 100%;
    padding: 36px 40px;
    background: var(--color-white);
    border: 1px solid var(--color-border);
    border-radius: 22px;
    box-shadow: var(--shadow-soft);
    display: flex;
    flex-direction: column;
    gap: 18px;
}

.review-stars {
    display: flex;
    gap: 3px;
}

.review-text {
    margin: 0;
    padding: 0;
    border: none;
    font-size: 1.05rem;
    line-height: 1.7;
    color: var(--color-text);
    font-style: italic;
}

.review-text p {
    margin: 0;
}

.review-text::before {
    content: "\201C";
    font-size: 2.2rem;
    line-height: 0;
    vertical-align: -0.55em;
    color: var(--color-primary);
    opacity: 0.4;
    margin-right: 4px;
}

.review-footer {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 0.9rem;
    margin-top: auto;
}

.review-footer strong {
    font-weight: 900;
    color: var(--color-primary-dark);
}

.review-location {
    color: var(--color-muted);
}

/* Controls */
.reviews-controls {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 14px;
    margin-top: 22px;
}

.reviews-prev,
.reviews-next {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 40px;
    height: 40px;
    border-radius: 999px;
    border: 1px solid var(--color-border);
    background: var(--color-white);
    color: var(--color-primary-dark);
    cursor: pointer;
    transition: background 0.18s ease, border-color 0.18s ease;
    flex-shrink: 0;
}

.reviews-prev:hover,
.reviews-next:hover {
    background: #eaf2fb;
    border-color: var(--color-primary);
    color: var(--color-primary);
}

.reviews-dots {
    display: flex;
    gap: 7px;
    align-items: center;
}

.reviews-dot {
    width: 8px;
    height: 8px;
    border-radius: 999px;
    background: var(--color-border);
    border: none;
    cursor: pointer;
    padding: 0;
    transition: background 0.2s ease, width 0.2s ease;
}

.reviews-dot.is-active {
    background: var(--color-primary);
    width: 22px;
}

.reviews-cta-row {
    text-align: center;
    margin-top: 36px;
}

/* Mobile */
@media (max-width: 680px) {
    .review-card {
        padding: 24px 20px;
        gap: 14px;
    }

    .review-text {
        font-size: 0.97rem;
    }
}
```

- [ ] **Step 7: Add `initReviewsCarousel` to app.js**

In `resources/js/app.js`, add the following function before the `document.addEventListener('DOMContentLoaded', ...)` block:

```js
function initReviewsCarousel() {
    var track = document.getElementById('reviewsTrack');
    if (!track) return;

    var cards = track.querySelectorAll('.review-card');
    var total = cards.length;
    if (total <= 1) return;

    var dotsContainer = document.getElementById('reviewsDots');
    var prevBtn = document.querySelector('.reviews-prev');
    var nextBtn = document.querySelector('.reviews-next');
    var carousel = track.closest('.reviews-carousel');

    var current = 0;
    var timer = null;
    var DELAY = 5000;
    var reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    // Build dots
    for (var i = 0; i < total; i++) {
        (function (idx) {
            var dot = document.createElement('button');
            dot.className = 'reviews-dot' + (idx === 0 ? ' is-active' : '');
            dot.setAttribute('aria-label', 'Review ' + (idx + 1));
            dot.setAttribute('role', 'tab');
            dot.addEventListener('click', function () { goTo(idx); restart(); });
            dotsContainer.appendChild(dot);
        }(i));
    }

    function goTo(idx) {
        current = ((idx % total) + total) % total;
        track.style.transform = 'translateX(-' + (current * 100) + '%)';
        dotsContainer.querySelectorAll('.reviews-dot').forEach(function (d, i) {
            d.classList.toggle('is-active', i === current);
        });
    }

    function next() { goTo(current + 1); }
    function prev() { goTo(current - 1); }

    function stop() {
        clearInterval(timer);
        timer = null;
    }

    function start() {
        if (reduced) return;
        stop();
        timer = setInterval(next, DELAY);
    }

    function restart() { stop(); start(); }

    if (prevBtn) { prevBtn.addEventListener('click', function () { prev(); restart(); }); }
    if (nextBtn) { nextBtn.addEventListener('click', function () { next(); restart(); }); }

    if (carousel) {
        carousel.addEventListener('mouseenter', stop);
        carousel.addEventListener('mouseleave', start);
        carousel.addEventListener('focusin', stop);
        carousel.addEventListener('focusout', function (e) {
            if (!carousel.contains(e.relatedTarget)) start();
        });
    }

    start();
}
```

Then in the existing `document.addEventListener('DOMContentLoaded', () => { ... })` block, add `initReviewsCarousel();` after `initScrollReveal();`:

```js
document.addEventListener('DOMContentLoaded', () => {
    initMobileMenu();
    initServicesDropdown();
    initPipeFlowAnimation();
    initScrollReveal();
    initReviewsCarousel();
    initCustomCursor();
});
```

- [ ] **Step 8: Run the failing tests — they should now pass**

```powershell
php artisan test --filter=HomepageTest
```

Expected: all 8 tests pass

- [ ] **Step 9: Run the full test suite to ensure no regressions**

```powershell
php artisan test
```

Expected: all pass

- [ ] **Step 10: Build assets**

```powershell
npm run build
```

Expected: no errors; `public/build/` updated

- [ ] **Step 11: Commit**

```powershell
git add `
    resources/views/layouts/app.blade.php `
    resources/views/pages/partials/home-page.blade.php `
    resources/css/pages/home.css `
    resources/js/app.js `
    tests/Feature/HomepageTest.php
git commit -m "feat(site): add reviews section and navigation link"
```

---

## Task E: Final Verification

- [ ] **Step 1: Footer quick check (code-only — no changes expected)**

Verify in `resources/views/layouts/app.blade.php`:
- Privacy link uses `$privacySlug` (lines ~290) ✅
- Site name uses `config('site.name')` → "Mastechnics" ✅
- Messenger link uses `https://m.me/{{ $siteContact['messenger'] }}` → `m.me/mastechnics` ✅

No changes needed.

- [ ] **Step 2: Run final full test suite**

```powershell
php artisan test
```

Expected: all pass, no regressions

- [ ] **Step 3: PHP-lint changed Blade files**

```powershell
php artisan view:clear
php artisan view:cache
```

Expected: no errors

- [ ] **Step 4: Final asset build confirmation**

```powershell
npm run build
```

Expected: exit 0

- [ ] **Step 5: Git status check**

```powershell
git status
git log --oneline -5
```

Expected: clean working tree; 3 new commits visible

---

## Manual Browser Checklist

After implementation, verify in a browser (desktop + mobile viewport):

- [ ] Quote edit page opens for request without quote (`/admin/requests/{id}/quote/edit`)
- [ ] Quote edit page opens for request with existing quote items
- [ ] Services hover on desktop opens dropdown
- [ ] Clicking "Diensten" text navigates to `#diensten`
- [ ] Services dropdown items (individual service pages) are clickable
- [ ] Mobile hamburger → Services row shows text link + chevron side by side
- [ ] Mobile chevron toggles dropdown; text link navigates
- [ ] Reviews nav link scrolls to `#reviews`
- [ ] Reviews carousel autoplays every ~5 seconds
- [ ] Prev/Next buttons advance carousel
- [ ] Hovering carousel pauses autoplay
- [ ] "Laat een review achter" button opens Google link in new tab
- [ ] Public request form (`/nl/aanvraag`) still submits correctly
- [ ] No console errors
