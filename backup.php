<?php
require __DIR__ . '/includes/articles.php';
require __DIR__ . '/includes/admin-nav.php';
require __DIR__ . '/includes/backup.php';

// Streams the zip directly, before any HTML output — same early-exit shape
// as the POST branch below.
if (isset($_GET['download'])) {
    streamBackupDownload((string) $_GET['download']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        createBackupArchive();
        pruneOldBackups();

        header('Location: backup.php?done=1');
        exit;
    }

    if ($action === 'delete') {
        deleteBackupFile((string) ($_POST['filename'] ?? ''));

        header('Location: backup.php?deleted=1');
        exit;
    }
}

$backups = listBackupFiles();

$pageTitle = 'Backup';
$topbarActions = adminTopbarActions();
$showAdminSidebar = true;
$layout = render_header(compact('pageTitle', 'topbarActions', 'showAdminSidebar'));
?>
  <h1 class="article-title">Backup</h1>

  <?php if (isset($_GET['done'])): ?>
    <div class="settings-notice settings-notice-success">สร้าง backup เรียบร้อยแล้ว</div>
  <?php endif; ?>
  <?php if (isset($_GET['deleted'])): ?>
    <div class="settings-notice settings-notice-success">ลบ backup เรียบร้อยแล้ว</div>
  <?php endif; ?>

  <div class="card">
    <p style="color:var(--text-muted); margin-top:0;">
      บีบอัดฐานข้อมูลทั้งหมด (ยกเว้นสถิติ pageview) และไฟล์ที่อัปโหลดไว้ทั้งหมด
      ลงไฟล์ zip ชุดเดียว — ดาวน์โหลดเก็บไว้ที่อื่นเองด้วยหลังจากสร้างแล้ว
      เพื่อไม่ให้ backup อยู่บนเซิร์ฟเวอร์เดียวกับเว็บเพียงที่เดียว
    </p>
    <form method="post">
      <input type="hidden" name="action" value="create">
      <button type="submit" class="btn">Backup ตอนนี้</button>
    </form>
  </div>

  <div class="card">
    <h2 class="admin-section-title" style="margin-top:0;">Backup ที่มีอยู่</h2>
    <?php if (!$backups): ?>
      <p style="color:var(--text-muted);">ยังไม่มี backup</p>
    <?php else: ?>
      <div class="table-scroll">
        <table class="admin-table">
          <thead><tr><th>ไฟล์</th><th>ขนาด</th><th>สร้างเมื่อ</th><th></th></tr></thead>
          <tbody>
            <?php foreach ($backups as $backup): ?>
              <tr>
                <td><?= htmlspecialchars($backup['filename']) ?></td>
                <td><?= formatBackupSize($backup['size']) ?></td>
                <td><?= relativeTimeTag($backup['created_at']) ?></td>
                <td class="row-actions">
                  <a href="backup.php?download=<?= urlencode($backup['filename']) ?>">ดาวน์โหลด</a>
                  <form method="post" style="display:inline" onsubmit="return confirm('ลบ backup นี้?');">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="filename" value="<?= htmlspecialchars($backup['filename']) ?>">
                    <button type="submit" class="link-danger">ลบ</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
<?php render_sidebar($layout); render_footer(); ?>
