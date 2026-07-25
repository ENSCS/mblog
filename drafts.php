<?php
require __DIR__ . '/includes/articles.php';

$articles = getDraftArticles();

$pageTitle = 'ร่างบทความ — ' . siteSetting('site_name');
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
        <h2><a href="editor.php?slug=<?= urlencode($a['slug']) ?>"><?= htmlspecialchars($a['title']) ?></a> <span class="status-badge status-draft">ร่าง</span> <span class="category-tag <?= $a['type'] === 'page' ? '' : 'category-tag-' . htmlspecialchars(articleCategoryColor($a)) ?>"><?= $a['type'] === 'page' ? 'หน้า' : htmlspecialchars(articleCategory($a)) ?></span></h2>
        <?php // a draft that's never been published has no published_at yet — fall back to updated_at ?>
        <div class="meta"><?= relativeTimeTag($a['published_at'] ?? $a['updated_at']) ?></div>
        <div class="row-actions">
          <a href="editor.php?slug=<?= urlencode($a['slug']) ?>">แก้ไข</a>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
<?php include __DIR__ . '/partials/footer.php'; ?>
