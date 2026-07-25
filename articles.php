<?php
require __DIR__ . '/includes/articles.php';

$articles = getArticles();

$pageTitle = 'บทความทั้งหมด — ' . siteSetting('site_name');
$topbarActions = '<a href="editor.php">+ เขียนบทความใหม่</a>';
include __DIR__ . '/partials/header.php';
?>
  <h1 class="article-title">บทความทั้งหมด</h1>
  <?php if (empty($articles)): ?>
    <div class="empty-state">
      ยังไม่มีบทความ — <a href="editor.php">เริ่มเขียนบทความแรก</a>
    </div>
  <?php else: ?>
    <?php foreach ($articles as $a): ?>
      <div class="card article-list-item">
        <?php $categorySlug = articleCategorySlug($a); $categoryColor = articleCategoryColor($a); ?>
        <h2><a href="article.php?slug=<?= urlencode($a['slug']) ?>"><?= htmlspecialchars($a['title']) ?></a>
          <?php if ($categorySlug): ?>
            <a class="category-tag category-tag-<?= htmlspecialchars($categoryColor) ?>" href="category.php?slug=<?= urlencode($categorySlug) ?>"><?= htmlspecialchars(articleCategory($a)) ?></a>
          <?php else: ?>
            <span class="category-tag category-tag-<?= htmlspecialchars($categoryColor) ?>"><?= htmlspecialchars(articleCategory($a)) ?></span>
          <?php endif; ?>
        </h2>
        <div class="meta"><?= relativeTimeTag($a['published_at']) ?></div>
        <div class="row-actions">
          <a href="article.php?slug=<?= urlencode($a['slug']) ?>">อ่าน</a>
          <a href="editor.php?slug=<?= urlencode($a['slug']) ?>">แก้ไข</a>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
<?php include __DIR__ . '/partials/footer.php'; ?>
