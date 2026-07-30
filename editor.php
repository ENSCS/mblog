<?php
require __DIR__ . '/includes/articles.php';
require __DIR__ . '/includes/admin-nav.php';

$slug = $_GET['slug'] ?? '';
$article = getArticleForEdit($slug);
$currentStatus = $article ? articleStatus($article) : 'draft';
$currentType = $article['type'] ?? 'post';
$categories = getCategories();
$currentCategory = $article ? (articleCategory($article) ?? '') : '';
$currentFeaturedImage = $article['featured_image'] ?? '';
// '' (ตามค่าเว็บ) เป็นค่าเริ่มต้นทั้งบทความใหม่และเก่าที่ยังไม่เคย override — ดู
// show_sidebar ใน database/article_sidebar_toggle.sql สำหรับความหมาย NULL/1/0 เต็มๆ
$currentShowSidebar = isset($article['show_sidebar']) && $article['show_sidebar'] !== null
    ? (string) (int) $article['show_sidebar']
    : '';
$currentTags = $article ? array_column(getArticleTags($article['id']), 'name') : [];
$allTagNames = array_column(getAllTags(), 'name');

$pageTitle = $article ? 'แก้ไข: ' . htmlspecialchars($article['title']) : 'เขียนบทความใหม่';
$extraHead = '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/github-dark.min.css">' . "\n"
    . '<link rel="stylesheet" href="assets/article.css?v=' . @filemtime(__DIR__ . '/assets/article.css') . '">' . "\n"
    . '<link rel="stylesheet" href="assets/editor.css?v=' . @filemtime(__DIR__ . '/assets/editor.css') . '">';
$topbarActions = adminTopbarActions(['<a href="articles.php">รายการบทความ</a>']);
$showAdminSidebar = true;

ob_start();
?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/highlight.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.js"></script>
<script src="assets/editor.js?v=<?= @filemtime(__DIR__ . '/assets/editor.js') ?>"></script>
<script>
  const existingContent = <?= json_encode($article['content'] ?? '') ?>;
  const quill = initArticleEditor(existingContent);
  setupFeaturedImagePicker();
  setupTagInput(<?= json_encode($currentTags, JSON_UNESCAPED_UNICODE) ?>, <?= json_encode($allTagNames, JSON_UNESCAPED_UNICODE) ?>);

  document.getElementById('type').addEventListener('change', (e) => {
    document.getElementById('category-field').style.display = e.target.value === 'page' ? 'none' : 'block';
    document.getElementById('tags-field').style.display = e.target.value === 'page' ? 'none' : 'block';
  });

  document.getElementById('save-draft-btn').addEventListener('click', () => {
    saveArticle(quill, document.getElementById('article-id').value, document.getElementById('slug').value, 'draft');
  });
  document.getElementById('publish-btn').addEventListener('click', () => {
    saveArticle(quill, document.getElementById('article-id').value, document.getElementById('slug').value, 'published');
  });
</script>
<?php
$footerScripts = ob_get_clean();

