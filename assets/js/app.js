"use strict";
const sidebarToggle = document.querySelector('[data-sidebar-toggle]');
if (sidebarToggle !== null) {
    sidebarToggle.addEventListener('click', () => {
        document.body.classList.toggle('sidebar-open');
    });
}
//# sourceMappingURL=app.js.map