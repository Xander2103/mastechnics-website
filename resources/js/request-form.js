function initRequestForm() {
    const requestPage = document.querySelector('.request-form-card');

    if (!requestPage) {
        return;
    }

    initSelectableCards();
    initStepNavigation();
    initManualStepTracking();
    initAttachmentPreview();
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

function initAttachmentPreview() {
    const input = document.getElementById('attachmentsInput');
    const list = document.getElementById('selectedAttachments');

    if (!input || !list) {
        return;
    }

    let selectedFiles = [];

    input.addEventListener('change', () => {
        selectedFiles = [...selectedFiles, ...Array.from(input.files)];
        syncFileInput(input, selectedFiles);
        renderSelectedFiles(input, list, selectedFiles, (updatedFiles) => {
            selectedFiles = updatedFiles;
        });
    });
}

function renderSelectedFiles(input, list, selectedFiles, onUpdate) {
    list.innerHTML = '';

    if (selectedFiles.length === 0) {
        return;
    }

    selectedFiles.forEach((file, index) => {
        const item = document.createElement('div');
        item.className = 'selected-attachment-item';

        const fileName = document.createElement('span');
        fileName.textContent = file.name;

        const removeButton = document.createElement('button');
        removeButton.type = 'button';
        removeButton.textContent = '×';
        removeButton.setAttribute('aria-label', `Verwijder ${file.name}`);

        removeButton.addEventListener('click', () => {
            const updatedFiles = selectedFiles.filter((_, fileIndex) => {
                return fileIndex !== index;
            });

            syncFileInput(input, updatedFiles);
            onUpdate(updatedFiles);
            renderSelectedFiles(input, list, updatedFiles, onUpdate);
        });

        item.appendChild(fileName);
        item.appendChild(removeButton);

        list.appendChild(item);
    });
}

function syncFileInput(input, files) {
    const dataTransfer = new DataTransfer();

    files.forEach((file) => {
        dataTransfer.items.add(file);
    });

    input.files = dataTransfer.files;
}

document.addEventListener('DOMContentLoaded', initRequestForm);