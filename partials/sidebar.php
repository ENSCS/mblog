<?php if ($hasSidebar): ?>
  </div><!-- .main-content -->
  <?php if ($showAdminSidebar): ?>
    <?php include __DIR__ . '/admin-sidebar.php'; ?>
  <?php else: ?>
    <aside class="sidebar">
      <?php foreach ($sidebarItems as $item): ?>
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
      <?php endforeach; ?>
    </aside>
  <?php endif; ?>
<?php endif; ?>
