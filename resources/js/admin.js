import './bootstrap';

const storageKey = 'admin-sidebar-collapsed';

const applySidebarState = (collapsed) => {
    document.documentElement.dataset.adminSidebarCollapsed = collapsed ? 'true' : 'false';

    document.querySelectorAll('[data-admin-sidebar-toggle]').forEach((button) => {
        const accessibilityLabel = collapsed ? 'Mostrar menú lateral' : 'Ocultar menú lateral';

        button.setAttribute('aria-pressed', collapsed ? 'true' : 'false');
        button.setAttribute('aria-label', accessibilityLabel);
        button.setAttribute('title', 'Menú lateral');
    });
};

const getStoredSidebarState = () => {
    try {
        return localStorage.getItem(storageKey) === 'true';
    } catch (error) {
        return false;
    }
};

const persistSidebarState = (collapsed) => {
    try {
        localStorage.setItem(storageKey, collapsed ? 'true' : 'false');
    } catch (error) {
        // Ignore storage write failures.
    }
};

const initializeBillingSettings = () => {
    const providerSelect = document.getElementById('provider');
    const modeSelect = document.getElementById('dispatch_mode');

    if (!providerSelect || !modeSelect) {
        return;
    }

    document.querySelectorAll('[data-provider-panel]').forEach((panel) => {
        panel.classList.toggle('is-active', panel.dataset.providerPanel === providerSelect.value);
    });

    const isQueue = modeSelect.value === 'queue';

    document.querySelectorAll('.queue-field input').forEach((input) => {
        input.disabled = !isQueue;
        input.closest('.queue-field')?.classList.toggle('opacity-50', !isQueue);
    });
};

const addTransportItem = () => {
    const container = document.getElementById('items');
    const template = container?.querySelector('.item-row');

    if (!container || !template) {
        return;
    }

    const row = template.cloneNode(true);
    const index = container.querySelectorAll('.item-row').length;

    row.querySelectorAll('input, select').forEach((field) => {
        field.name = field.name.replace(/items\[\d+\]/, `items[${index}]`);

        if (field.tagName === 'SELECT') {
            field.selectedIndex = 0;
        } else if (!field.name.endsWith('[unit_code]')) {
            field.value = '';
        }
    });

    container.appendChild(row);
};

const addAccountingLine = () => {
    const tableBody = document.querySelector('#entry-lines-table tbody');
    const template = tableBody?.querySelector('tr');

    if (!tableBody || !template) {
        return;
    }

    const row = template.cloneNode(true);
    const indexes = Array.from(tableBody.querySelectorAll('[name^="lines["]'))
        .map((field) => field.name.match(/^lines\[(\d+)]/)?.[1])
        .filter((index) => index !== undefined)
        .map(Number);
    const index = Math.max(-1, ...indexes) + 1;

    row.querySelectorAll('input, select').forEach((field) => {
        field.name = field.name.replace(/lines\[\d+\]/, `lines[${index}]`);

        if (field.tagName === 'SELECT') {
            field.selectedIndex = 0;
        } else if (field.name.endsWith('[debit]') || field.name.endsWith('[credit]')) {
            field.value = '0';
        } else {
            field.value = '';
        }
    });

    tableBody.appendChild(row);
};

const initializeAdminPage = () => {
    applySidebarState(getStoredSidebarState());
    initializeBillingSettings();
};

initializeAdminPage();

document.addEventListener('click', (event) => {
    const toggle = event.target.closest('[data-admin-sidebar-toggle]');

    if (toggle) {
        const collapsed = document.documentElement.dataset.adminSidebarCollapsed === 'true';
        const nextState = !collapsed;

        applySidebarState(nextState);
        persistSidebarState(nextState);

        return;
    }

    if (event.target.closest('[data-transport-item-add]')) {
        addTransportItem();

        return;
    }

    if (event.target.closest('[data-accounting-line-add]')) {
        addAccountingLine();

        return;
    }

    const removeAccountingLine = event.target.closest('[data-accounting-line-remove]');

    if (removeAccountingLine) {
        removeAccountingLine.closest('tr')?.remove();
    }
});

document.addEventListener('change', (event) => {
    if (event.target.matches('#provider, #dispatch_mode')) {
        initializeBillingSettings();
    }
});

document.addEventListener('livewire:navigated', () => {
    initializeAdminPage();
});
