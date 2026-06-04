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

    if (!reducedMotion) {
        document.addEventListener('click', () => {
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
    initPipeFlowAnimation();
    initScrollReveal();
    initCustomCursor();
});
