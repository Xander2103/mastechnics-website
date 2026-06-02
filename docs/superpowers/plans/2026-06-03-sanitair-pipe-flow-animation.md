# Sanitair Pipe-Flow Scroll Animation — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a premium scroll-triggered animation to the Sanitair service page pipe-flow section — pipe draws downward, cards stagger in, nodes pulse, a soft water glow travels once along the pipe.

**Architecture:** A single `IntersectionObserver` in `app.js` adds `.is-in-view` to `.use-cases-list` once when 15% of it is visible, then disconnects. All visual behaviour — timing, easing, stagger, keyframes, reduced-motion fallback — lives in `service.css`, scoped to `.service-page--plumbing`.

**Tech Stack:** Laravel 12, Blade, Vite, vanilla CSS (transitions + `@keyframes`), vanilla JS (`IntersectionObserver`)

**Spec:** `docs/superpowers/specs/2026-06-03-sanitair-pipe-flow-animation-design.md`

---

## File Map

| File | Lines affected | Role |
|---|---|---|
| `resources/css/pages/service.css` | Lines 226–242, 245–257, 265–279 modified; ~90 lines appended | All animation CSS |
| `resources/js/app.js` | Lines 17–18 modified; ~18 lines added | IntersectionObserver trigger |

**Not touched:** any Blade file, `vite.config.js`, any other CSS file, admin, request flow, DB schema.

---

## Task 1: CSS — Pipe draw animation

**Files:**
- Modify: `resources/css/pages/service.css:226-242`

**Context:** The `.use-cases-list::before` rule draws the vertical pipe line. It currently has no transform or transition. We add an initial `scaleY(0)` collapsed state with `transform-origin: top center` so it grows downward, and a smooth transition that activates when `.is-in-view` is added to the parent list.

The parent `.use-cases-list` already has `position: relative` (line 222) — the absolute `::before` is correctly contained.

- [ ] **Step 1: Modify the existing `::before` rule (lines 226–242)**

Replace the rule at line 226 with this version (add three properties after `pointer-events: none`):

```css
/* Vertical pipe line connecting all nodes */
.service-page--plumbing .use-cases-list::before {
    content: "";
    position: absolute;
    left: 19px;
    top: 30px;
    bottom: 30px;
    width: 2px;
    border-radius: 2px;
    background: linear-gradient(
        to bottom,
        rgba(15, 102, 194, 0.04),
        rgba(15, 102, 194, 0.28) 12%,
        rgba(15, 102, 194, 0.28) 88%,
        rgba(15, 102, 194, 0.04)
    );
    pointer-events: none;
    transform: scaleY(0);
    transform-origin: top center;
    transition: transform 0.75s cubic-bezier(0.22, 1, 0.36, 1);
}
```

- [ ] **Step 2: Verify the pipe is hidden**

Run `npm run build`. Expected output ends with `✓ built in Xms` — no errors.

Open `/nl/sanitair` in a browser. The pipe line between cards should be invisible (pipe is collapsed to height 0).

- [ ] **Step 3: Verify the reveal works via DevTools**

Open browser DevTools Console on `/nl/sanitair` and run:

```javascript
document.querySelector('.service-page--plumbing .use-cases-list').classList.add('is-in-view')
```

Expected: the pipe line grows smoothly downward from the top over ~0.75s with a fast-start ease. (The `.is-in-view` CSS rule comes in Task 2 — if the pipe does not appear yet, that is expected. This step verifies only that the transition property is wired correctly by confirming `transition` is present in DevTools computed styles for `::before`.)

---

## Task 2: CSS — Card and node reveal + stagger

**Files:**
- Modify: `resources/css/pages/service.css:245-257` (li rule)
- Modify: `resources/css/pages/service.css:265-279` (li::before rule)
- Append: `resources/css/pages/service.css` (new animation block — revealed states + stagger)

**Context:** Cards start hidden (`opacity: 0`, `translateY(10px)`). Nodes start hidden (`scale(0.4)`, `opacity: 0`). When `.is-in-view` is present on the parent list, CSS transitions reveal the cards with a stagger via `transition-delay` on `:nth-child`. Nodes use a `@keyframes nodeReveal` animation (instead of a plain transition) because it needs to compose the scale-in and the brightening pulse in one step — mixing `transition` and `animation` on the same element for the same properties causes conflicts.

