---
name: sanitair-pipe-flow-animation
description: Scroll-triggered animation for the Sanitair service page pipe-flow section — pipe draw, card reveal, node pulse, water glow highlight
metadata:
  type: project
---

# Sanitair Pipe-Flow Scroll Animation — Design Spec

**Date:** 2026-06-03
**Scope:** Sanitair service page only (`.service-page--plumbing`)
**Approach:** CSS transitions + CSS keyframes triggered by a single `IntersectionObserver` class toggle
**Status:** Approved, ready for implementation

---

## 1. Architecture

Two files change. Nothing else is touched.

| File | Change |
|---|---|
| `resources/css/pages/service.css` | Modify 3 existing rules; append one new animation block |
| `resources/js/app.js` | Add `initPipeFlowAnimation()`; wrap `DOMContentLoaded` to call both init functions |

No Blade changes. No Vite config changes. No new files. No new npm packages.

The Observer adds `.is-in-view` to `.use-cases-list` once, then disconnects. All visual behaviour — timing, easing, stagger, glow, reduced-motion fallback — lives in CSS.

---

## 2. CSS Animation Design

### 2.1 Modifications to existing rules

**`.service-page--plumbing .use-cases-list::before`** (the vertical pipe line)

Add:
```css
transform: scaleY(0);
transform-origin: top center;
transition: transform 0.75s cubic-bezier(0.22, 1, 0.36, 1);
```

The cubic-bezier is fast-start, smooth-settle — mimics liquid filling downward.

**`.service-page--plumbing .use-cases-list li`** (the cards)

Add initial hidden state and extend transition:
```css
opacity: 0;
transform: translateY(10px);
transition: border-left-color 0.18s ease, box-shadow 0.18s ease,
            opacity 0.45s ease, transform 0.45s ease;
```

The existing hover transitions (`border-left-color`, `box-shadow`) are preserved.

**`.service-page--plumbing .use-cases-list li::before`** (the nodes)

Replace bare `transform: translateY(-50%)` with initial hidden state:
```css
transform: translateY(-50%) scale(0.4);
opacity: 0;
```

No `transition` on this element — a `@keyframes` animation handles both the scale-in and the pulse in one composed step.

---

### 2.2 New animation block (appended to service.css)

#### Pipe draw — revealed state
```css
.service-page--plumbing .use-cases-list.is-in-view::before {
    transform: scaleY(1);
}
```

#### Card reveal — revealed state + stagger
```css
.service-page--plumbing .use-cases-list.is-in-view li {
    opacity: 1;
    transform: translateY(0);
}
.service-page--plumbing .use-cases-list.is-in-view li:nth-child(1) { transition-delay: 0.08s; }
.service-page--plumbing .use-cases-list.is-in-view li:nth-child(2) { transition-delay: 0.18s; }
.service-page--plumbing .use-cases-list.is-in-view li:nth-child(3) { transition-delay: 0.28s; }
.service-page--plumbing .use-cases-list.is-in-view li:nth-child(4) { transition-delay: 0.38s; }
.service-page--plumbing .use-cases-list.is-in-view li:nth-child(5) { transition-delay: 0.48s; }
```

#### Node reveal keyframe
One animation composes the scale-in and the brightening pulse:
```css
@keyframes nodeReveal {
    0%   { opacity: 0; transform: translateY(-50%) scale(0.4);
           box-shadow: 0 0 0 3px transparent; }
    65%  { opacity: 1; transform: translateY(-50%) scale(1.08);
           box-shadow: 0 0 0 5px rgba(15, 102, 194, 0.22); }
    100% { opacity: 1; transform: translateY(-50%) scale(1);
           box-shadow: 0 0 0 3px rgba(15, 102, 194, 0.10); }
}

.service-page--plumbing .use-cases-list.is-in-view li::before {
    animation: nodeReveal 0.55s ease-out forwards;
}
.service-page--plumbing .use-cases-list.is-in-view li:nth-child(1)::before { animation-delay: 0.12s; }
.service-page--plumbing .use-cases-list.is-in-view li:nth-child(2)::before { animation-delay: 0.22s; }
.service-page--plumbing .use-cases-list.is-in-view li:nth-child(3)::before { animation-delay: 0.32s; }
.service-page--plumbing .use-cases-list.is-in-view li:nth-child(4)::before { animation-delay: 0.42s; }
.service-page--plumbing .use-cases-list.is-in-view li:nth-child(5)::before { animation-delay: 0.52s; }
```

#### Water / flow glow highlight

The `::after` pseudo on `.use-cases-list` is unused — the pipe uses `::before`.

**How the gradient movement works:**
`background-size: 100% 200%` makes the gradient twice the element height. The glow peak sits at 50% of that gradient, which equals exactly one element-height from the gradient top. Using the CSS background-position formula `bg_top = (element_H - bg_H) * (position_y / 100)`:
- At `200%` → glow sits above element (invisible)
- At `100%` → glow is at element top (entering)
- At `0%` → glow is at element bottom (exiting)
- At `-100%` → glow has exited below (invisible)

The animation travels `200% → -100%`, passing through the visible range in the middle. The gradient's own `transparent` edges create a natural soft entry and exit.

```css
@keyframes pipeFlowGlow {
    from { background-position: 0% 200%; }
    to   { background-position: 0% -100%; }
}

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

.service-page--plumbing .use-cases-list.is-in-view::after {
    opacity: 1;
    animation: pipeFlowGlow 3.5s cubic-bezier(0.37, 0, 0.63, 1) 1.2s 1 forwards;
}
```

The 1.2s delay lets the pipe finish drawing and most cards reveal before the glow begins. One pass only — not a loop. After completing, the glow rests invisible below the pipe (`animation-fill-mode: forwards`).

---

### 2.3 Animation sequence

