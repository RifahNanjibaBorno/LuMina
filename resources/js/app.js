document.querySelectorAll('[data-dialog-open]').forEach((button) => {
    button.addEventListener('click', () => document.getElementById(button.dataset.dialogOpen)?.showModal());
});
document.querySelectorAll('[data-dialog-close]').forEach((button) => {
    button.addEventListener('click', () => button.closest('dialog')?.close());
});
document.querySelectorAll('dialog').forEach((dialog) => {
    dialog.addEventListener('click', (event) => {
        if (event.target === dialog) {
            const bounds = dialog.getBoundingClientRect();
            if (event.clientX < bounds.left || event.clientX > bounds.right || event.clientY < bounds.top || event.clientY > bounds.bottom) dialog.close();
        }
    });
});
document.querySelectorAll('[data-confirm]').forEach((form) => {
    form.addEventListener('submit', (event) => {
        if (!window.confirm(form.dataset.confirm)) event.preventDefault();
    });
});
document.querySelectorAll('[data-copy]').forEach((button) => {
    button.addEventListener('click', async () => {
        try {
            await navigator.clipboard.writeText(button.dataset.copy);
            const original = button.textContent;
            button.textContent = 'Link copied';
            setTimeout(() => { button.textContent = original; }, 2500);
        } catch {
            window.prompt('Copy your portfolio link:', button.dataset.copy);
        }
    });
});
document.querySelectorAll('[data-print]').forEach((button) => button.addEventListener('click', () => window.print()));
document.querySelectorAll('[data-filter]').forEach((select) => select.addEventListener('change', () => select.form.requestSubmit()));
document.querySelectorAll('[data-file-label]').forEach((input) => {
    input.addEventListener('change', () => {
        const label = input.closest('label');
        let feedback = label?.querySelector('[data-file-feedback]');
        if (!feedback && label) {
            feedback = document.createElement('small');
            feedback.dataset.fileFeedback = '';
            feedback.setAttribute('role', 'status');
            label.append(feedback);
        }
        if (feedback) feedback.textContent = input.files[0]?.name ?? '';
    });
});
document.querySelectorAll('.project-choice input').forEach((input) => {
    input.addEventListener('change', () => {
        const status = input.closest('.project-choice').querySelector('[data-choice-status]');
        status.textContent = input.checked ? 'Selected' : 'Hidden';
        status.classList.toggle('badge-green', input.checked);
        status.classList.toggle('badge-red', !input.checked);
    });
});
document.querySelectorAll('[data-section-link]').forEach((link) => {
    link.addEventListener('click', () => {
        document.querySelectorAll('[data-section-link]').forEach((other) => other.classList.remove('selected'));
        link.classList.add('selected');
    });
});
const menuButton = document.querySelector('[data-menu-toggle]');
const navigation = document.getElementById('workspace-navigation');
menuButton?.addEventListener('click', () => {
    const open = navigation.classList.toggle('open');
    menuButton.setAttribute('aria-expanded', String(open));
});
document.addEventListener('click', (event) => {
    if (navigation?.classList.contains('open') && !navigation.contains(event.target) && !menuButton.contains(event.target)) {
        navigation.classList.remove('open');
        menuButton.setAttribute('aria-expanded', 'false');
    }
});
document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && navigation?.classList.contains('open')) {
        navigation.classList.remove('open');
        menuButton.setAttribute('aria-expanded', 'false');
        menuButton.focus();
    }
});
document.querySelectorAll('form').forEach((form) => {
    form.addEventListener('submit', (event) => {
        if (event.defaultPrevented) return;
        const button = form.querySelector('[data-loading-text]');
        if (button) {
            button.textContent = button.dataset.loadingText;
            button.disabled = true;
        }
    });
});