- [ ] **Step 1: Modify the existing `li` rule (lines 245–257)**

Replace the rule at line 245 with this version (add `opacity`, `transform`, extend `transition`):

```css
/* Each situation becomes a connected card */
.service-page--plumbing .use-cases-list li {
    position: relative;
    padding: 16px 24px 16px 60px;
    margin-bottom: 10px;
    background: var(--color-white);
    border: 1px solid var(--color-border);
    border-left: 3px solid rgba(15, 102, 194, 0.20);
    border-radius: var(--radius-medium);
    box-shadow: 0 2px 12px rgba(15, 53, 87, 0.05);
    color: var(--color-text);
    line-height: 1.55;
    opacity: 0;
    transform: translateY(10px);
    transition: border-left-color 0.18s ease, box-shadow 0.18s ease,
                opacity 0.45s ease, transform 0.45s ease;
}
```

- [ ] **Step 2: Modify the existing `li::before` rule (lines 265–279)**

Replace the rule at line 265 with this version (change `transform`, add `opacity`):

```css
/* Override checkmark with pipe-node circle */
.service-page--plumbing .use-cases-list li::before {
    content: "";
    position: absolute;
    left: 12px;
    top: 50%;
    transform: translateY(-50%) scale(0.4);
    opacity: 0;
    width: 16px;
    height: 16px;
    border-radius: 50%;
    background: var(--color-white);
    border: 2.5px solid var(--color-primary);
    box-shadow: 0 0 0 3px rgba(15, 102, 194, 0.10);
    color: transparent;
    font-size: 0;
}
```

- [ ] **Step 3: Append the card revealed state + stagger block**

Append to the end of `service.css`, after line 308 (after the existing `@media (max-width: 680px)` block):

```css
/* ================================
   Pipe-flow scroll animation
   Triggered by .is-in-view on .use-cases-list
   Scoped to .service-page--plumbing only
================================ */

/* Pipe: revealed state */
.service-page--plumbing .use-cases-list.is-in-view::before {
    transform: scaleY(1);
}

/* Cards: revealed state */
.service-page--plumbing .use-cases-list.is-in-view li {
    opacity: 1;
    transform: translateY(0);
}

/* Cards: stagger delays */
.service-page--plumbing .use-cases-list.is-in-view li:nth-child(1) { transition-delay: 0.08s; }
.service-page--plumbing .use-cases-list.is-in-view li:nth-child(2) { transition-delay: 0.18s; }
.service-page--plumbing .use-cases-list.is-in-view li:nth-child(3) { transition-delay: 0.28s; }
.service-page--plumbing .use-cases-list.is-in-view li:nth-child(4) { transition-delay: 0.38s; }
.service-page--plumbing .use-cases-list.is-in-view li:nth-child(5) { transition-delay: 0.48s; }

/* Nodes: reveal + brighten keyframe */
@keyframes nodeReveal {
    0%   { opacity: 0; transform: translateY(-50%) scale(0.4);
           box-shadow: 0 0 0 3px transparent; }
    65%  { opacity: 1; transform: translateY(-50%) scale(1.08);
           box-shadow: 0 0 0 5px rgba(15, 102, 194, 0.22); }
    100% { opacity: 1; transform: translateY(-50%) scale(1);
           box-shadow: 0 0 0 3px rgba(15, 102, 194, 0.10); }
}

/* Nodes: animation trigger */
.service-page--plumbing .use-cases-list.is-in-view li::before {
    animation: nodeReveal 0.55s ease-out forwards;
}

/* Nodes: stagger delays (slightly after matching card) */
.service-page--plumbing .use-cases-list.is-in-view li:nth-child(1)::before { animation-delay: 0.12s; }
.service-page--plumbing .use-cases-list.is-in-view li:nth-child(2)::before { animation-delay: 0.22s; }
.service-page--plumbing .use-cases-list.is-in-view li:nth-child(3)::before { animation-delay: 0.32s; }
.service-page--plumbing .use-cases-list.is-in-view li:nth-child(4)::before { animation-delay: 0.42s; }
.service-page--plumbing .use-cases-list.is-in-view li:nth-child(5)::before { animation-delay: 0.52s; }
```

