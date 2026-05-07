(() => {
    if (window.__clinicEnterNavigationInitialized) {
        return;
    }

    window.__clinicEnterNavigationInitialized = true;

    const ignoredInputTypes = new Set(['button', 'submit', 'reset', 'checkbox', 'radio', 'file', 'hidden', 'image']);
    const textSelectableTypes = new Set(['email', 'number', 'password', 'search', 'tel', 'text', 'url']);

    function isVisible(element) {
        return element instanceof HTMLElement && element.getClientRects().length > 0;
    }

    function isFocusableField(element) {
        if (!(element instanceof HTMLElement) || !isVisible(element) || element.closest('[data-enter-navigation="off"]')) {
            return false;
        }

        if (element.hasAttribute('disabled') || element.getAttribute('aria-hidden') === 'true') {
            return false;
        }

        if (element instanceof HTMLInputElement) {
            return !ignoredInputTypes.has((element.type || 'text').toLowerCase()) && !element.readOnly;
        }

        if (element instanceof HTMLSelectElement) {
            return true;
        }

        if (element instanceof HTMLTextAreaElement) {
            return !element.readOnly;
        }

        return element instanceof HTMLButtonElement;
    }

    function shouldMoveWithEnter(element) {
        if (!(element instanceof HTMLElement) || element.closest('[data-enter-navigation="off"]') || element.closest('[data-enter-submit="1"]')) {
            return false;
        }

        if (element.isContentEditable || element instanceof HTMLTextAreaElement || element instanceof HTMLButtonElement) {
            return false;
        }

        if (element instanceof HTMLInputElement) {
            return !ignoredInputTypes.has((element.type || 'text').toLowerCase()) && !element.readOnly;
        }

        return element instanceof HTMLSelectElement;
    }

    function focusField(element) {
        if (!(element instanceof HTMLElement)) {
            return;
        }

        element.focus({ preventScroll: false });

        if (element instanceof HTMLInputElement && textSelectableTypes.has((element.type || 'text').toLowerCase())) {
            element.select();
        }
    }

    function findPreferredField(scope = document) {
        if (!(scope instanceof Document || scope instanceof HTMLElement)) {
            return null;
        }

        const preferredFields = Array.from(scope.querySelectorAll('[data-page-autofocus], [autofocus]')).filter(isFocusableField);

        if (preferredFields[0]) {
            return preferredFields[0];
        }

        const firstField = Array.from(scope.querySelectorAll('input, select, textarea')).filter(isFocusableField)[0];

        if (firstField) {
            return firstField;
        }

        return Array.from(scope.querySelectorAll('button')).filter(isFocusableField)[0] || null;
    }

    function focusPreferredField(scope = document) {
        const field = findPreferredField(scope);

        if (!field) {
            return;
        }

        window.requestAnimationFrame(() => focusField(field));
    }

    document.addEventListener('keydown', (event) => {
        if (
            event.key !== 'Enter'
            || event.defaultPrevented
            || event.isComposing
            || event.shiftKey
            || event.ctrlKey
            || event.altKey
            || event.metaKey
        ) {
            return;
        }

        const target = event.target;

        if (!shouldMoveWithEnter(target)) {
            return;
        }

        const scope = target.form || target.closest('form') || target.closest('[data-enter-scope]') || document;
        const fields = Array.from(scope.querySelectorAll('input, select, textarea, button')).filter(isFocusableField);
        const currentIndex = fields.indexOf(target);

        if (currentIndex === -1) {
            return;
        }

        const nextField = fields.slice(currentIndex + 1).find((field) => field !== target);

        if (!nextField) {
            return;
        }

        event.preventDefault();
        window.requestAnimationFrame(() => focusField(nextField));
    });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => focusPreferredField(document), { once: true });
    } else {
        focusPreferredField(document);
    }

    document.addEventListener('shown.bs.modal', (event) => {
        if (event.target instanceof HTMLElement) {
            focusPreferredField(event.target);
        }
    });
})();
