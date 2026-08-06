<?php
require __DIR__ . '/includes/articles.php';
require __DIR__ . '/includes/admin-nav.php';
require_once __DIR__ . '/includes/orphan-files.php';
require __DIR__ . '/includes/backup.php'; // formatBackupSize() — generic despite the name

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_selected') {
    $paths = $_POST['paths'] ?? [];
    $deleted = deleteOrphanFiles(is_array($paths) ? $paths : []);

    header('Location: orphan-files.php?done=1&deleted=' . $deleted);
    exit;
}

$candidates = scanOrphanUploads();

$pageTitle = 'ไฟล์กำพร้า';
$topbarActions = adminTopbarActions();
$showAdminSidebar = true;
$layout = render_header(compact('pageTitle', 'topbarActions', 'showAdminSidebar'));
?>
  <h1 class="article-title">ไฟล์กำพร้า</h1>

  <?php if (isset($_GET['done'])): ?>
    <div class="settings-notice settings-notice-success">ลบไฟล์ที่เลือกแล้ว (<?= (int) ($_GET['deleted'] ?? 0) ?> ไฟล์)</div>
  <?php endif; ?>

  <div class="card">
    <p style="color:var(--text-muted); margin-top:0;">
      ไฟล์ใน <code>uploads/</code> ที่ไม่มีบทความ, sidebar item, หรือโลโก้/favicon เว็บอ้างอิงถึงอีกแล้ว
      (เช่น รูปที่เคยฝังในเนื้อหาบทความแล้วถูกลบออกจากเนื้อหาทีหลัง) — ตรวจสอบก่อนติ๊กเลือกแล้วลบเสมอ ลบแล้วกู้คืนไม่ได้
    </p>
    <?php if (!$candidates): ?>
      <p style="color:var(--text-muted);">ไม่พบไฟล์กำพร้า</p>
    <?php else: ?>
      <form method="post" onsubmit="return confirm('ลบไฟล์ที่เลือก — กู้คืนไม่ได้อีก ยืนยันลบ?');">
        <input type="hidden" name="action" value="delete_selected">
        <div class="table-scroll">
          <table class="admin-table">
            <thead>
              <tr>
                <th><input type="checkbox" id="select-all"></th>
                <th></th>
                <th>ไฟล์</th>
                <th>ขนาด</th>
                <th>วันที่สร้าง</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($candidates as $file): ?>
                <tr>
                  <td><input type="checkbox" name="paths[]" value="<?= htmlspecialchars($file['relative_path']) ?>" class="row-select"></td>
                  <td>
                    <?php if ($file['is_image']): ?>
                      <img src="<?= htmlspecialchars($file['relative_path']) ?>" alt="" style="width:48px; height:48px; object-fit:cover; border-radius:4px;">
                    <?php endif; ?>
                  </td>
                  <td style="word-break:break-all;"><?= htmlspecialchars($file['relative_path']) ?></td>
                  <td><?= formatBackupSize($file['size']) ?></td>
                  <td><?= relativeTimeTag($file['created_at']) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <button type="submit" class="btn" style="margin-top:1rem;">ลบไฟล์ที่เลือก</button>
      </form>
    <?php endif; ?>
  </div>
<script src="assets/manage-list.js<?= '?v=' . @filemtime(__DIR__ . '/assets/manage-list.js') ?>" defer></script>
<?php render_sidebar($layout); render_footer(); ?>
