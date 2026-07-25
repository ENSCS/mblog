// Desktop dropdown (.menu-item-has-children/.submenu) opens on hover, done
// entirely in CSS (see layout.css) — the toggle is a real <a> link now, so
// clicking it navigates instead of just toggling a menu. No JS needed here.
document.addEventListener('DOMContentLoaded', () => {
  // Mobile: hamburger reveals a full-width accordion list (see layout.css).
  const mobileToggle = document.querySelector('.mobile-menu-toggle');
  const mobileMenu = document.querySelector('.mobile-menu');
  if (mobileToggle && mobileMenu) {
    mobileToggle.addEventListener('click', () => {
      mobileMenu.classList.toggle('open');
    });
  }

  document.querySelectorAll('.mobile-menu-item-has-children').forEach((item) => {
    const toggle = item.querySelector('.mobile-menu-parent-toggle');
    toggle.addEventListener('click', () => {
      item.classList.toggle('open');
    });
  });
});
