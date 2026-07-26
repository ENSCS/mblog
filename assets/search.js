// Click-to-expand search box in the topbar (partials/header.php) — collapsed
// to just the icon by default so it doesn't crowd the nav row, expands into
// a text input on click. Starts pre-expanded on search.php itself (server
// renders .site-search-open when ?q= is already set), so the query stays
// visible instead of collapsing back over its own result.
document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('site-search-form');
  const toggle = document.getElementById('site-search-toggle');
  const input = document.getElementById('site-search-input');
  if (!form || !toggle || !input) return;

  toggle.addEventListener('click', () => {
    const isOpen = form.classList.toggle('site-search-open');
    if (isOpen) {
      input.focus();
    }
  });

  // Collapse back on an outside click, but only if left empty — a typed
  // query stays visible/open even if focus moves elsewhere.
  document.addEventListener('click', (e) => {
    if (!form.contains(e.target) && !input.value) {
      form.classList.remove('site-search-open');
    }
  });
});
