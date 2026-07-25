<?php
require __DIR__ . '/includes/articles.php';

$pages = getPages();

$pageTitle = 'หน้าทั้งหมด — ' . siteSetting('site_name');
$topbarActions = '<a href="editor.php">+ เขียนบทความใหม่</a>';
include __DIR__ . '/partials/header.php';
?>
  <h1 class="article-title">หน้าทั้งหมด</h1>
  <?php if (empty($pages)): ?>
    <div class="empty-state">
      ยังไม่มีหน้า
    </div>
  <?php else: ?>
    <?php foreach ($pages as $p): ?>
      <div class="card article-list-item">
        <h2><a href="page.php?slug=<?= urlencode($p['slug']) ?>"><?= htmlspecialchars($p['title']) ?></a></h2>
        <div class="meta">อัปเดตล่าสุด: <?= htmlspecialchars($p['updated_at']) ?></div>
        <div class="row-actions">
          <a href="page.php?slug=<?= urlencode($p['slug']) ?>">อ่าน</a>
          <a href="editor.php?slug=<?= urlencode($p['slug']) ?>">แก้ไข</a>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
<?php include __DIR__ . '/partials/footer.php'; ?>
