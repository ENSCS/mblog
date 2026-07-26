<?php
require_once __DIR__ . '/../includes/menu.php';
$menuItems = getMenuItems();
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<script>
  // Applies the saved/system theme before first paint, so switching themes
  // never causes a flash of the wrong one on load — must run before any
  // stylesheet below, since it's the CSS that actually reads [data-theme].
  (function () {
    var saved = localStorage.getItem('mblog-theme');
    if (saved === 'light' || saved === 'dark') {
      document.documentElement.setAttribute('data-theme', saved);
    }
  })();
</script>
<title><?= $pageTitle ?></title>
<?= $extraHead ?? '' ?>
<?php
// Cache-bust with each file's mtime so a CSS/JS edit shows up immediately
// instead of serving a stale cached copy from the visitor's browser.
$assetVer = fn(string $path) => '?v=' . @filemtime(__DIR__ . '/../' . $path);

$faviconPath = siteSetting('site_favicon');
$faviconMime = ['ico' => 'image/x-icon', 'png' => 'image/png', 'svg' => 'image/svg+xml', 'gif' => 'image/gif'];
?>
<?php if ($faviconPath): ?>
<link rel="icon" type="<?= htmlspecialchars($faviconMime[strtolower(pathinfo($faviconPath, PATHINFO_EXTENSION))] ?? 'image/x-icon') ?>" href="<?= htmlspecialchars($faviconPath) ?><?= $assetVer($faviconPath) ?>">
<?php endif; ?>
<link rel="stylesheet" href="assets/base.css<?= $assetVer('assets/base.css') ?>">
<link rel="stylesheet" href="assets/layout.css<?= $assetVer('assets/layout.css') ?>">
<link rel="stylesheet" href="assets/components.css<?= $assetVer('assets/components.css') ?>">
<script src="assets/menu.js<?= $assetVer('assets/menu.js') ?>" defer></script>
<script src="assets/theme.js<?= $assetVer('assets/theme.js') ?>" defer></script>
</head>
<body>
<div class="topbar">
  <div class="container">
    <div class="topbar-left">
      <a href="index.php" class="brand">
        <?php if ($logoPath = siteSetting('site_logo')): ?>
          <img src="<?= htmlspecialchars($logoPath) ?><?= $assetVer($logoPath) ?>" alt="" class="brand-logo">
        <?php endif; ?>
        <?= htmlspecialchars(siteSetting('site_name')) ?>
      </a>
      <!-- Desktop: click-to-open dropdown, submenu anchored right of the toggle -->
      <nav class="topbar-menu topbar-menu-desktop">
        <?php foreach ($menuItems as $item): ?>
          <?php if (!empty($item['children'])): ?>
            <div class="menu-item-has-children">
              <a href="<?= htmlspecialchars($item['href']) ?>" class="menu-toggle"><?= htmlspecialchars($item['label']) ?> <span class="menu-caret">&#9662;</span></a>
              <ul class="submenu">
                <?php foreach ($item['children'] as $child): ?>
                  <li><a href="<?= htmlspecialchars($child['href']) ?>"><?= htmlspecialchars($child['label']) ?></a></li>
                <?php endforeach; ?>
              </ul>
            </div>
          <?php else: ?>
            <a href="<?= htmlspecialchars($item['href']) ?>"><?= htmlspecialchars($item['label']) ?></a>
          <?php endif; ?>
        <?php endforeach; ?>
      </nav>
    </div>
    <div class="actions">
      <button type="button" id="theme-toggle" class="theme-toggle" aria-label="สลับธีมสว่าง/มืด">
        <svg class="theme-icon theme-icon-sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="4"></circle><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"></path></svg>
        <svg class="theme-icon theme-icon-moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path></svg>
      </button>
      <?= $topbarActions ?? '' ?>
    </div>
  </div>
</div>
<!-- Mobile: hamburger toggle + full-width accordion list (hidden on desktop) -->
<button type="button" class="mobile-menu-toggle">&#9776; Menu</button>
<nav class="mobile-menu">
  <?php foreach ($menuItems as $item): ?>
    <?php if (!empty($item['children'])): ?>
      <div class="mobile-menu-item-has-children">
        <button type="button" class="mobile-menu-row mobile-menu-parent-toggle">
          <span><?= htmlspecialchars($item['label']) ?></span>
          <span class="menu-caret">&#9662;</span>
        </button>
        <div class="mobile-submenu">
          <?php foreach ($item['children'] as $child): ?>
            <a class="mobile-submenu-link" href="<?= htmlspecialchars($child['href']) ?>"><?= htmlspecialchars($child['label']) ?></a>
          <?php endforeach; ?>
        </div>
      </div>
    <?php else: ?>
      <a class="mobile-menu-row" href="<?= htmlspecialchars($item['href']) ?>"><?= htmlspecialchars($item['label']) ?></a>
    <?php endif; ?>
  <?php endforeach; ?>
</nav>
<div class="container">
