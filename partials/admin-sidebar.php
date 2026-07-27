<?php
// Persistent admin nav — every admin page sets $showAdminSidebar = true
// before including header.php, which turns this on in place of the public
// reader-facing content sidebar ($showSidebar, an unrelated feature). Root
// element deliberately uses the same class="sidebar" the content sidebar
// uses (width/flex-shrink/gap/responsive collapse — assets/layout.css) —
// one engine for the outer shape, not a parallel one; only the markup
// *inside* it (admin-sidebar-group/-link/-badge below) is admin-specific.
// admin-sidebar-shell overrides just --sidebar-width (240px instead of the
// public sidebar's 280px default) — same engine, one variable tweaked.
// Groups/labels/badges come from adminNavGroups(), one place to change
// instead of duplicating this list.
$adminNavGroups = adminNavGroups();
$adminCurrentScript = basename($_SERVER['SCRIPT_NAME']);
?>
<aside class="sidebar admin-sidebar-shell">
  <?php foreach ($adminNavGroups as $groupLabel => $items): ?>
    <div class="admin-sidebar-group">
      <div class="admin-sidebar-group-label"><?= htmlspecialchars($groupLabel) ?></div>
      <?php foreach ($items as $item): ?>
        <a class="admin-sidebar-link<?= $item['href'] === $adminCurrentScript ? ' admin-sidebar-link-active' : '' ?>" href="<?= htmlspecialchars($item['href']) ?>">
          <span><?= htmlspecialchars($item['label']) ?></span>
          <?php if (isset($item['badge'])): ?><span class="admin-sidebar-badge"><?= (int) $item['badge'] ?></span><?php endif; ?>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endforeach; ?>
</aside>
