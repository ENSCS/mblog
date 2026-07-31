<?php if ($hasSidebar): ?>
  </div><!-- .main-content -->
  <?php if ($showAdminSidebar): ?>
    <?php include __DIR__ . '/admin-sidebar.php'; ?>
  <?php else: ?>
    <aside class="sidebar">
      <?php foreach ($sidebarItems as $item): ?>
        <?php require __DIR__ . '/sidebar-renderers/' . (($item['type'] ?? 'article') === 'iframe' ? 'iframe.php' : 'article.php'); ?>
      <?php endforeach; ?>
    </aside>
  <?php endif; ?>
<?php endif; ?>