- [ ] **Step 4: Verify via DevTools**

Run `npm run build`. Expected: `✓ built in Xms`.

Open `/nl/sanitair`. All five cards should be invisible (opacity 0).

In DevTools Console:

```javascript
document.querySelector('.service-page--plumbing .use-cases-list').classList.add('is-in-view')
```

Expected:
- Pipe line grows downward from top (~0.75s)
- Card 1 fades in and slides up; cards 2–5 follow ~100ms apart
- Each node circle scales up from a small dot and briefly brightens
- Hover a card after animation — `border-left` should turn solid blue

---

## Task 3: CSS — Water flow glow highlight

**Files:**
- Append: `resources/css/pages/service.css` (keyframe + `::after` rules, added to the animation block from Task 2)

**Context:** The `::after` pseudo-element on `.use-cases-list` is currently unused (the pipe uses `::before`). A 2px absolute strip, same position as the pipe, carries an animated gradient band. `background-size: 100% 200%` makes the gradient twice the element height; the glow peak sits at 50% of that = one element-height from the gradient top. `background-position-y: 200%` parks the glow above the element (invisible); `-100%` parks it below. Animating `200% → -100%` passes through the visible range in the middle. The gradient's own transparent edges produce a natural soft entry and exit — no separate opacity keyframes needed. `filter: blur(1px)` adds a barely-there glow softness. One pass only, `animation-iteration-count: 1`, `animation-fill-mode: forwards` — rests invisible at the bottom after completion.

- [ ] **Step 1: Append the glow keyframe and `::after` rules**

Continue appending to the animation block at the end of `service.css` (after the node stagger rules from Task 2):

```css
/* Water glow: keyframe */
@keyframes pipeFlowGlow {
    from { background-position: 0% 200%; }
    to   { background-position: 0% -100%; }
}

/* Water glow: ::after initial state */
.service-page--plumbing .use-cases-list::after {
    content: "";
    position: absolute;
    left: 19px;
    top: 30px;
    bottom: 30px;
    width: 2px;
    border-radius: 2px;
    pointer-events: none;
    background: linear-gradient(
        to bottom,
        transparent 0%,
        rgba(15, 102, 194, 0.50) 50%,
        transparent 100%
    );
    background-size: 100% 200%;
    background-repeat: no-repeat;
    background-position: 0% 200%;
    filter: blur(1px);
    opacity: 0;
}

/* Water glow: revealed — one pass down the pipe */
.service-page--plumbing .use-cases-list.is-in-view::after {
    opacity: 1;
    animation: pipeFlowGlow 3.5s cubic-bezier(0.37, 0, 0.63, 1) 1.2s 1 forwards;
}
```

- [ ] **Step 2: Verify via DevTools**

Run `npm run build`. Expected: `✓ built in Xms`.

Open `/nl/sanitair`. In DevTools Console:

```javascript
document.querySelector('.service-page--plumbing .use-cases-list').classList.add('is-in-view')
```

Expected:
- Pipe draws, cards and nodes appear (as in Task 2)
- After ~1.2s, a soft blue glow starts at the top of the pipe and travels slowly downward
- The glow fades out naturally as it exits the bottom of the pipe (~3.5s travel time)
- After the glow completes, it does not loop — the pipe is static

If the glow is too bright or too fast: `rgba(15, 102, 194, 0.50)` controls intensity; `3.5s` controls speed. Both can be tuned here.

---

## Task 4: app.js — IntersectionObserver

**Files:**
- Modify: `resources/js/app.js`

