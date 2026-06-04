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

    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        list.classList.add('is-in-view');
        return;
    }

    if (!('IntersectionObserver' in window)) {
        list.classList.add('is-in-view');
        return;
    }

    // rootMargin starts animation 80px before the list enters the viewport,
    // preventing the invisible initial state from being visible to the user.
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

document.addEventListener('DOMContentLoaded', () => {
    initMobileMenu();
    initPipeFlowAnimation();
});
