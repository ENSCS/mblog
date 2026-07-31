<?php
// Renderer for sidebar item type 'iframe' — a straightforward <iframe>
// embed (src + height), validated server-side in api/save-sidebar-item.php
// before it ever reaches the DB. $item is in scope from the require in
// partials/sidebar.php's loop.
?>
<div class="sidebar-item">
  <?php if (!empty($item['iframe_src'])): ?>
    <iframe class="sidebar-item-iframe" src="<?= htmlspecialchars($item['iframe_src']) ?>" height="<?= (int) ($item['iframe_height'] ?: 300) ?>" loading="lazy"></iframe>
  <?php endif; ?>
</div>
