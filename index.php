<?php
require __DIR__ . '/includes/articles.php';

$articles = getArticles();

$pageTitle = siteSetting('site_name');
$topbarActions = '<a href="editor.php">+ เขียนบทความใหม่</a>';
include __DIR__ . '/partials/header.php';
?>
  <?php if (empty($articles)): ?>
    <div class="empty-state">
      ยังไม่มีบทความ — <a href="editor.php">เริ่มเขียนบทความแรก</a>
    </div>
  <?php else: ?>
    <?php foreach ($articles as $a): ?>
      <div class="card article-list-item">
        <h2><a href="article.php?slug=<?= urlencode($a['slug']) ?>"><?= htmlspecialchars($a['title']) ?></a> <span class="category-tag"><?= htmlspecialchars(articleCategory($a)) ?></span></h2>
        <div class="meta">อัปเดตล่าสุด: <?= htmlspecialchars($a['updated_at']) ?></div>
        <div class="row-actions">
          <a href="article.php?slug=<?= urlencode($a['slug']) ?>">อ่าน</a>
          <a href="editor.php?slug=<?= urlencode($a['slug']) ?>">แก้ไข</a>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
<?php include __DIR__ . '/partials/footer.php'; ?>
