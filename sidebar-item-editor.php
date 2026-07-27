<?php
require __DIR__ . '/includes/sidebar.php';
require __DIR__ . '/includes/admin-nav.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : null;
// Named $sidebarItem, not $item — partials/header.php's own `foreach
// ($menuItems as $item)` loops would otherwise clobber a plain $item by the
// time this file's HTML (which runs after including header.php) reads it
// back, since include() shares the including script's variable scope.
$sidebarItem = $id ? getSidebarItemById($id) : null;

$pageTitle = ($sidebarItem ? 'แก้ไข Sidebar: ' . htmlspecialchars($sidebarItem['title']) : 'เพิ่มรายการ Sidebar ใหม่') . ' — ' . siteSetting('site_name');
$extraHead = '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/github-dark.min.css">' . "\n"
    . '<link rel="stylesheet" href="assets/article.css?v=' . @filemtime(__DIR__ . '/assets/article.css') . '">' . "\n"
    . '<link rel="stylesheet" href="assets/editor.css?v=' . @filemtime(__DIR__ . '/assets/editor.css') . '">';
$topbarActions = adminTopbarActions(['<a href="sidebar-items.php">รายการ Sidebar</a>']);
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
</script>
<?php
$footerScripts = ob_get_clean();

include __DIR__ . '/partials/header.php';
?>
  <div class="field">
    <label for="title">ชื่อรายการ (ไว้อ้างอิงในหน้าจัดการ — ไม่ได้แสดงบนเว็บ)</label>
    <input type="text" id="title" value="<?= $sidebarItem ? htmlspecialchars($sidebarItem['title']) : '' ?>" placeholder="เช่น แบนเนอร์คอร์สลงทุน">
  </div>
  <div class="field">
    <label>รูป (ไม่บังคับ — ถ้าไม่ใส่จะแสดงเป็นข้อความล้วนๆ)</label>
    <div id="featured-image-preview" class="featured-image-preview" style="display:<?= !empty($sidebarItem['image']) ? 'flex' : 'none' ?>;">
      <img id="featured-image-thumb" src="<?= htmlspecialchars($sidebarItem['image'] ?? '') ?>" alt="">
      <button type="button" class="btn btn-secondary" id="remove-featured-image-btn">ลบรูป</button>
    </div>
    <input type="file" id="featured-image-input" accept="image/*" style="display:<?= !empty($sidebarItem['image']) ? 'none' : 'block' ?>;">
    <input type="hidden" id="featured-image" value="<?= htmlspecialchars($sidebarItem['image'] ?? '') ?>">
  </div>
  <div class="field">
    <label for="link-url">ลิงก์ปลายทาง (ไม่บังคับ — คลิกที่การ์ดนี้แล้วไปที่ไหน เช่น https://... หรือ category.php?slug=...)</label>
    <input type="text" id="link-url" value="<?= htmlspecialchars($sidebarItem['link_url'] ?? '') ?>" placeholder="https://...">
  </div>
  <div class="field">
    <label style="display:flex; align-items:center; gap:6px;"><input type="checkbox" id="is-active" <?= (!$sidebarItem || $sidebarItem['is_active']) ? 'checked' : '' ?>> แสดงรายการนี้</label>
  </div>
  <input type="hidden" id="item-id" value="<?= $sidebarItem['id'] ?? '' ?>">

  <div id="editor-container"></div>

  <div class="toolbar-row">
    <button class="btn" id="save-btn">บันทึก</button>
    <span id="save-status"></span>
  </div>
<?php include __DIR__ . '/partials/footer.php'; ?>
