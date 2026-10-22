(() => {
    'use strict';

    const showToast = (message, tone = 'success') => window.AppUI?.showToast(message, tone);

    document.querySelectorAll('[data-confirm-event-delete]').forEach((form) => {
        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            if (form.dataset.confirmed === undefined) return;
            const button = form.querySelector('button[type="submit"]');
            if (!(button instanceof HTMLButtonElement)) return;
            button.disabled = true;
            let responseStatus = null;
            try {
                const response = await fetch(form.getAttribute('action') || window.location.href, {
                    method: 'POST', body: new FormData(form), credentials: 'same-origin',
                    headers: { Accept: 'application/json' },
                });
                responseStatus = response.status;
                const result = await window.AppUI.readJsonResponse(response);
                if (!response.ok || !result.ok) throw new Error(result.message || 'Não foi possível excluir o evento.');
                const card = form.closest('.events-admin-card');
                card?.remove();
                showToast(result.message || 'Evento excluído.', 'danger');
                if (!document.querySelector('.events-admin-card')) {
                    const empty = document.createElement('section');
                    empty.className = 'events-admin-empty';
                    empty.innerHTML = '<span aria-hidden="true">◷</span><h2>A agenda está vazia</h2><p>Cadastre um jogo, treino ou compromisso para começar.</p>';
                    document.querySelector('.events-admin-list')?.replaceWith(empty);
                }
            } catch (error) {
                window.AppUI?.logRequestError('Excluir evento', error, responseStatus);
                showToast(error instanceof Error ? error.message : 'Não foi possível excluir o evento.', 'error');
                button.disabled = false;
            }
        });
    });

    const releaseToggle = document.querySelector('[data-release-toggle]');
    const releaseFields = document.querySelector('[data-release-fields]');
    if (releaseToggle instanceof HTMLInputElement && releaseFields instanceof HTMLElement) {
        const updateReleaseFields = () => {
            releaseFields.hidden = !releaseToggle.checked;
            releaseFields.querySelectorAll('input, textarea').forEach((field) => { field.disabled = !releaseToggle.checked; });
        };
        releaseToggle.addEventListener('change', updateReleaseFields);
        updateReleaseFields();
    }

    const typeField = document.querySelector('select[name="tipo"]');
    const resultFields = document.querySelector('.event-result-fields');
    if (typeField instanceof HTMLSelectElement && resultFields instanceof HTMLElement) {
        const updateResultFields = () => {
            const available = ['jogo', 'amistoso'].includes(typeField.value);
            resultFields.hidden = !available;
            resultFields.querySelectorAll('input, select').forEach((field) => { field.disabled = !available; });
            const opponent = document.querySelector('.event-opponent-field');
            if (opponent instanceof HTMLElement) opponent.hidden = !available;
            if (opponent instanceof HTMLElement) opponent.querySelectorAll('input').forEach((field) => { field.disabled = !available; });
        };
        typeField.addEventListener('change', updateResultFields);
        updateResultFields();
    }
})();
