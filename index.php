<?php
$articlesDir = __DIR__ . '/articles/';
$files = glob($articlesDir . '*.json');

$articles = [];
foreach ($files as $file) {
    $data = json_decode(file_get_contents($file), true);
    if ($data) {
        $articles[] = $data;
    }
}
usort($articles, fn($a, $b) => strcmp($b['updated_at'], $a['updated_at']));
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>mBlog</title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<div class="topbar">
  <div class="container">
    <a href="index.php">mBlog</a>
    <div class="actions">
      <a href="editor.php">+ เขียนบทความใหม่</a>
    </div>
  </div>
</div>
<div class="container">
  <?php if (empty($articles)): ?>
    <div class="empty-state">
      ยังไม่มีบทความ — <a href="editor.php">เริ่มเขียนบทความแรก</a>
    </div>
  <?php else: ?>
    <?php foreach ($articles as $a): ?>
      <div class="card article-list-item">
        <h2><a href="article.php?slug=<?= urlencode($a['slug']) ?>"><?= htmlspecialchars($a['title']) ?></a></h2>
        <div class="meta">อัปเดตล่าสุด: <?= htmlspecialchars($a['updated_at']) ?></div>
        <div class="row-actions">
          <a href="article.php?slug=<?= urlencode($a['slug']) ?>">อ่าน</a>
          <a href="editor.php?slug=<?= urlencode($a['slug']) ?>">แก้ไข</a>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>
</body>
</html>
