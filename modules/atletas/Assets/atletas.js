(() => {
    'use strict';

    const searchForm = document.querySelector('[data-athlete-search]');
    if (searchForm instanceof HTMLFormElement) {
        const searchInput = searchForm.querySelector('input[name="q"]');
        let searchTimer;
        const initialSearch = searchInput ? searchInput.value.trim() : '';
        let searchHasStarted = Array.from(initialSearch).length >= 3
            || (initialSearch !== '' && new URLSearchParams(window.location.search).has('q'));
        searchInput?.addEventListener('input', () => {
            const searchLength = Array.from(searchInput.value.trim()).length;
            const shouldSearch = searchHasStarted || searchLength >= 3 || searchLength === 0;
            window.clearTimeout(searchTimer);
            if (searchLength >= 3) searchHasStarted = true;
            if (searchLength === 0) searchHasStarted = false;
            if (shouldSearch) {
                searchTimer = window.setTimeout(() => searchForm.requestSubmit(), 250);
            }
        });
        searchForm.querySelectorAll('select').forEach((select) => {
            select.addEventListener('change', () => searchForm.requestSubmit());
        });
    }

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
