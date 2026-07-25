<?php
require __DIR__ . '/includes/articles.php';

$slug = $_GET['slug'] ?? '';
$tag = getTagBySlug($slug);
if (!$tag) {
    renderErrorPage(404, 'ไม่พบแท็กนี้');
}

$articles = getArticlesByTagSlug($slug);

$pageTitle = 'แท็ก: ' . htmlspecialchars($tag['name']) . ' — ' . siteSetting('site_name');
$topbarActions = '<a href="editor.php">+ เขียนบทความใหม่</a>';
include __DIR__ . '/partials/header.php';
?>
  <h1 class="article-title">#<?= htmlspecialchars($tag['name']) ?></h1>
  <?php if (empty($articles)): ?>
    <div class="empty-state">
      ยังไม่มีบทความในแท็กนี้
    </div>
  <?php else: ?>
    <?php foreach ($articles as $a): ?>
      <div class="card article-list-item">
        <h2><a href="article.php?slug=<?= urlencode($a['slug']) ?>"><?= htmlspecialchars($a['title']) ?></a></h2>
        <div class="meta"><?= relativeTimeTag($a['published_at']) ?></div>
        <div class="row-actions">
          <a href="article.php?slug=<?= urlencode($a['slug']) ?>">อ่าน</a>
          <a href="editor.php?slug=<?= urlencode($a['slug']) ?>">แก้ไข</a>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
<?php include __DIR__ . '/partials/footer.php'; ?>
