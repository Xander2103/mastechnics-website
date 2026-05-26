function initRequestForm() {
    const requestPage = document.querySelector('.request-form-card');

    if (!requestPage) {
        return;
    }

    initSelectableCards();
    initStepNavigation();
    initManualStepTracking();
}

function setActiveStep(stepIndex) {
    const steps = document.querySelectorAll('.request-step');

    steps.forEach((step) => {
        step.classList.remove('is-active');
    });

    if (steps[stepIndex]) {
        steps[stepIndex].classList.add('is-active');
    }
}

function initSelectableCards() {
    const optionCards = document.querySelectorAll('.option-card');

    optionCards.forEach((card) => {
        const input = card.querySelector('input[type="radio"]');

        if (!input) {
            return;
        }

        card.addEventListener('click', () => {
            const groupName = input.name;

            document
                .querySelectorAll(`input[name="${groupName}"]`)
                .forEach((groupInput) => {
                    const groupCard = groupInput.closest('.option-card');

                    if (groupCard) {
                        groupCard.classList.remove('is-selected');
                    }
                });

            input.checked = true;
            card.classList.add('is-selected');

            const section = card.closest('[data-step]');

            if (section) {
                setActiveStep(Number(section.dataset.step));
            }
        });
    });
}

function initStepNavigation() {
    const steps = Array.from(document.querySelectorAll('.request-step'));
    const sections = Array.from(document.querySelectorAll('[data-step]'));

    steps.forEach((step, index) => {
        step.addEventListener('click', () => {
            const targetSection = sections.find((section) => {
                return Number(section.dataset.step) === index;
            });

            setActiveStep(index);

            if (!targetSection) {
                return;
            }

            targetSection.scrollIntoView({
                behavior: 'smooth',
                block: 'start',
            });
        });
    });
}

function initManualStepTracking() {
    const sections = document.querySelectorAll('[data-step]');

    sections.forEach((section) => {
        const stepIndex = Number(section.dataset.step);

        section.addEventListener('click', () => {
            setActiveStep(stepIndex);
        });

        section.querySelectorAll('input, textarea').forEach((field) => {
            field.addEventListener('focus', () => {
                setActiveStep(stepIndex);
            });
        });
    });
}

document.addEventListener('DOMContentLoaded', initRequestForm);