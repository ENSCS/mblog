<?php
$articlesDir = __DIR__ . '/articles/';
$slug = $_GET['slug'] ?? '';

if (!preg_match('/^[a-z0-9\-]+$/', $slug) || !is_file($articlesDir . $slug . '.json')) {
    http_response_code(404);
    $article = null;
} else {
    $article = json_decode(file_get_contents($articlesDir . $slug . '.json'), true);
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $article ? htmlspecialchars($article['title']) : 'ไม่พบบทความ' ?> — mBlog</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.snow.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/github.min.css">
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<div class="topbar">
  <div class="container">
    <a href="index.php">mBlog</a>
    <div class="actions">
      <?php if ($article): ?>
        <a href="editor.php?slug=<?= urlencode($slug) ?>">แก้ไข</a>
      <?php endif; ?>
      <a href="editor.php">+ เขียนบทความใหม่</a>
    </div>
  </div>
</div>
<div class="container">
  <?php if (!$article): ?>
    <div class="empty-state">ไม่พบบทความนี้ — <a href="index.php">กลับหน้ารายการ</a></div>
  <?php else: ?>
    <div class="card">
      <h1 class="article-title"><?= htmlspecialchars($article['title']) ?></h1>
      <div class="meta" style="margin-bottom:20px;">อัปเดตล่าสุด: <?= htmlspecialchars($article['updated_at']) ?></div>
      <div class="article-content ql-editor" style="padding:0;"><?= $article['content'] ?></div>
    </div>
  <?php endif; ?>
</div>
<script src="assets/copy-button.js"></script>
</body>
</html>
