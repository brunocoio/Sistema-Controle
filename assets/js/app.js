(() => {
    'use strict';

    let toastTimer;
    let removeTimer;

    const showToast = (message, tone = 'success') => {
        let toast = document.querySelector('[data-app-toast]');
        if (!(toast instanceof HTMLElement)) {
            toast = document.createElement('div');
            toast.className = 'app-toast';
            toast.dataset.appToast = '';
            document.body.append(toast);
        }

        toast.classList.remove('is-success', 'is-warning', 'is-danger', 'is-error', 'is-closing');
        toast.classList.add(`is-${tone}`);
        toast.textContent = message;
        toast.setAttribute('role', tone === 'error' ? 'alert' : 'status');
        toast.setAttribute('aria-live', tone === 'error' ? 'assertive' : 'polite');
        toast.hidden = false;
        window.clearTimeout(toastTimer);
        window.clearTimeout(removeTimer);
        toastTimer = window.setTimeout(() => {
            toast.classList.add('is-closing');
            removeTimer = window.setTimeout(() => {
                toast.hidden = true;
                toast.classList.remove('is-closing');
            }, 220);
        }, 4500);
    };

    const readJsonResponse = async (response) => {
        const body = await response.text();
        try {
            return JSON.parse(body);
        } catch {
            console.log('[Sistema FLAG] Resposta inválida do servidor.', {
                status: response.status,
                path: new URL(response.url).pathname,
            });
            throw new Error('O servidor não retornou uma resposta válida. Atualize a página e tente novamente.');
        }
    };

    const logRequestError = (operation, error, status = null) => {
        console.log('[Sistema FLAG] Falha na solicitação.', {
            operation,
            status,
            message: error instanceof Error ? error.message : 'Falha inesperada',
        });
    };

    window.AppUI = Object.freeze({ showToast, readJsonResponse, logRequestError });

    const userPasswordForm = document.querySelector('[data-user-password-form]');
    if (userPasswordForm instanceof HTMLFormElement) {
        const cpfInput = userPasswordForm.querySelector('[data-user-cpf]');
        const passwordInput = userPasswordForm.querySelector('[data-user-password]');
        const resetPassword = userPasswordForm.querySelector('[data-reset-password-to-cpf]');
        const isEdit = userPasswordForm.dataset.isEdit === '1';
        let passwordChanged = isEdit;
        if (cpfInput instanceof HTMLInputElement && passwordInput instanceof HTMLInputElement) {
            cpfInput.addEventListener('input', () => {
                if (!passwordChanged && !isEdit) passwordInput.value = cpfInput.value.replace(/\D/g, '');
            });
            passwordInput.addEventListener('input', () => {
                passwordChanged = passwordInput.value !== '';
                if (resetPassword instanceof HTMLInputElement && passwordChanged) resetPassword.checked = false;
            });
            if (resetPassword instanceof HTMLInputElement) {
                resetPassword.addEventListener('change', () => {
                    if (resetPassword.checked) {
                        passwordInput.value = cpfInput.value.replace(/\D/g, '');
                        passwordChanged = true;
                    } else {
                        passwordInput.value = '';
                        passwordChanged = false;
                    }
                });
            }
        }
    }

    const sidebar = document.querySelector('[data-admin-sidebar]');
    const sidebarToggles = [...document.querySelectorAll('[data-admin-sidebar-toggle]')];
    const sidebarBackdrop = document.querySelector('[data-admin-sidebar-backdrop]');
    if (sidebar instanceof HTMLElement && sidebarToggles.length > 0) {
        const mobileLayout = window.matchMedia('(max-width: 760px)');
        const collapsed = () => document.body.classList.contains('admin-sidebar-collapsed');
        const mobileOpen = () => document.body.classList.contains('admin-sidebar-mobile-open');
        const syncToggle = () => {
            sidebarToggles.forEach((toggle) => toggle.setAttribute('aria-expanded', String(mobileLayout.matches ? mobileOpen() : !collapsed())));
            sidebar.dataset.collapsed = String(collapsed());
            sidebar.toggleAttribute('inert', mobileLayout.matches && !mobileOpen());
        };
        const closeMobileSidebar = () => {
            document.body.classList.remove('admin-sidebar-mobile-open');
            syncToggle();
        };
        sidebarToggles.forEach((sidebarToggle) => sidebarToggle.addEventListener('click', async () => {
            if (mobileLayout.matches) {
                document.body.classList.toggle('admin-sidebar-mobile-open');
                syncToggle();
                return;
            }
            const previous = collapsed();
            const next = !previous;
            document.body.classList.toggle('admin-sidebar-collapsed', next);
            syncToggle();
            const body = new URLSearchParams({ _csrf: sidebarToggle.dataset.csrf || '', sidebar_collapsed: next ? '1' : '0' });
            let responseStatus = null;
            try {
                const response = await fetch('ui-preferences', { method: 'POST', body, credentials: 'same-origin', headers: { Accept: 'application/json' } });
                responseStatus = response.status;
                const result = await readJsonResponse(response);
                if (!response.ok || !result.ok) throw new Error(result.message || 'Não foi possível salvar a preferência.');
            } catch (error) {
                logRequestError('Salvar preferência do menu', error, responseStatus);
                document.body.classList.toggle('admin-sidebar-collapsed', previous);
                syncToggle();
                showToast(error instanceof Error ? error.message : 'Não foi possível salvar a preferência.', 'error');
            }
        }));
        sidebarBackdrop?.addEventListener('click', closeMobileSidebar);
        document.addEventListener('keydown', (event) => { if (event.key === 'Escape' && mobileOpen()) closeMobileSidebar(); });
        mobileLayout.addEventListener('change', () => { document.body.classList.remove('admin-sidebar-mobile-open'); syncToggle(); });
        syncToggle();
    }

    document.querySelectorAll('[data-app-toast]').forEach((toast) => {
        const message = toast.textContent.trim();
        const tone = [...toast.classList].find((name) => name.startsWith('is-'))?.slice(3) || 'success';
        if (message) showToast(message, tone);
    });

    const authForm = document.querySelector('[data-auth-form]');
    if (authForm instanceof HTMLFormElement) {
        const emailStep = authForm.querySelector('[data-step="email"]');
        const passwordStep = authForm.querySelector('[data-step="password"]');
        const emailInput = authForm.querySelector('#email');
        const passwordInput = authForm.querySelector('#password');
        const nextButton = authForm.querySelector('[data-auth-next]');
        const backLink = document.querySelector('[data-auth-back-link]');
        const feedback = authForm.querySelector('[data-auth-feedback]');
        const userName = authForm.querySelector('[data-user-name]');
        const userEmail = authForm.querySelector('[data-user-email]');
        const userAvatar = authForm.querySelector('[data-user-avatar]');
        const defaultAvatarHtml = userAvatar ? userAvatar.innerHTML : '';
        let isInPasswordStep = false;

        const showFeedback = (msg) => {
            if (feedback) feedback.textContent = msg;
            if (msg) showToast(msg, 'error');
        };

        const clearFeedback = () => {
            if (feedback) feedback.textContent = '';
        };

        const goToEmailStep = () => {
            isInPasswordStep = false;
            clearFeedback();
            if (emailStep instanceof HTMLElement && passwordStep instanceof HTMLElement) {
                passwordStep.hidden = true;
                passwordStep.classList.remove('is-entering');
                emailStep.hidden = false;
                emailStep.classList.add('is-entering');
                if (emailInput instanceof HTMLInputElement) {
                    emailInput.focus();
                }
            }
        };

        const handleCheckUser = async () => {
            if (!(emailInput instanceof HTMLInputElement)) return;
            const email = emailInput.value.trim();
            if (!email || !emailInput.checkValidity()) {
                emailInput.reportValidity();
                return;
            }

            clearFeedback();
            if (nextButton instanceof HTMLButtonElement) nextButton.disabled = true;

            try {
                const csrfInput = authForm.querySelector('input[name="_csrf"]');
                const csrfToken = csrfInput instanceof HTMLInputElement ? csrfInput.value : '';
                const body = new URLSearchParams({
                    action: 'check-user',
                    _csrf: csrfToken,
                    email: email,
                });

                const response = await fetch(authForm.getAttribute('action') || 'login', {
                    method: 'POST',
                    headers: { Accept: 'application/json' },
                    body: body,
                    credentials: 'same-origin',
                });

                const data = await readJsonResponse(response);

                if (!response.ok || !data.ok) {
                    throw new Error(data.message || 'Usuário não encontrado.');
                }

                if (userName) userName.textContent = data.name || email;
                if (userEmail) userEmail.textContent = data.email || email;

                if (userAvatar) {
                    if (data.has_photo && data.photo) {
                        userAvatar.innerHTML = `<img src="${data.photo}" alt="Foto de ${data.name || 'usuário'}" width="48" height="48">`;
                    } else {
                        userAvatar.innerHTML = defaultAvatarHtml;
                    }
                }

                if (emailStep instanceof HTMLElement && passwordStep instanceof HTMLElement) {
                    isInPasswordStep = true;
                    emailStep.hidden = true;
                    emailStep.classList.remove('is-entering');
                    passwordStep.hidden = false;
                    passwordStep.classList.add('is-entering');
                    if (passwordInput instanceof HTMLInputElement) {
                        passwordInput.value = '';
                        passwordInput.focus();
                    }
                }
            } catch (err) {
                const message = err instanceof Error ? err.message : 'Não foi possível identificar o usuário.';
                showFeedback(message);
                if (emailInput instanceof HTMLInputElement) {
                    emailInput.focus();
                    emailInput.select();
                }
            } finally {
                if (nextButton instanceof HTMLButtonElement) nextButton.disabled = false;
            }
        };

        nextButton?.addEventListener('click', (e) => {
            e.preventDefault();
            handleCheckUser();
        });

        emailInput?.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                handleCheckUser();
            }
        });

        backLink?.addEventListener('click', (e) => {
            e.preventDefault();
            goToEmailStep();
        });
    }

    const confirmDialog = document.querySelector('[data-app-confirm-dialog]');
    let pendingConfirmation = null;
    if (confirmDialog instanceof HTMLDialogElement) {
        document.addEventListener('submit', (event) => {
            const form = event.target;
            if (!(form instanceof HTMLFormElement) || !form.matches('[data-app-confirm]')) return;
            if (form.dataset.confirmed === '1') { delete form.dataset.confirmed; return; }
            event.preventDefault();
            pendingConfirmation = form;
            const message = confirmDialog.querySelector('[data-app-confirm-message]');
            if (message) message.textContent = form.dataset.confirmMessage || 'Confirma esta ação?';
            confirmDialog.showModal();
        });
        const closeConfirmation = () => { if (confirmDialog.open) confirmDialog.close(); pendingConfirmation = null; };
        confirmDialog.querySelector('[data-app-confirm-cancel]')?.addEventListener('click', closeConfirmation);
        confirmDialog.querySelector('[data-app-confirm-accept]')?.addEventListener('click', () => {
            const form = pendingConfirmation;
            closeConfirmation();
            if (!(form instanceof HTMLFormElement)) return;
            form.dataset.confirmed = '1';
            form.requestSubmit();
        });
        confirmDialog.addEventListener('click', (event) => { if (event.target === confirmDialog) closeConfirmation(); });
    }
})();
