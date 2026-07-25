<?php
require __DIR__ . '/includes/articles.php';

$articles = getDraftArticles();

$pageTitle = 'ร่างบทความ — mBlog';
$topbarActions = '<a href="editor.php">+ เขียนบทความใหม่</a>';
include __DIR__ . '/partials/header.php';
?>
  <?php if (empty($articles)): ?>
    <div class="empty-state">
      ไม่มีร่างบทความ
    </div>
  <?php else: ?>
    <?php foreach ($articles as $a): ?>
      <div class="card article-list-item">
        <h2><a href="editor.php?slug=<?= urlencode($a['slug']) ?>"><?= htmlspecialchars($a['title']) ?></a> <span class="status-badge status-draft">ร่าง</span> <span class="category-tag"><?= htmlspecialchars(articleCategory($a)) ?></span></h2>
        <div class="meta">อัปเดตล่าสุด: <?= htmlspecialchars($a['updated_at']) ?></div>
        <div class="row-actions">
          <a href="editor.php?slug=<?= urlencode($a['slug']) ?>">แก้ไข</a>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
<?php include __DIR__ . '/partials/footer.php'; ?>
