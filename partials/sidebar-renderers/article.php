<?php
// Renderer for sidebar item type 'article' — moved verbatim out of
// partials/sidebar.php's foreach body when the iframe type was added (see
// sidebar-renderers/iframe.php). $item is in scope from the require in
// partials/sidebar.php's loop.
?>
<div class="sidebar-item">
  <?php if (!empty($item['link_url'])): ?><a class="sidebar-item-link" href="<?= htmlspecialchars($item['link_url']) ?>" target="_blank" rel="noopener noreferrer"><?php endif; ?>
  <?php if (!empty($item['image'])): ?>
    <img src="<?= htmlspecialchars($item['image']) ?>" alt="<?= htmlspecialchars($item['title']) ?>">
  <?php endif; ?>
  <?php if (!empty($item['content'])): ?>
    <div class="sidebar-item-content rich-content ql-editor"><?= $item['content'] ?></div>
  <?php endif; ?>
  <?php if (!empty($item['link_url'])): ?></a><?php endif; ?>
</div>
