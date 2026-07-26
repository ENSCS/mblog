<?php if ($hasSidebar): ?>
  </div><!-- .main-content -->
  <aside class="sidebar">
    <?php foreach ($sidebarItems as $item): ?>
      <div class="sidebar-item">
        <?php if (!empty($item['link_url'])): ?><a class="sidebar-item-link" href="<?= htmlspecialchars($item['link_url']) ?>"><?php endif; ?>
        <?php if (!empty($item['image'])): ?>
          <img src="<?= htmlspecialchars($item['image']) ?>" alt="<?= htmlspecialchars($item['title']) ?>">
        <?php endif; ?>
        <?php if (!empty($item['content'])): ?>
          <div class="sidebar-item-content"><?= $item['content'] ?></div>
        <?php endif; ?>
        <?php if (!empty($item['link_url'])): ?></a><?php endif; ?>
      </div>
    <?php endforeach; ?>
  </aside>
<?php endif; ?>
</div>
<footer class="site-footer">
  <div class="container">&copy; <?= date('Y') ?> <?= htmlspecialchars(siteSetting('site_name')) ?> — <?= htmlspecialchars(siteSetting('footer_tagline')) ?></div>
</footer>
<?= $footerScripts ?? '' ?>
</body>
</html>
