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

function initHeroParallax() {
    const hero = document.querySelector('.home-hero');
    if (!hero) return;

    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
    if ('ontouchstart' in window) return;

    const layers = hero.querySelectorAll('[data-parallax]');
    if (!layers.length) return;

    hero.addEventListener('mouseenter', () => {
        layers.forEach(layer => { layer.style.transition = ''; });
    });

    hero.addEventListener('mousemove', (e) => {
        const rect = hero.getBoundingClientRect();
        const dx = (e.clientX - rect.left - rect.width  / 2) / rect.width;
        const dy = (e.clientY - rect.top  - rect.height / 2) / rect.height;

        layers.forEach(layer => {
            const speed = parseFloat(layer.dataset.parallax) || 0;
            layer.style.transform =
                `translate(${dx * speed * 18}px, ${dy * speed * 12}px)`;
        });
    });

    hero.addEventListener('mouseleave', () => {
        layers.forEach(layer => {
            layer.style.transition = 'transform 0.7s cubic-bezier(0.22, 1, 0.36, 1)';
            layer.style.transform = '';
        });
    });
}

document.addEventListener('DOMContentLoaded', () => {
    initMobileMenu();
    initPipeFlowAnimation();
    initHeroParallax();
});
