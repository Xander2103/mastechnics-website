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

document.addEventListener('DOMContentLoaded', initMobileMenu);