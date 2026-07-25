<?php
require __DIR__ . '/includes/articles.php';

$slug = $_GET['slug'] ?? '';
$article = getArticle($slug);
if (!$article) {
    http_response_code(404);
}

$pageTitle = ($article ? htmlspecialchars($article['title']) : 'ไม่พบบทความ') . ' — mBlog';
$extraHead = '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.snow.css">' . "\n"
    . '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/github.min.css">';
$topbarActions = ($article ? '<a href="editor.php?slug=' . urlencode($slug) . '">แก้ไข</a>' : '')
    . '<a href="editor.php">+ เขียนบทความใหม่</a>';
$footerScripts = '<script src="assets/copy-button.js"></script>';
include __DIR__ . '/partials/header.php';
?>
  <?php if (!$article): ?>
    <div class="empty-state">ไม่พบบทความนี้ — <a href="index.php">กลับหน้ารายการ</a></div>
  <?php else: ?>
    <div class="card">
      <h1 class="article-title"><?= htmlspecialchars($article['title']) ?></h1>
      <div class="meta" style="margin-bottom:20px;">อัปเดตล่าสุด: <?= htmlspecialchars($article['updated_at']) ?></div>
      <div class="article-content ql-editor" style="padding:0;"><?= $article['content'] ?></div>
    </div>
  <?php endif; ?>
<?php include __DIR__ . '/partials/footer.php'; ?>
