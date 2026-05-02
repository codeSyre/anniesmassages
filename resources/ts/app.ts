const sidebarToggle = document.querySelector<HTMLButtonElement>('[data-sidebar-toggle]');

if (sidebarToggle !== null) {
  sidebarToggle.addEventListener('click', () => {
    document.body.classList.toggle('sidebar-open');
  });
}