$layout = render_header(compact('pageTitle', 'extraHead', 'topbarActions', 'showAdminSidebar'));
?>
  <div class="field">
    <label for="title">ชื่อบทความ</label>
    <input type="text" id="title" value="<?= $article ? htmlspecialchars($article['title']) : '' ?>" placeholder="ใส่ชื่อบทความ...">
  </div>
  <div class="field">
    <label for="type">ประเภท</label>
    <select id="type">
      <option value="post" <?= $currentType === 'post' ? 'selected' : '' ?>>บทความ</option>
      <option value="page" <?= $currentType === 'page' ? 'selected' : '' ?>>หน้า (About/ติดต่อ/นโยบาย ฯลฯ)</option>
    </select>
  </div>
  <div class="field" id="category-field" style="display:<?= $currentType === 'page' ? 'none' : 'block' ?>;">
    <label for="category">หมวดหมู่</label>
    <select id="category">
      <option value="" <?= $currentCategory === '' ? 'selected' : '' ?>>ไม่ระบุหมวดหมู่</option>
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
    <div id="featured-image-upload-mode" style="display:<?= $currentFeaturedImage ? 'none' : 'block' ?>;">
      <input type="file" id="featured-image-input" accept="image/*">
      <div style="margin-top:6px;"><a href="#" id="featured-image-url-toggle" style="font-size:13px; color:var(--text-muted);">หรือใส่ URL แทน</a></div>
    </div>
    <div id="featured-image-url-mode" style="display:none;">
      <div style="display:flex; gap:8px;">
        <input type="text" id="featured-image-url-input" placeholder="https://..." style="flex:1;">
        <button type="button" class="btn btn-secondary" id="featured-image-url-confirm-btn">ใช้ภาพนี้</button>
      </div>
      <div style="margin-top:6px;"><a href="#" id="featured-image-url-cancel" style="font-size:13px; color:var(--text-muted);">กลับไปอัปโหลดไฟล์</a></div>
    </div>
    <input type="hidden" id="featured-image" value="<?= htmlspecialchars($currentFeaturedImage) ?>">
  </div>
  <div class="field">
    <label for="show-sidebar">Sidebar (สำหรับหน้านี้โดยเฉพาะ)</label>
    <select id="show-sidebar">
      <option value="" <?= $currentShowSidebar === '' ? 'selected' : '' ?>>ตามค่าเว็บ (ค่าเริ่มต้น)</option>
      <option value="1" <?= $currentShowSidebar === '1' ? 'selected' : '' ?>>เปิด</option>
      <option value="0" <?= $currentShowSidebar === '0' ? 'selected' : '' ?>>ปิด</option>
    </select>
  </div>
  <div class="field" id="tags-field" style="display:<?= $currentType === 'page' ? 'none' : 'block' ?>;">
    <label for="tag-input">แท็ก (ไม่บังคับ — พิมพ์ชื่อแล้วกด Enter หรือเลือกจากรายการที่แนะนำ)</label>
    <div class="tag-input-wrap">
      <div id="tag-chips" class="tag-chip-list"></div>
      <input type="text" id="tag-input" placeholder="พิมพ์แท็ก แล้วกด Enter..." autocomplete="off">
      <div id="tag-suggestions" class="tag-suggestions"></div>
    </div>
    <input type="hidden" id="tags" value="">
  </div>
  <div class="field">
    <label for="slug">Slug (URL ของบทความ — ไม่ใส่จะสร้างจากชื่อบทความให้อัตโนมัติ)</label>
    <input type="text" id="slug" value="<?= $article ? htmlspecialchars($article['slug']) : '' ?>" placeholder="เว้นว่างไว้ให้สร้างอัตโนมัติจากชื่อบทความ">
    <div id="slug-warning" style="display:<?= $currentStatus === 'published' ? 'block' : 'none' ?>; margin-top:4px; font-size:13px; color:#92400e;">
      ⚠ บทความนี้เผยแพร่แล้ว — แก้ slug จะทำให้ลิงก์เดิมที่แชร์ไปแล้วใช้ไม่ได้ (404)
    </div>
  </div>
  <input type="hidden" id="article-id" value="<?= $article['id'] ?? '' ?>">

  <div id="editor-container"></div>

  <div class="toolbar-row">
    <button class="btn btn-secondary" id="save-draft-btn">บันทึกร่าง</button>
    <button class="btn" id="publish-btn">เผยแพร่</button>
    <span id="status-badge" class="status-badge status-<?= $currentStatus ?>"><?= $currentStatus === 'published' ? 'เผยแพร่แล้ว' : 'ร่าง' ?></span>
    <span id="save-status"></span>
    <a id="view-link" href="<?= $article ? ($currentType === 'page' ? 'page.php' : 'article.php') . '?slug=' . urlencode($article['slug']) : '#' ?>"
       style="margin-left:16px; display:<?= $article ? 'inline' : 'none' ?>;">ดูบทความ &rarr;</a>
  </div>
<?php render_sidebar($layout); render_footer(compact('footerScripts')); ?>
