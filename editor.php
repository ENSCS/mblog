<?php
require __DIR__ . '/includes/articles.php';

$slug = $_GET['slug'] ?? '';
$article = getArticleForEdit($slug);
$currentStatus = $article ? articleStatus($article) : 'draft';
$categories = getCategories();
$currentCategory = $article ? articleCategory($article) : $categories[0];
$currentFeaturedImage = $article['featured_image'] ?? '';

$pageTitle = ($article ? 'แก้ไข: ' . htmlspecialchars($article['title']) : 'เขียนบทความใหม่') . ' — mBlog';
$extraHead = '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.snow.css">' . "\n"
    . '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/github.min.css">';
$topbarActions = '<a href="index.php">รายการบทความ</a>';

ob_start();
?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/highlight.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.js"></script>
<script src="assets/editor.js"></script>
<script>
  const existingContent = <?= json_encode($article['content'] ?? '') ?>;
  const quill = initArticleEditor(existingContent);
  setupFeaturedImagePicker();

  document.getElementById('save-draft-btn').addEventListener('click', () => {
    saveArticle(quill, document.getElementById('slug').value, 'draft');
  });
  document.getElementById('publish-btn').addEventListener('click', () => {
    saveArticle(quill, document.getElementById('slug').value, 'published');
  });
</script>
<?php
$footerScripts = ob_get_clean();

include __DIR__ . '/partials/header.php';
?>
  <div class="field">
    <label for="title">ชื่อบทความ</label>
    <input type="text" id="title" value="<?= $article ? htmlspecialchars($article['title']) : '' ?>" placeholder="ใส่ชื่อบทความ...">
  </div>
  <div class="field">
    <label for="category">หมวดหมู่</label>
    <select id="category">
      <?php foreach ($categories as $cat): ?>
        <option value="<?= htmlspecialchars($cat) ?>" <?= $cat === $currentCategory ? 'selected' : '' ?>><?= htmlspecialchars($cat) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="field">
    <label for="excerpt">สรุปสั้น (ไม่บังคับ — ถ้าไม่ใส่จะตัดจากเนื้อหาให้อัตโนมัติ)</label>
    <textarea id="excerpt" rows="2" placeholder="สรุปสั้นๆ สำหรับแสดงตอนแชร์ลิงก์..."><?= $article ? htmlspecialchars($article['excerpt'] ?? '') : '' ?></textarea>
  </div>
  <div class="field">
    <label>ภาพหลัก (Featured Image, ไม่บังคับ — ถ้าไม่ใส่จะใช้รูปแรกในเนื้อหาแทน)</label>
    <div id="featured-image-preview" class="featured-image-preview" style="display:<?= $currentFeaturedImage ? 'flex' : 'none' ?>;">
      <img id="featured-image-thumb" src="<?= htmlspecialchars($currentFeaturedImage) ?>" alt="">
      <button type="button" class="btn btn-secondary" id="remove-featured-image-btn">ลบภาพหลัก</button>
    </div>
    <input type="file" id="featured-image-input" accept="image/*" style="display:<?= $currentFeaturedImage ? 'none' : 'block' ?>;">
    <input type="hidden" id="featured-image" value="<?= htmlspecialchars($currentFeaturedImage) ?>">
  </div>
  <input type="hidden" id="slug" value="<?= $article ? htmlspecialchars($article['slug']) : '' ?>">

  <div id="editor-container"></div>

  <div class="toolbar-row">
    <button class="btn btn-secondary" id="save-draft-btn">บันทึกร่าง</button>
    <button class="btn" id="publish-btn">เผยแพร่</button>
    <span id="status-badge" class="status-badge status-<?= $currentStatus ?>"><?= $currentStatus === 'published' ? 'เผยแพร่แล้ว' : 'ร่าง' ?></span>
    <span id="save-status"></span>
    <a id="view-link" href="<?= $article ? 'article.php?slug=' . urlencode($article['slug']) : '#' ?>"
       style="margin-left:16px; display:<?= $article ? 'inline' : 'none' ?>;">ดูบทความ &rarr;</a>
  </div>
<?php include __DIR__ . '/partials/footer.php'; ?>
