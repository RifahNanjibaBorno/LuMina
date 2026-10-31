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
        if (feedback) feedback.textContent = Array.from(input.files, (file) => file.name).join(', ');
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

document.querySelectorAll('[data-assignment-role]').forEach((select) => {
    select.addEventListener('change', () => {
        const role = select.value;
        const dialog = document.getElementById(`assign-${role}`);
        if (!dialog) return;
        const selected = new Set(Array.from(document.querySelectorAll(`[data-assignment-values="${role}"] input`), (input) => input.value));
        dialog.querySelectorAll('[data-assignment-member]').forEach((input) => { input.checked = selected.has(input.value); });
        dialog.showModal();
        select.value = '';
    });
});
document.querySelectorAll('[data-assignment-apply]').forEach((button) => {
    button.addEventListener('click', () => {
        const dialog = button.closest('[data-assignment-dialog]');
        const role = dialog.dataset.assignmentDialog;
        const container = document.querySelector(`[data-assignment-values="${role}"]`);
        const selected = Array.from(dialog.querySelectorAll('[data-assignment-member]:checked'));
        container.replaceChildren(...selected.map((checkbox) => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = `${role}[]`;
            input.value = checkbox.value;
            return input;
        }));
        document.querySelector(`[data-assignment-summary="${role}"]`).textContent = `${selected.length} ${role} selected`;
        dialog.close();
    });
});

document.querySelectorAll('[data-management-role]').forEach((select) => {
    select.addEventListener('change', () => {
        document.getElementById(`manage-${select.value}`)?.showModal();
        select.value = '';
    });
});
document.querySelectorAll('[data-management-course]').forEach((select) => {
    select.addEventListener('change', () => {
        const members = JSON.parse(select.selectedOptions[0]?.dataset.members ?? '[]').map(String);
        select.closest('dialog').querySelectorAll('[data-management-member]').forEach((input) => {
            input.checked = members.includes(input.value);
            input.disabled = !select.value || (select.dataset.addOnly === 'true' && input.checked);
        });
    });
});

document.querySelectorAll('[data-course-assignment-role]').forEach((select) => {
    select.addEventListener('change', () => {
        select.closest('dialog').querySelectorAll('[data-course-member-group]').forEach((group) => {
            const active = group.dataset.courseMemberGroup === select.value;
            group.hidden = !active;
            group.querySelectorAll('input').forEach((input) => { input.disabled = !active; });
        });
    });
});

window.showToast = (message, type = 'success') => {
    if (typeof window.Toastify !== 'function') return false;
    const colors = { success: '#187454', error: '#b42332', warning: '#895b08', info: '#1b3e68' };
    window.Toastify({
        text: String(message),
        duration: type === 'error' ? 8000 : 5000,
        close: true,
        gravity: 'top',
        position: 'right',
        stopOnFocus: true,
        escapeMarkup: true,
        style: { background: colors[type] ?? colors.info },
        ariaLive: type === 'error' ? 'assertive' : 'polite',
    }).showToast();
    return true;
};
document.querySelectorAll('[data-toast-type]').forEach((notice) => {
    if (window.showToast(notice.textContent.replace(/\s+/g, ' ').trim(), notice.dataset.toastType)) notice.hidden = true;
});
if (typeof window.DataTable === 'function') {
    document.querySelectorAll('table[data-datatable]').forEach((table) => {
        if (!window.DataTable.isDataTable(table)) {
            new window.DataTable(table, {
                pageLength: 10,
                lengthMenu: [10, 25, 50, 100],
                order: [[0, 'asc']],
                language: { emptyTable: 'No entries available.', zeroRecords: 'No matching entries found.' },
            });
        }
    });
}

window.initializeSelect2 = (root = document) => {
    if (!window.jQuery?.fn.select2) return;
    root.querySelectorAll('select[data-enhanced-select], select.select2').forEach((select) => {
        const element = window.jQuery(select);
        if (element.hasClass('select2-hidden-accessible')) return;
        const dialog = select.closest('dialog');
        element.select2({ width: '100%', ...(dialog ? { dropdownParent: window.jQuery(dialog) } : {}) });
    });
};
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => window.initializeSelect2());
} else {
    window.initializeSelect2();
}
