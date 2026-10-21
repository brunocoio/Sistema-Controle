(() => {
    'use strict';

    const genderField = document.getElementById('athlete-gender');
    if (!genderField) return;

    const categorySections = document.querySelectorAll('[data-category-gender]');
    const updateCategories = () => {
        const selectedGender = genderField.value;
        categorySections.forEach((section) => {
            const compatible = section.dataset.categoryGender === selectedGender;
            const linkToggle = section.querySelector('.category-link-toggle');
            const linked = linkToggle instanceof HTMLInputElement && linkToggle.checked;
            section.querySelectorAll('.category-assignment-control').forEach((control) => {
                const playerOnly = control.classList.contains('athlete-player-control')
                    || control.dataset.playerRole === 'true';
                control.disabled = !linked || (playerOnly && !compatible);
            });
        });
    };

    categorySections.forEach((section) => {
        section.addEventListener('change', (event) => {
            const control = event.target;
            const linkToggle = section.querySelector('.category-link-toggle');
            if (!linkToggle || !(control instanceof HTMLElement)) return;

            if (control === linkToggle && !linkToggle.checked) {
                section.querySelectorAll('.category-assignment-control').forEach((assignment) => {
                    if (assignment instanceof HTMLInputElement && assignment.type === 'checkbox') {
                        assignment.checked = false;
                    } else if (assignment instanceof HTMLSelectElement) {
                        assignment.value = '';
                    }
                });
                updateCategories();
                return;
            }

            if (control.matches('.category-assignment-control')
                && ((control instanceof HTMLInputElement && control.checked)
                    || (control instanceof HTMLSelectElement && control.value !== ''))) {
                linkToggle.checked = true;
            }
            updateCategories();
        });
    });

    genderField.addEventListener('change', updateCategories);
    updateCategories();
})();