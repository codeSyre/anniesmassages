"use strict";
const sidebarToggle = document.querySelector('[data-sidebar-toggle]');
if (sidebarToggle !== null) {
    sidebarToggle.addEventListener('click', () => {
        document.body.classList.toggle('sidebar-open');
    });
}
const userMenuTrigger = document.querySelector('[data-user-menu-trigger]');
if (userMenuTrigger !== null) {
    userMenuTrigger.addEventListener('click', (e) => {
        e.stopPropagation();
        const expanded = userMenuTrigger.getAttribute('aria-expanded') === 'true';
        userMenuTrigger.setAttribute('aria-expanded', expanded ? 'false' : 'true');
    });
    userMenuTrigger.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            userMenuTrigger.click();
        }
        if (e.key === 'Escape') {
            userMenuTrigger.setAttribute('aria-expanded', 'false');
        }
    });
    document.addEventListener('click', () => {
        userMenuTrigger.setAttribute('aria-expanded', 'false');
    });
}
//# sourceMappingURL=app.js.map