**Context:** The current `app.js` exports `initMobileMenu()` and calls it in a `DOMContentLoaded` listener. We add `initPipeFlowAnimation()` following the same pattern. The function guards immediately with `querySelector` (only present on Sanitair) and with `matchMedia('prefers-reduced-motion: reduce')` — if reduced motion is set, the function returns before allocating an observer. The CSS `prefers-reduced-motion` block already shows all elements at full opacity/transform in that case, so no JS involvement is needed. `threshold: 0.15` fires when 15% of the list is visible. `obs.unobserve()` after the first trigger ensures the animation never re-fires on scroll-back.

- [ ] **Step 1: Replace the full contents of `app.js`**

```javascript
import './bootstrap';
import './request-form';

function initMobileMenu() {
    const header = document.querySelector('.site-header');
    const toggle = document.querySelector('.mobile-menu-toggle');

    if (!header || !toggle) {
        return;
    }

    toggle.addEventListener('click', () => {
        header.classList.toggle('is-open');
    });
}

function initPipeFlowAnimation() {
    const list = document.querySelector('.service-page--plumbing .use-cases-list');
    if (!list) return;

    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

    const observer = new IntersectionObserver(
        (entries, obs) => {
            entries.forEach(entry => {
                if (!entry.isIntersecting) return;
                entry.target.classList.add('is-in-view');
                obs.unobserve(entry.target);
            });
        },
        { threshold: 0.15 }
    );

    observer.observe(list);
}

document.addEventListener('DOMContentLoaded', () => {
    initMobileMenu();
    initPipeFlowAnimation();
});
```

- [ ] **Step 2: Verify no JS errors**

Run `npm run build`. Expected: `✓ built in Xms`.

Open `/nl/sanitair`. Open DevTools Console — confirm no JS errors on page load.

- [ ] **Step 3: Verify scroll trigger works**

Scroll the use-cases section out of view (scroll to top of page so the section is below the fold). Then scroll down past 15% of the list.

Expected: the animation fires automatically — pipe draws, cards stagger in, nodes pulse, glow travels after 1.2s.

- [ ] **Step 4: Verify one-time trigger**

Scroll back up so the section is out of view. Scroll down again.

Expected: animation does **not** re-trigger. Cards remain visible. Pipe remains visible.

- [ ] **Step 5: Verify other pages are unaffected**

Open `/nl/verwarming`. Open DevTools Console — confirm no JS errors. Open the Elements panel — confirm `.use-cases-list` does not have `.is-in-view` and shows the standard checkmark list with normal opacity.

---

## Task 5: CSS — Reduced-motion and mobile overrides

**Files:**
- Append: `resources/css/pages/service.css` (two new blocks added to the animation section)

**Context:** The `prefers-reduced-motion` block overrides all initial hidden states so elements are fully visible on page load — the JS guard returns early so no observer runs, but the CSS fallback works even if the JS somehow does run. The existing mobile block (lines 292–308) handles layout; the new mobile block handles animation-specific overrides only: smaller `translateY` offset for cards on tight screens, `::after` repositioned to match mobile pipe offsets, `filter: none` on `::after` to avoid compositing overhead.

- [ ] **Step 1: Append the `prefers-reduced-motion` block**

Continue appending to the animation block at the end of `service.css`:

```css
/* Reduced motion: show everything immediately, disable all animation */
@media (prefers-reduced-motion: reduce) {
    .service-page--plumbing .use-cases-list::before {
        transform: scaleY(1);
        transition: none;
    }

    .service-page--plumbing .use-cases-list li {
        opacity: 1;
        transform: none;
        transition: border-left-color 0.18s ease, box-shadow 0.18s ease;
    }

    .service-page--plumbing .use-cases-list li::before {
        opacity: 1;
        transform: translateY(-50%);
        animation: none;
    }

    .service-page--plumbing .use-cases-list::after {
        display: none;
    }
}

/* Mobile animation overrides */
@media (max-width: 680px) {
    .service-page--plumbing .use-cases-list li {
        transform: translateY(6px);
    }

    .service-page--plumbing .use-cases-list::after {
        left: 15px;
        top: 26px;
        bottom: 26px;
        filter: none;
    }
}
```

- [ ] **Step 2: Verify reduced-motion fallback**