| Time after `.is-in-view` | Event |
|---|---|
| 0ms | Pipe starts drawing downward (0.75s, fast-settle easing) |
| 80ms | Card 1 fades + slides up (0.45s) |
| 120ms | Node 1 scales in + pulses (0.55s) |
| 180–480ms | Cards 2–5 stagger in |
| 220–520ms | Nodes 2–5 follow |
| 1,200ms | Water glow begins single pass (3.5s) |
| 4,700ms | Glow exits below pipe, rests invisible |

---

## 3. JS IntersectionObserver Design

### 3.1 `initPipeFlowAnimation()` — full function

```javascript
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
```

**Guard:** `.service-page--plumbing .use-cases-list` is only present on the Sanitair page. On all other pages `querySelector` returns `null` and the function returns immediately — zero allocations.

**`prefers-reduced-motion` check:** If set, the function returns before creating the observer. The CSS media query already shows all elements at full opacity and transform regardless of `.is-in-view`, so no class toggle is needed.

**Observer behaviour:** Fires when 15% of the list is visible (`threshold: 0.15`). Adds `.is-in-view` to the list once, then calls `obs.unobserve()` to disconnect. No repeated callbacks.

**What JS does NOT do:**
- No `scroll` event listener
- No `requestAnimationFrame` loop
- No `element.style.*` manipulation
- No timing or delay logic (all in CSS)
- No `innerHTML`
- No user-controlled data

### 3.2 `DOMContentLoaded` change

Current:
```javascript
document.addEventListener('DOMContentLoaded', initMobileMenu);
```

Updated:
```javascript
document.addEventListener('DOMContentLoaded', () => {
    initMobileMenu();
    initPipeFlowAnimation();
});
```

---

## 4. Reduced-Motion Behaviour

### CSS block

```css
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
```

This overrides all initial hidden states. The pipe, cards, and nodes are fully visible on page load with no transition. The water glow is hidden entirely. Hover effects on cards are preserved (only `border-left-color` and `box-shadow` are in the reduced-motion transition). The JS guard returns before creating the observer.

---

## 5. Mobile Behaviour

### CSS block

```css
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

**Card slide distance:** Reduced from `10px` to `6px` on mobile. The tighter card layout on small screens means a smaller offset is less visually jarring.

**Glow position:** Aligned with mobile pipe offsets (`left: 15px; top: 26px; bottom: 26px`) matching the existing mobile override for `::before`.

**`filter: none`:** Removes `blur(1px)` on mobile. The blur is purely decorative and avoiding it eliminates any GPU compositing cost on lower-power devices.

**JS:** No mobile-specific JS. The same `IntersectionObserver` runs at `threshold: 0.15` on all viewports.

---

## 6. Files to Change

| File | Type of change |
|---|---|
| `resources/css/pages/service.css` | Modify 3 existing rules; append new animation block (~80 lines) |
| `resources/js/app.js` | Add `initPipeFlowAnimation()` (~18 lines); update `DOMContentLoaded` wrapper |

### Files explicitly not changed
- `resources/views/pages/partials/service-page.blade.php`
- `vite.config.js`
- Any other service page CSS or JS
- Admin, request flow, or any other route

---

## 7. Acceptance Criteria

1. On the Sanitair page (`/nl/sanitair`, `/fr/sanitaire`, `/en/plumbing`), the pipe-flow section animates when scrolled into view.
2. The vertical pipe line grows downward from the top when the section enters the viewport.
3. The five use-case cards reveal one by one with a soft fade-in and upward slide, staggered ~100ms apart.
4. The blue circular nodes scale in and briefly brighten (pulse) as each card reveals.
5. A soft blue glow travels down the pipe line once, ~1.2s after the section enters view.
6. The glow completes its pass and disappears — it does not loop repeatedly.
7. Scrolling back up and re-entering the viewport does **not** re-trigger the animation (observer disconnects after first trigger).
8. With `prefers-reduced-motion: reduce` active, all elements are visible immediately on page load with no animation.
9. Hover effects on cards (border-left and box-shadow) continue to work on all devices.
10. No animation runs on any other service page (heating, airco, ventilation, water-softeners, cold-rooms).
11. The page passes `php artisan test` with no regressions.
12. `npm run build` completes without errors.

---

## 8. Test Plan

### Automated
- `php artisan test` — full suite, no regressions
- `npm run build` — no Vite errors, no missing asset warnings

### Browser — desktop
- [ ] Open `/nl/sanitair`, scroll the use-cases section into view — confirm pipe draws, cards stagger, nodes pulse
- [ ] Confirm the water glow makes one pass then disappears
- [ ] Scroll back up, scroll down again — confirm animation does **not** re-trigger
- [ ] Hover each card — confirm `border-left-color` and `box-shadow` hover still works
- [ ] Open `/fr/sanitaire` and `/en/plumbing` — same animation expected

### Browser — reduced motion
- [ ] Enable `prefers-reduced-motion: reduce` in OS settings or DevTools (`Rendering > Emulate CSS media feature`)
- [ ] Reload `/nl/sanitair` — confirm all cards and pipe are fully visible immediately with no animation
- [ ] Confirm no pipe draw, no card slide, no glow

### Browser — mobile
- [ ] Open DevTools mobile emulation (375px, iPhone 14) for `/nl/sanitair`
- [ ] Scroll use-cases into view — confirm animation runs cleanly, no jank
- [ ] Confirm `translateY` offset feels appropriate (6px, tighter than desktop)

### Other service pages — regression check
- [ ] Open `/nl/verwarming`, `/nl/airco`, `/nl/ventilatie` — confirm no animation, no hidden elements, no layout change
- [ ] Confirm `.use-cases-list` on non-plumbing pages shows the standard checkmark list with no opacity/transform changes
