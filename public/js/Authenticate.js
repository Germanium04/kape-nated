(() => {
    'use strict';

    /* ------------------------------------------------------------------
       1. Back/forward cache.
       Some browsers (esp. Safari/Firefox on mobile) restore a page from
       memory on Back without asking the server. If that happens, force a
       real reload so the server's auth check runs and a logged-out user
       lands on the login page instead of seeing the old dashboard.
       ------------------------------------------------------------------ */
    window.addEventListener('pageshow', (e) => {
        if (e.persisted) window.location.reload();
    });

    /* ------------------------------------------------------------------
       2. Toasts: close button + auto-dismiss.
       ------------------------------------------------------------------ */
    const dismiss = (toast) => {
        toast.classList.add('is-leaving');
        setTimeout(() => toast.remove(), 200);
    };

    document.querySelectorAll('.ui-toast').forEach((toast) => {
        toast.querySelector('.ui-toast__close')?.addEventListener('click', () => dismiss(toast));
        const ms = Number(toast.dataset.autoclose || 0);
        if (ms > 0) setTimeout(() => dismiss(toast), ms);
    });

    /* ------------------------------------------------------------------
       3. Confirmation dialog.
       window.uiConfirm({...}) returns a Promise<boolean>. Use it from your
       own AJAX code too:
           if (!await uiConfirm({ title: 'Delete item?', ok: 'Delete' })) return;
       ------------------------------------------------------------------ */
    const dlg = document.getElementById('ui-confirm');

    window.uiConfirm = ({
        title = 'Are you sure?',
        message = '',
        ok = 'Confirm',
        cancel = 'Cancel',
    } = {}) => {
        // Page without the partial: fall back to the browser's own dialog.
        if (!dlg || typeof dlg.showModal !== 'function') {
            return Promise.resolve(window.confirm(message || title));
        }

        // textContent (never innerHTML) so messages can't inject markup.
        dlg.querySelector('#ui-confirm-title').textContent = title;
        dlg.querySelector('#ui-confirm-msg').textContent = message;
        dlg.querySelector('#ui-confirm-ok').textContent = ok;
        dlg.querySelector('#ui-confirm-cancel').textContent = cancel;
        dlg.querySelector('#ui-confirm-msg').hidden = !message;

        dlg.returnValue = '';
        dlg.showModal();
        dlg.querySelector('#ui-confirm-cancel').focus(); // safest default

        return new Promise((resolve) => {
            dlg.addEventListener('close', () => resolve(dlg.returnValue === 'ok'), { once: true });
        });
    };

    /* ------------------------------------------------------------------
       4. Forms.
       - data-confirm="..." on a <form> asks first (title/ok/cancel via
         data-confirm-title, data-confirm-ok, data-confirm-cancel).
       - Normal submits disable the button so a double-click can't send
         the request twice.
       ------------------------------------------------------------------ */
    const isDialogForm = (form) => form.getAttribute('method') === 'dialog';

    const lock = (form) => {
        form.querySelectorAll('button[type="submit"], button:not([type])').forEach((btn) => {
            btn.disabled = true;
            if (btn.dataset.loading !== undefined) btn.textContent = btn.dataset.loading;
        });
    };

    document.addEventListener('submit', async (e) => {
        const form = e.target;
        if (isDialogForm(form) || e.defaultPrevented) return;

        const message = form.dataset.confirm;

        if (message && !form.dataset.confirmed) {
            e.preventDefault();

            const yes = await window.uiConfirm({
                title: form.dataset.confirmTitle || 'Are you sure?',
                message,
                ok: form.dataset.confirmOk || 'Confirm',
                cancel: form.dataset.confirmCancel || 'Cancel',
            });

            if (!yes) return;

            form.dataset.confirmed = '1';
            lock(form);
            form.submit(); // native validation already ran before this event fired
            return;
        }

        lock(form);
    });
})();