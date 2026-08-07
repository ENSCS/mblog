<?php
require __DIR__ . '/includes/articles.php';
require __DIR__ . '/includes/admin-nav.php';
requireCapability('edit_articles');

$slug = $_GET['slug'] ?? '';
$article = getArticleForEdit($slug);
// 'author' role opening someone else's article by URL — the real
// enforcement is in api/save.php (a raw POST there would otherwise bypass
// this), this is just so they see a clear 403 instead of a form that fails
// silently on save.
if ($article && (int) ($article['author_id'] ?? 0) !== (int) (currentStaff()['id'] ?? 0) && !staffCan('edit_others_articles')) {
    renderErrorPage(403, 'ไม่มีสิทธิ์แก้ไขบทความนี้');
}
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
$currentSeoTitle = $article['seo_title'] ?? '';
$currentSeoDescription = $article['seo_description'] ?? '';
$currentSeoNoindex = !empty($article['seo_noindex']);
// datetime-local wants "Y-m-d\TH:i" with no timezone/seconds — only prefill
// scheduled-at when the article is actually still in the scheduled state
// (a published article's published_at is its real publish date, not
// something to show back as a pending schedule).
$currentScheduledAt = ($currentStatus === 'scheduled' && !empty($article['published_at']))
    ? date('Y-m-d\TH:i', strtotime($article['published_at']))
    : '';
$currentExpiresAt = !empty($article['expires_at']) ? date('Y-m-d\TH:i', strtotime($article['expires_at'])) : '';
$statusLabels = ['published' => 'เผยแพร่แล้ว', 'scheduled' => 'ตั้งเวลา', 'draft' => 'ร่าง', 'private' => 'ส่วนตัว'];
$currentTags = $article ? array_column(getArticleTags($article['id']), 'name') : [];
$allTagNames = array_column(getAllTags(), 'name');

$pageTitle = $article ? 'แก้ไข: ' . htmlspecialchars($article['title']) : 'เขียนบทความใหม่';
$extraHead = '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/github-dark.min.css">' . "\n"
    . '<link rel="stylesheet" href="assets/article.css?v=' . @filemtime(__DIR__ . '/assets/article.css') . '">' . "\n"
    . '<link rel="stylesheet" href="assets/editor.css?v=' . @filemtime(__DIR__ . '/assets/editor.css') . '">';
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
  document.getElementById('private-btn').addEventListener('click', () => {
    saveArticle(quill, document.getElementById('article-id').value, document.getElementById('slug').value, 'private');
  });
  document.getElementById('schedule-btn').addEventListener('click', () => {
    const scheduledAt = document.getElementById('scheduled-at').value;
    if (!scheduledAt) {
      alert('กรุณาเลือกวันที่/เวลาที่ต้องการเผยแพร่');
      return;
    }
    if (new Date(scheduledAt) <= new Date()) {
      alert('เวลาที่ตั้งต้องเป็นอนาคต');
      return;
    }
    saveArticle(quill, document.getElementById('article-id').value, document.getElementById('slug').value, 'scheduled');
  });
</script>
<?php
$footerScripts = ob_get_clean();

$layout = render_header(compact('pageTitle', 'extraHead', 'showAdminSidebar'));
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
    <label for="seo-title">SEO Title (ไม่บังคับ — ถ้าไม่ใส่จะใช้ชื่อบทความแทนใน &lt;title&gt;/og:title)</label>
    <input type="text" id="seo-title" value="<?= htmlspecialchars($currentSeoTitle) ?>" placeholder="เว้นว่างไว้ให้ใช้ชื่อบทความ">
  </div>
  <div class="field">
    <label for="seo-description">SEO Description (ไม่บังคับ — ถ้าไม่ใส่จะตัดจากเนื้อหาให้อัตโนมัติแทนใน meta description)</label>
    <textarea id="seo-description" rows="2" placeholder="เว้นว่างไว้ให้ตัดจากเนื้อหาอัตโนมัติ..."><?= htmlspecialchars($currentSeoDescription) ?></textarea>
  </div>
  <div class="field">
    <label style="display:flex; align-items:center; gap:6px;"><input type="checkbox" id="seo-noindex" <?= $currentSeoNoindex ? 'checked' : '' ?>> Noindex (ไม่ให้ Google เก็บบทความนี้ในผลค้นหา)</label>
  </div>
  <div class="field">
    <label for="scheduled-at">ตั้งเวลาเผยแพร่ (ไม่บังคับ — เลือกวันที่/เวลาในอนาคต แล้วกด "ตั้งเวลาเผยแพร่" ด้านล่างแทนปุ่มเผยแพร่)</label>
    <input type="datetime-local" id="scheduled-at" value="<?= htmlspecialchars($currentScheduledAt) ?>">
  </div>
  <div class="field">
    <label for="expires-at">วันหมดอายุ (ไม่บังคับ — พ้นเวลานี้แล้วบทความจะไม่แสดงบนเว็บสาธารณะอีก)</label>
    <input type="datetime-local" id="expires-at" value="<?= htmlspecialchars($currentExpiresAt) ?>">
  </div>
  <div class="field">
    <label for="slug">Slug (URL ของบทความ — ไม่ใส่จะสร้างจากชื่อบทความให้อัตโนมัติ)</label>
    <input type="text" id="slug" value="<?= $article ? htmlspecialchars($article['slug']) : '' ?>" placeholder="เว้นว่างไว้ให้สร้างอัตโนมัติจากชื่อบทความ">
    <div id="slug-warning" style="display:<?= $currentStatus === 'published' ? 'block' : 'none' ?>; margin-top:4px; font-size:13px; color:#92400e;">
      ⚠ บทความนี้เผยแพร่แล้ว — แก้ slug จะทำให้ลิงก์เดิมที่แชร์ไปแล้วใช้ไม่ได้ (404)
    </div>
  </div>
  <input type="hidden" id="article-id" value="<?= $article['id'] ?? '' ?>">
  <input type="hidden" id="csrf-token" value="<?= htmlspecialchars(csrfToken()) ?>">

  <div id="editor-container"></div>

  <div class="toolbar-row">
    <button class="btn btn-secondary" id="save-draft-btn">บันทึกร่าง</button>
    <button class="btn btn-secondary" id="schedule-btn">ตั้งเวลาเผยแพร่</button>
    <button class="btn btn-secondary" id="private-btn">เผยแพร่แบบส่วนตัว</button>
    <button class="btn" id="publish-btn">เผยแพร่</button>
    <span id="status-badge" class="status-badge status-<?= $currentStatus ?>"><?= $statusLabels[$currentStatus] ?? 'ร่าง' ?></span>
    <span id="save-status"></span>
    <a id="view-link" href="<?= $article ? ($currentType === 'page' ? 'page.php' : 'article.php') . '?slug=' . urlencode($article['slug']) : '#' ?>"
       style="margin-left:16px; display:<?= $article ? 'inline' : 'none' ?>;">ดูบทความ &rarr;</a>
  </div>
<?php render_sidebar($layout); render_footer(compact('footerScripts')); ?>
