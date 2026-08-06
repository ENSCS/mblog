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

// Drop entries the current user would just get a 403 on — same capability
// string each entry's own page checks via requireCapability() (see
// adminNavGroups()'s own comment). A group left with nothing visible (e.g.
// "ตั้งค่าเว็บ" for an author) is skipped entirely so it doesn't show as an
// empty heading.
$adminNavGroups = array_filter(array_map(
    fn($items) => array_values(array_filter($items, fn($item) => !isset($item['capability']) || userCan($item['capability']))),
    $adminNavGroups
));
?>
<aside class="sidebar admin-sidebar-shell">
  <div class="admin-sidebar-group">
    <a class="admin-sidebar-link<?= $adminCurrentScript === 'admin.php' ? ' admin-sidebar-link-active' : '' ?>" href="admin.php">
      <span>&larr; จัดการเว็บ</span>
    </a>
  </div>
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
