import './bootstrap';
import $ from 'jquery';
import 'bootstrap/dist/js/bootstrap.bundle';

window.$ = window.jQuery = $;

const storageKey = 'admin-sidebar-collapsed';

const applySidebarState = (collapsed) => {
    document.documentElement.dataset.adminSidebarCollapsed = collapsed ? 'true' : 'false';

    document.querySelectorAll('[data-admin-sidebar-toggle]').forEach((button) => {
        const label = button.querySelector('[data-admin-sidebar-toggle-label]');
        const text = collapsed ? '' : '';

        button.setAttribute('aria-pressed', collapsed ? 'true' : 'false');
        button.setAttribute('aria-label', text + ' lateral');
        button.setAttribute('title', text + ' lateral');

        if (label) {
            label.textContent = text;
        }
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

applySidebarState(getStoredSidebarState());

document.addEventListener('click', (event) => {
    const toggle = event.target.closest('[data-admin-sidebar-toggle]');

    if (!toggle) {
        return;
    }

    const collapsed = document.documentElement.dataset.adminSidebarCollapsed === 'true';
    const nextState = !collapsed;

    applySidebarState(nextState);
    persistSidebarState(nextState);
});

document.addEventListener('livewire:navigated', () => {
    applySidebarState(getStoredSidebarState());
});
