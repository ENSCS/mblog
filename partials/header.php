<?php
require_once __DIR__ . '/../includes/menu.php';
$menuItems = getMenuItems();
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $pageTitle ?></title>
<?= $extraHead ?? '' ?>
<?php
// Cache-bust with each file's mtime so a CSS/JS edit shows up immediately
// instead of serving a stale cached copy from the visitor's browser.
$assetVer = fn(string $path) => '?v=' . @filemtime(__DIR__ . '/../' . $path);
?>
<link rel="stylesheet" href="assets/base.css<?= $assetVer('assets/base.css') ?>">
<link rel="stylesheet" href="assets/layout.css<?= $assetVer('assets/layout.css') ?>">
<link rel="stylesheet" href="assets/components.css<?= $assetVer('assets/components.css') ?>">
<script src="assets/menu.js<?= $assetVer('assets/menu.js') ?>" defer></script>
</head>
<body>
<div class="topbar">
  <div class="container">
    <div class="topbar-left">
      <a href="index.php" class="brand"><?= htmlspecialchars(siteSetting('site_name')) ?></a>
      <!-- Desktop: click-to-open dropdown, submenu anchored right of the toggle -->
      <nav class="topbar-menu topbar-menu-desktop">
        <?php foreach ($menuItems as $item): ?>
          <?php if (!empty($item['children'])): ?>
            <div class="menu-item-has-children">
              <button type="button" class="menu-toggle"><?= htmlspecialchars($item['label']) ?> <span class="menu-caret">&#9662;</span></button>
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
