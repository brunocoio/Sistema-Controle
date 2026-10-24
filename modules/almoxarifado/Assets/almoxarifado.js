(() => {
    'use strict';

    document.querySelectorAll('[data-checklist-builder]').forEach((builder) => {
        const rows = builder.querySelector('[data-checklist-rows]');
        const template = builder.querySelector('[data-checklist-template]');
        const status = builder.querySelector('[data-checklist-status]');

        if (!(rows instanceof HTMLElement) || !(template instanceof HTMLTemplateElement)) return;

        const refresh = () => {
            const currentRows = [...rows.querySelectorAll('[data-checklist-row]')];
            const selectedItems = new Set(currentRows
                .map((row) => row.querySelector('select')?.value)
                .filter((value) => value));
            currentRows.forEach((row) => {
                const remove = row.querySelector('[data-remove-checklist-item]');
                const select = row.querySelector('select');
                if (remove instanceof HTMLButtonElement) remove.disabled = currentRows.length === 1;
                if (select instanceof HTMLSelectElement) {
                    [...select.options].forEach((option) => {
                        option.disabled = option.value !== ''
                            && selectedItems.has(option.value)
                            && option.value !== select.value;
                    });
                }
            });
            if (status instanceof HTMLElement) status.textContent = `${currentRows.length} ${currentRows.length === 1 ? 'item' : 'itens'} na lista.`;
        };

        builder.addEventListener('click', (event) => {
            const target = event.target;
            if (!(target instanceof Element)) return;

            const addButton = target.closest('[data-add-checklist-item]');
            if (addButton) {
                const fragment = template.content.cloneNode(true);
                const newRow = fragment.querySelector('[data-checklist-row]');
                if (!(newRow instanceof HTMLElement)) return;
                rows.append(fragment);
                refresh();
                newRow.querySelector('select')?.focus();
                return;
            }

            const removeButton = target.closest('[data-remove-checklist-item]');
            const row = removeButton?.closest('[data-checklist-row]');
            if (!row || rows.querySelectorAll('[data-checklist-row]').length <= 1) return;
            const nextSelect = row.nextElementSibling?.querySelector('select')
                || row.previousElementSibling?.querySelector('select');
            row.remove();
            refresh();
            nextSelect?.focus();
        });

        refresh();
    });
})();
