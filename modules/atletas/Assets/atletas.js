(() => {
    'use strict';

    const configEditors = document.querySelectorAll('[data-config-editor]');
    const statusMessage = document.querySelector('[data-category-status]');
    if (statusMessage && statusMessage.textContent.trim() !== '') statusMessage.hidden = false;
    const showCategoryMessage = (message, isError = false) => {
        if (!statusMessage) return;
        statusMessage.textContent = message;
        statusMessage.classList.toggle('is-error', isError);
        statusMessage.hidden = false;
    };
    const deleteDialog = document.querySelector('[data-delete-dialog]');
    let pendingDeleteForm = null;
    const closeDeleteDialog = () => {
        if (deleteDialog instanceof HTMLDialogElement && deleteDialog.open) deleteDialog.close();
        pendingDeleteForm = null;
    };
    if (deleteDialog instanceof HTMLDialogElement) {
        deleteDialog.querySelector('[data-delete-cancel]')?.addEventListener('click', closeDeleteDialog);
        deleteDialog.querySelector('[data-delete-confirm]')?.addEventListener('click', async () => {
            const form = pendingDeleteForm;
            if (!(form instanceof HTMLFormElement)) return closeDeleteDialog();
            const button = form.querySelector('button[type="submit"]');
            button.disabled = true;
            try {
                const response = await fetch(form.getAttribute('action') || window.location.href, { method: 'POST', body: new FormData(form), credentials: 'same-origin', headers: { Accept: 'application/json' } });
                const text = await response.text();
                let result;
                try { result = JSON.parse(text); } catch { throw new Error('O servidor não retornou uma resposta válida. Atualize a página e tente novamente.'); }
                if (!response.ok || !result.ok) throw new Error(result.message || 'Não foi possível excluir o registro.');
                document.querySelector(form.dataset.removeSelector)?.remove();
                closeDeleteDialog();
                showCategoryMessage(result.message || 'Registro excluído.');
            } catch (error) {
                closeDeleteDialog();
                showCategoryMessage(error instanceof Error ? error.message : 'Não foi possível excluir o registro.', true);
                button.disabled = false;
            }
        });
        deleteDialog.addEventListener('click', (event) => {
            if (event.target === deleteDialog) closeDeleteDialog();
        });
    }
    const enterEditMode = (type, data) => {
        const form = document.querySelector(`[data-config-editor="${type}"]`);
        if (!(form instanceof HTMLFormElement)) return;
        form.elements.namedItem('id').value = data.id;
        if (type === 'category') {
            form.elements.namedItem('modalidade').value = data.name;
            form.elements.namedItem('genero').value = data.gender;
        } else if (type === 'position') {
            form.elements.namedItem('categoria_id').value = data.category;
            form.elements.namedItem('side').value = data.side;
            form.elements.namedItem('nome_posicao').value = data.name;
        } else {
            form.elements.namedItem('nome_cargo').value = data.name;
        }
        const action = form.elements.namedItem('action');
        action.value = `update-${type}`;
        const title = form.querySelector('[data-editor-title]');
        const submit = form.querySelector('[data-editor-submit]');
        if (title) title.textContent = type === 'category' ? 'Editar categoria' : (type === 'position' ? 'Editar posição' : 'Editar cargo');
        if (submit) submit.textContent = 'Salvar alterações';
        form.querySelector('[data-editor-cancel]').hidden = false;
        form.scrollIntoView({ behavior: 'smooth', block: 'start' });
        form.elements.namedItem(type === 'category' ? 'modalidade' : (type === 'position' ? 'nome_posicao' : 'nome_cargo')).focus({ preventScroll: true });
    };

    configEditors.forEach((form) => {
        const type = form.dataset.configEditor;
        form.querySelector('[data-editor-cancel]')?.addEventListener('click', () => {
            form.reset();
            form.elements.namedItem('id').value = '';
            form.elements.namedItem('action').value = type === 'category' ? 'create-category' : (type === 'position' ? 'add-position' : 'add-role');
            form.querySelector('[data-editor-title]').textContent = type === 'category' ? 'Nova categoria' : (type === 'position' ? 'Nova posição' : 'Novo cargo');
            form.querySelector('[data-editor-submit]').textContent = type === 'category' ? 'Criar categoria' : (type === 'position' ? 'Adicionar posição' : 'Cadastrar cargo');
            form.querySelector('[data-editor-cancel]').hidden = true;
        });
        form.addEventListener('submit', async (event) => {
            if (!form.elements.namedItem('action').value.startsWith('update-')) return;
            event.preventDefault();
            try {
                const response = await fetch(form.getAttribute('action') || window.location.href, { method: 'POST', body: new FormData(form), credentials: 'same-origin', headers: { Accept: 'application/json' } });
                const text = await response.text();
                let result;
                try { result = JSON.parse(text); } catch { throw new Error('O servidor não retornou uma resposta válida. Atualize a página e tente novamente.'); }
                if (!response.ok || !result.ok) throw new Error(result.message || 'Não foi possível salvar as alterações.');
                window.location.reload();
            } catch (error) {
                showCategoryMessage(error instanceof Error ? error.message : 'Não foi possível salvar as alterações.', true);
            }
        });
    });

    document.querySelectorAll('[data-edit-category]').forEach((button) => button.addEventListener('click', () => enterEditMode('category', button.dataset)));
    document.querySelectorAll('[data-edit-position]').forEach((button) => button.addEventListener('click', () => enterEditMode('position', button.dataset)));
    document.querySelectorAll('[data-edit-role]').forEach((button) => button.addEventListener('click', () => enterEditMode('role', button.dataset)));

    document.querySelectorAll('[data-delete-config]').forEach((form) => form.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (!(deleteDialog instanceof HTMLDialogElement)) return showCategoryMessage('Não foi possível abrir a confirmação. Atualize a página.', true);
        pendingDeleteForm = form;
        const itemName = deleteDialog.querySelector('[data-delete-item-name]');
        if (itemName) itemName.textContent = form.dataset.itemName || 'este registro';
        deleteDialog.showModal();
    }));

    const searchForm = document.querySelector('[data-athlete-search]');
    if (searchForm instanceof HTMLFormElement) {
        const searchInput = searchForm.querySelector('input[name="q"]');
        let searchTimer;
        searchForm.addEventListener('submit', (event) => {
            event.preventDefault();
            const state = JSON.stringify(Object.fromEntries(new FormData(searchForm).entries()));
            const bytes = new TextEncoder().encode(state);
            const token = btoa(String.fromCharCode(...bytes))
                .replace(/\+/g, '-')
                .replace(/\//g, '_')
                .replace(/=+$/, '');
            window.location.assign(new URL(`atletas-filtros-${token}`, window.location.href));
        });
        const initialSearch = searchInput ? searchInput.value.trim() : '';
        const cleanFilterRoute = /\/admin\/atletas-filtros-[A-Za-z0-9_-]+$/.test(window.location.pathname);
        let searchHasStarted = Array.from(initialSearch).length >= 3
            || (initialSearch !== '' && (cleanFilterRoute || new URLSearchParams(window.location.search).has('q')));
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
