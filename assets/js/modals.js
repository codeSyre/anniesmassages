"use strict";
const confirmDialog = document.querySelector('[data-confirm-dialog]');
if (confirmDialog !== null) {
    const titleElement = confirmDialog.querySelector('[data-confirm-dialog-title]');
    const messageElement = confirmDialog.querySelector('[data-confirm-dialog-message]');
    const cancelButton = confirmDialog.querySelector('[data-confirm-dialog-cancel]');
    const submitButton = confirmDialog.querySelector('[data-confirm-dialog-submit]');
    let pendingForm = null;
    const resetDialog = () => {
        pendingForm = null;
    };
    document.querySelectorAll('[data-confirm-dialog-form]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            event.preventDefault();
            if (typeof confirmDialog.showModal !== 'function') {
                const fallbackMessage = form.dataset.confirmMessage ?? 'This action cannot be undone.';
                if (window.confirm(fallbackMessage)) {
                    form.submit();
                }
                return;
            }
            pendingForm = form;
            if (titleElement !== null) {
                titleElement.textContent = form.dataset.confirmTitle ?? 'Confirm action';
            }
            if (messageElement !== null) {
                messageElement.textContent = form.dataset.confirmMessage ?? 'This action cannot be undone.';
            }
            if (submitButton !== null) {
                submitButton.textContent = form.dataset.confirmSubmitLabel ?? 'Continue';
            }
            confirmDialog.showModal();
        });
    });
    cancelButton?.addEventListener('click', () => {
        confirmDialog.close();
    });
    submitButton?.addEventListener('click', () => {
        const form = pendingForm;
        confirmDialog.close();
        if (form !== null) {
            form.submit();
        }
    });
    confirmDialog.addEventListener('close', resetDialog);
    confirmDialog.addEventListener('cancel', resetDialog);
}
//# sourceMappingURL=modals.js.map