In Chrome DevTools: open **Rendering** tab (three-dot menu → More tools → Rendering). Find **Emulate CSS media feature `prefers-reduced-motion`** and set it to `reduce`.

Reload `/nl/sanitair`. Expected:
- All five cards are visible immediately on page load
- Pipe line is visible immediately
- No animation plays on scroll
- Hovering cards still changes the border-left colour and box-shadow (hover transitions preserved)

Return emulation to `no-preference`.

- [ ] **Step 3: Verify mobile layout**

In DevTools, switch to mobile emulation (iPhone 14, 390px width). Reload `/nl/sanitair`.

Scroll the use-cases section into view. Expected:
- Animation fires cleanly
- Card slide distance feels appropriate (6px, tighter than desktop 10px)
- No jank or layout shift
- Glow `::after` is aligned with the mobile pipe (left: 15px, top: 26px, bottom: 26px)
- No blur on the glow (filter: none on mobile)

---

## Task 6: Build and test

**Files:** None changed — verification only.

- [ ] **Step 1: Full production build**

```
npm run build
```

Expected output (exact build time varies):
```
✓ built in Xms
```

No warnings about missing assets, unresolved imports, or CSS parse errors.

- [ ] **Step 2: PHP test suite**

```
php artisan test
```

Expected: all existing tests pass. Zero failures, zero errors. This confirms no regression to the request flow, admin, models, or routes.

- [ ] **Step 3: Full browser acceptance checklist**

Work through every item in this list. Do not mark the task complete until all pass.

**Desktop (1280px+):**
- [ ] `/nl/sanitair` — scroll section into view: pipe draws, cards stagger, nodes pulse, glow passes
- [ ] `/nl/sanitair` — scroll back up, scroll down again: animation does NOT re-trigger
- [ ] `/nl/sanitair` — hover each card: `border-left` turns solid blue, `box-shadow` deepens
- [ ] `/fr/sanitaire` — same animation behaviour
- [ ] `/en/plumbing` — same animation behaviour

**Reduced motion:**
- [ ] Enable `prefers-reduced-motion: reduce` in DevTools Rendering tab
- [ ] Reload `/nl/sanitair` — all cards and pipe visible immediately, no animation
- [ ] Hover effects still work
- [ ] Restore to `no-preference`

**Mobile (390px, iPhone 14 emulation):**
- [ ] `/nl/sanitair` — scroll section into view: animation runs without jank
- [ ] Card slide offset is 6px (visually tighter than desktop)
- [ ] Glow travels correctly along mobile pipe position

**Regression — other service pages:**
- [ ] `/nl/verwarming` — checkmark list visible, no opacity/transform change, no animation
- [ ] `/nl/airco` — same
- [ ] `/nl/ventilatie` — same

---

## Task 7: Commit

**Files:**
- `resources/css/pages/service.css`
- `resources/js/app.js`

- [ ] **Step 1: Stage only the two changed files**

```
git add resources/css/pages/service.css resources/js/app.js
```

- [ ] **Step 2: Confirm what is staged**

```
git diff --cached --stat
```

Expected output:
```
 resources/css/pages/service.css | ~90 insertions(+), 6 changes(-)
 resources/js/app.js             | ~18 insertions(+), 1 change(-)
 2 files changed
```

No other files should appear.

- [ ] **Step 3: Commit**

```
git commit -m "feat: add scroll animation to sanitair pipe flow

Co-Authored-By: Claude Sonnet 4.6 <noreply@anthropic.com>"
```

Expected:
```
[main XXXXXXX] feat: add scroll animation to sanitair pipe flow
 2 files changed
```

---

## Acceptance Criteria (from spec)

All of these must be true before declaring done:

1. Pipe line grows downward on scroll into view
2. Five cards stagger in with fade + slide, ~100ms apart
3. Nodes scale in and briefly brighten when revealed
4. Soft blue glow travels once along the pipe after 1.2s; does not loop
5. Scrolling back does not re-trigger animation
6. `prefers-reduced-motion: reduce` → fully visible immediately, no animation
7. Hover effects on cards work on all devices
8. No animation on any other service page
9. `php artisan test` — zero failures
10. `npm run build` — no errors
