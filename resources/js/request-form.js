function initRequestForm() {
    const requestPage = document.querySelector('.request-form-card');

    if (!requestPage) {
        return;
    }

    initSelectableCards();
    initManualStepTracking();
    initStepNavigation();
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

            if (groupName === 'service') {
                setActiveStep(0);
            }

            if (groupName === 'request_type') {
                setActiveStep(1);
            }
        });
    });
}

function initManualStepTracking() {
    const technicalFields = document.querySelectorAll(
        'input[type="text"], .checkbox-field input'
    );

    technicalFields.forEach((field) => {
        field.addEventListener('focus', () => {
            setActiveStep(2);
        });

        field.addEventListener('click', () => {
            setActiveStep(2);
        });
    });

    const descriptionFields = document.querySelectorAll(
        'textarea, .upload-box'
    );

    descriptionFields.forEach((field) => {
        field.addEventListener('focus', () => {
            setActiveStep(3);
        });

        field.addEventListener('click', () => {
            setActiveStep(3);
        });
    });

    const summaryBoxes = document.querySelectorAll(
        '.estimate-box, .summary-box'
    );

    summaryBoxes.forEach((box) => {
        box.addEventListener('click', () => {
            setActiveStep(4);
        });
    });
}

function initStepNavigation() {
    const steps = Array.from(document.querySelectorAll('.request-step'));
    const sections = Array.from(document.querySelectorAll('.form-section'));
    const summaryBox = document.querySelector('.summary-box');

    if (summaryBox) {
        sections.push(summaryBox);
    }

    steps.forEach((step, index) => {
        step.addEventListener('click', () => {
            setActiveStep(index);

            const targetSection = sections[index];

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

document.addEventListener('DOMContentLoaded', initRequestForm);