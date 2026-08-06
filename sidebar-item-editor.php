<?php
require __DIR__ . '/includes/sidebar.php';
require __DIR__ . '/includes/admin-nav.php';
requireCapability('manage_sidebar');

$id = isset($_GET['id']) ? (int) $_GET['id'] : null;
// Named $sidebarItem, not $item — partials/header.php's own `foreach
// ($menuItems as $item)` loops would otherwise clobber a plain $item by the
// time this file's HTML (which runs after including header.php) reads it
// back, since include() shares the including script's variable scope.
$sidebarItem = $id ? getSidebarItemById($id) : null;

$pageTitle = $sidebarItem ? 'แก้ไข Sidebar: ' . htmlspecialchars($sidebarItem['title']) : 'เพิ่มรายการ Sidebar ใหม่';
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
  const existingContent = <?= json_encode($sidebarItem['content'] ?? '') ?>;
  const quill = initArticleEditor(existingContent);
  setupFeaturedImagePicker();

  document.getElementById('save-btn').addEventListener('click', () => {
    saveSidebarItem(quill, document.getElementById('item-id').value);
  });

  document.getElementById('item-type').addEventListener('change', (e) => {
    document.getElementById('article-fields').style.display = e.target.value === 'iframe' ? 'none' : 'block';
    document.getElementById('iframe-fields').style.display = e.target.value === 'iframe' ? 'block' : 'none';
  });
</script>
<?php
$footerScripts = ob_get_clean();

$layout = render_header(compact('pageTitle', 'extraHead', 'showAdminSidebar'));
?>
  <div class="field">
    <label for="title">ชื่อรายการ (ไว้อ้างอิงในหน้าจัดการ — ไม่ได้แสดงบนเว็บ)</label>
    <input type="text" id="title" value="<?= $sidebarItem ? htmlspecialchars($sidebarItem['title']) : '' ?>" placeholder="เช่น แบนเนอร์คอร์สลงทุน">
  </div>
  <?php $currentItemType = $sidebarItem['type'] ?? 'article'; ?>
  <div class="field">
    <label for="item-type">ประเภท</label>
    <select id="item-type">
      <option value="article" <?= $currentItemType === 'article' ? 'selected' : '' ?>>บทความปกติ (รูป/ข้อความ/ลิงก์)</option>
      <option value="iframe" <?= $currentItemType === 'iframe' ? 'selected' : '' ?>>iframe embed</option>
    </select>
  </div>

  <div id="article-fields" style="display:<?= $currentItemType === 'iframe' ? 'none' : 'block' ?>;">
    <div class="field">
      <label>รูป (ไม่บังคับ — ถ้าไม่ใส่จะแสดงเป็นข้อความล้วนๆ)</label>
      <div id="featured-image-preview" class="featured-image-preview" style="display:<?= !empty($sidebarItem['image']) ? 'flex' : 'none' ?>;">
        <img id="featured-image-thumb" src="<?= htmlspecialchars($sidebarItem['image'] ?? '') ?>" alt="">
        <button type="button" class="btn btn-secondary" id="remove-featured-image-btn">ลบรูป</button>
      </div>
      <div id="featured-image-upload-mode" style="display:<?= !empty($sidebarItem['image']) ? 'none' : 'block' ?>;">
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
      <input type="hidden" id="featured-image" value="<?= htmlspecialchars($sidebarItem['image'] ?? '') ?>">
    </div>
    <div class="field">
      <label for="link-url">ลิงก์ปลายทาง (ไม่บังคับ — คลิกที่การ์ดนี้แล้วไปที่ไหน เช่น https://... หรือ category.php?slug=...)</label>
      <input type="text" id="link-url" value="<?= htmlspecialchars($sidebarItem['link_url'] ?? '') ?>" placeholder="https://...">
    </div>
    <div id="editor-container"></div>
  </div>

  <div id="iframe-fields" style="display:<?= $currentItemType === 'iframe' ? 'block' : 'none' ?>;">
    <div class="field">
      <label for="iframe-src">URL ที่จะ embed</label>
      <input type="text" id="iframe-src" value="<?= htmlspecialchars($sidebarItem['iframe_src'] ?? '') ?>" placeholder="https://...">
    </div>
    <div class="field">
      <label for="iframe-height">ความสูง (px)</label>
      <input type="number" id="iframe-height" value="<?= (int) ($sidebarItem['iframe_height'] ?? 300) ?>" min="50" max="2000">
    </div>
  </div>

  <div class="field">
    <label style="display:flex; align-items:center; gap:6px;"><input type="checkbox" id="is-active" <?= (!$sidebarItem || $sidebarItem['is_active']) ? 'checked' : '' ?>> แสดงรายการนี้</label>
  </div>
  <input type="hidden" id="item-id" value="<?= $sidebarItem['id'] ?? '' ?>">
  <input type="hidden" id="csrf-token" value="<?= htmlspecialchars(csrfToken()) ?>">

  <div class="toolbar-row">
    <button class="btn" id="save-btn">บันทึก</button>
    <span id="save-status"></span>
  </div>
<?php render_sidebar($layout); render_footer(compact('footerScripts')); ?>
