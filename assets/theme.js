// Click handling for the light/dark toggle. The no-flash "apply saved theme
// before paint" logic lives inline in partials/header.php instead — it has
// to run before assets/base.css is even parsed, which is earlier than this
// deferred script (or anything else external) can run.
document.addEventListener('DOMContentLoaded', () => {
  const toggle = document.getElementById('theme-toggle');
  if (!toggle) return;

  function currentTheme() {
    const saved = localStorage.getItem('mblog-theme');
    if (saved === 'light' || saved === 'dark') return saved;
    return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
  }

  toggle.addEventListener('click', () => {
    const next = currentTheme() === 'dark' ? 'light' : 'dark';
    document.documentElement.setAttribute('data-theme', next);
    localStorage.setItem('mblog-theme', next);
  });
});
