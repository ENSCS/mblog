<?php
$articlesDir = __DIR__ . '/articles/';
$slug = $_GET['slug'] ?? '';
$article = null;

if (preg_match('/^[a-z0-9\-]+$/', $slug) && is_file($articlesDir . $slug . '.json')) {
    $article = json_decode(file_get_contents($articlesDir . $slug . '.json'), true);
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $article ? 'แก้ไข: ' . htmlspecialchars($article['title']) : 'เขียนบทความใหม่' ?> — mBlog</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.snow.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/github.min.css">
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<div class="topbar">
  <div class="container">
    <a href="index.php">mBlog</a>
    <div class="actions">
      <a href="index.php">รายการบทความ</a>
    </div>
  </div>
</div>
<div class="container">
  <div class="field">
    <label for="title">ชื่อบทความ</label>
    <input type="text" id="title" value="<?= $article ? htmlspecialchars($article['title']) : '' ?>" placeholder="ใส่ชื่อบทความ...">
  </div>
  <input type="hidden" id="slug" value="<?= $article ? htmlspecialchars($article['slug']) : '' ?>">

  <div id="editor-container"></div>

  <div class="toolbar-row">
    <button class="btn" id="save-btn">บันทึกบทความ</button>
    <span id="save-status"></span>
    <a id="view-link" href="<?= $article ? 'article.php?slug=' . urlencode($article['slug']) : '#' ?>"
       style="margin-left:16px; display:<?= $article ? 'inline' : 'none' ?>;">ดูบทความ &rarr;</a>
  </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/highlight.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.js"></script>
<script src="assets/editor.js"></script>
<script>
  const existingContent = <?= json_encode($article['content'] ?? '') ?>;
  const quill = initArticleEditor(existingContent);

  document.getElementById('save-btn').addEventListener('click', () => {
    saveArticle(quill, document.getElementById('slug').value);
  });
</script>
</body>
</html>
