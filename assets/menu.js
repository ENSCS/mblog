// Click-to-toggle dropdown for menu items with children — click, not hover,
// so it works the same on touch (mobile) and desktop without a separate
// mobile fallback.
document.addEventListener('DOMContentLoaded', () => {
  const dropdowns = document.querySelectorAll('.menu-item-has-children');

  dropdowns.forEach((item) => {
    const toggle = item.querySelector('.menu-toggle');
    toggle.addEventListener('click', (e) => {
      e.stopPropagation();
      const isOpen = item.classList.contains('open');
      dropdowns.forEach((el) => el.classList.remove('open'));
      if (!isOpen) {
        item.classList.add('open');
      }
    });
  });

  document.addEventListener('click', () => {
    dropdowns.forEach((el) => el.classList.remove('open'));
  });

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
