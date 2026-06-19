import './bootstrap';
import './request-form';

function initMobileMenu() {
    const header = document.querySelector('.site-header');
    const toggle = document.querySelector('.mobile-menu-toggle');

    if (!header || !toggle) return;

    toggle.addEventListener('click', () => {
        header.classList.toggle('is-open');
    });
}

function initServicesDropdown() {
    const dropdown = document.querySelector('.services-dropdown');
    const dropdownToggle = document.querySelector('.services-dropdown-toggle');

    if (!dropdown || !dropdownToggle) return;

    const isMobile = () => window.innerWidth <= 680;
    const CLOSE_DELAY = 250;
    let closeTimer = null;

    function clearCloseTimer() {
        if (closeTimer) {
            clearTimeout(closeTimer);
            closeTimer = null;
        }
    }

    function openDropdown() {
        clearCloseTimer();
        dropdown.classList.add('is-open');
        dropdownToggle.setAttribute('aria-expanded', 'true');
    }

    function closeDropdown() {
        clearCloseTimer();
        dropdown.classList.remove('is-open');
        dropdownToggle.setAttribute('aria-expanded', 'false');
    }

    function scheduleClose() {
        clearCloseTimer();
        closeTimer = setTimeout(closeDropdown, CLOSE_DELAY);
    }

    // Desktop: open immediately on hover, close after a short delay so the
    // pointer has time to travel from the trigger into the dropdown panel.
    // Re-entering (trigger or panel) cancels the pending close.
    dropdown.addEventListener('mouseenter', () => {
        if (isMobile()) return;
        openDropdown();
    });

    dropdown.addEventListener('mouseleave', () => {
        if (isMobile()) return;
        scheduleClose();
    });

    // Keyboard: focus anywhere inside the dropdown keeps it open; focus
    // moving outside closes it immediately.
    dropdown.addEventListener('focusin', () => {
        if (isMobile()) return;
        openDropdown();
    });

    dropdown.addEventListener('focusout', (e) => {
        if (isMobile()) return;
        if (dropdown.contains(e.relatedTarget)) return;
        closeDropdown();
    });

    // Mobile: click/tap toggles accordion
    dropdownToggle.addEventListener('click', (e) => {
        if (!isMobile()) return;
        e.stopPropagation();
        const isOpen = dropdown.classList.toggle('is-open');
        dropdownToggle.setAttribute('aria-expanded', String(isOpen));
    });

    // Close mobile dropdown when clicking outside
    document.addEventListener('click', (e) => {
        if (isMobile() && !dropdown.contains(e.target)) {
            closeDropdown();
        }
    });

    // Close mobile dropdown when mobile menu closes
    const menuToggle = document.querySelector('.mobile-menu-toggle');
    if (menuToggle) {
        menuToggle.addEventListener('click', () => {
            closeDropdown();
        });
    }

    // Escape closes the dropdown and returns focus to the trigger
    dropdown.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            closeDropdown();
            dropdownToggle.focus();
        }
    });
}

function initPipeFlowAnimation() {
    const list = document.querySelector('.service-page--plumbing .use-cases-list');
    if (!list) return;

    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        list.classList.add('is-in-view');
        return;
    }

    if (!('IntersectionObserver' in window)) {
        list.classList.add('is-in-view');
        return;
    }

    // rootMargin starts animation 80px before the list enters the viewport
    const observer = new IntersectionObserver(
        (entries, obs) => {
            entries.forEach(entry => {
                if (!entry.isIntersecting) return;
                entry.target.classList.add('is-in-view');
                obs.unobserve(entry.target);
            });
        },
        { threshold: 0, rootMargin: '0px 0px 80px 0px' }
    );

    observer.observe(list);
}

function initScrollReveal() {
    const elements = document.querySelectorAll('.reveal');
    if (!elements.length) return;

    // Show immediately when motion is reduced or IO is unavailable
    if (
        window.matchMedia('(prefers-reduced-motion: reduce)').matches ||
        !('IntersectionObserver' in window)
    ) {
        elements.forEach(el => el.classList.add('is-visible'));
        return;
    }

    const observer = new IntersectionObserver(
        (entries, obs) => {
            entries.forEach(entry => {
                if (!entry.isIntersecting) return;
                entry.target.classList.add('is-visible');
                obs.unobserve(entry.target);
            });
        },
        { threshold: 0.12, rootMargin: '0px 0px -40px 0px' }
    );

    elements.forEach(el => observer.observe(el));
}

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

function initCustomCursor() {
    // Desktop / fine-pointer only
    if (!window.matchMedia('(pointer: fine)').matches) return;

    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    const cursor = document.createElement('div');
    cursor.className = 'custom-cursor';
    cursor.setAttribute('aria-hidden', 'true');

    const img = document.createElement('img');
    img.src = '/assets/images/cursor-pipe.png';
    img.alt = '';
    img.setAttribute('draggable', 'false');
    cursor.appendChild(img);
    document.body.appendChild(cursor);
    document.body.classList.add('custom-cursor-enabled');

    let pendingRaf = false;
    let cx = -100;
    let cy = -100;

    function render() {
        cursor.style.transform = `translate(${cx - 18}px, ${cy - 18}px)`;
        pendingRaf = false;
    }

    document.addEventListener('mousemove', (e) => {
        cx = e.clientX;
        cy = e.clientY;
        if (!pendingRaf) {
            pendingRaf = true;
            requestAnimationFrame(render);
        }
    });

    const interactiveSelector =
        'a, button, [role="button"], .button, ' +
        '.hero-hex, .hero-service-panel, .service-card, .service-card-link';

    document.addEventListener('mouseover', (e) => {
        cursor.classList.toggle('is-hovering', !!e.target.closest(interactiveSelector));
    });

    function isInteractiveTarget(target) {
        return Boolean(target.closest(
            'a, button, input, textarea, select, label, summary,' +
            ' [role="button"], [role="link"], [role="menuitem"],' +
            ' [tabindex]:not([tabindex="-1"]),' +
            ' .button, .nav-link, .language-link, .quicknav-link,' +
            ' .service-card, .hero-hex, .hero-service-panel,' +
            ' .custom-cursor-disabled, header, form'
        ));
    }

    if (!reducedMotion) {
        document.addEventListener('click', (event) => {
            if (isInteractiveTarget(event.target)) return;
            cursor.classList.remove('is-spinning');
            void cursor.offsetWidth; // restart animation
            cursor.classList.add('is-spinning');
        });
        img.addEventListener('animationend', () => cursor.classList.remove('is-spinning'));
    }

    // Hide cursor when mouse leaves the page
    document.documentElement.addEventListener('mouseleave', () => {
        cursor.style.opacity = '0';
    });
    document.documentElement.addEventListener('mouseenter', () => {
        cursor.style.opacity = '1';
    });
}

document.addEventListener('DOMContentLoaded', () => {
    initMobileMenu();
    initServicesDropdown();
    initPipeFlowAnimation();
    initScrollReveal();
    initReviewsCarousel();
    initCustomCursor();
});
