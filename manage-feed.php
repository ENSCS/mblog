<?php
require __DIR__ . '/includes/articles.php';
require __DIR__ . '/includes/feed.php';
require __DIR__ . '/includes/admin-nav.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $content = trim($_POST['content'] ?? '');
        if ($content !== '') {
            createFeedItem($content);
        }
    } elseif ($action === 'update') {
        $content = trim($_POST['content'] ?? '');
        if ($content !== '') {
            updateFeedItem((int) ($_POST['id'] ?? 0), $content);
        }
    } elseif ($action === 'delete') {
        deleteFeedItem((int) ($_POST['id'] ?? 0));
    }

    header('Location: manage-feed.php?done=1');
    exit;
}

$items = getFeedItems((int) siteSetting('feed_item_limit', 50));
$editId = (int) ($_GET['edit_id'] ?? 0);
// The same textarea at the top serves both "post new" and "edit existing" —
// clicking "แก้ไข" on a row just reloads this page with ?edit_id=, which
// pre-fills that box and swaps its button/label instead of opening a
// separate inline form inside the table row.
$editItem = $editId ? getFeedItemById($editId) : null;

$pageTitle = 'จัดการฟีดข่าว';
$topbarActions = adminTopbarActions();
$showAdminSidebar = true;
$layout = render_header(compact('pageTitle', 'topbarActions', 'showAdminSidebar'));
?>
  <h1 class="article-title">จัดการฟีดข่าว</h1>

  <?php if (isset($_GET['done'])): ?>
    <div class="settings-notice settings-notice-success">ดำเนินการเรียบร้อยแล้ว</div>
  <?php endif; ?>

  <div class="card">
    <form method="post">
      <input type="hidden" name="action" value="<?= $editItem ? 'update' : 'create' ?>">
      <?php if ($editItem): ?>
        <input type="hidden" name="id" value="<?= (int) $editItem['id'] ?>">
      <?php endif; ?>
      <div class="field">
        <label for="content"><?= $editItem ? 'แก้ไขข้อความ' : 'ข้อความใหม่' ?></label>
        <textarea id="content" name="content" rows="3" placeholder="เช่น HMPRO ล่าสุด 1,594.18 ปีก่อน 1,398.55 (+13.99%)" required><?= $editItem ? htmlspecialchars($editItem['content']) : '' ?></textarea>
      </div>
      <button type="submit" class="btn"><?= $editItem ? 'บันทึก' : 'โพสต์' ?></button>
      <?php if ($editItem): ?>
        <a href="manage-feed.php" class="btn btn-secondary">ยกเลิก</a>
      <?php endif; ?>
    </form>
  </div>

  <div class="card">
    <h2 class="admin-section-title" style="margin-top:0;">ข้อความล่าสุด</h2>
    <?php if (!$items): ?>
      <p style="color:var(--text-muted);">ยังไม่มีข้อความ</p>
    <?php else: ?>
      <div class="table-scroll">
        <table class="admin-table">
          <thead><tr><th>ข้อความ</th><th>เวลา</th><th></th></tr></thead>
          <tbody>
            <?php foreach ($items as $item): ?>
              <tr>
                <td style="white-space:normal; max-width:500px;"><?= nl2br(htmlspecialchars($item['content'])) ?></td>
                <td><?= relativeTimeTag($item['created_at']) ?></td>
                <td class="row-actions">
                  <a href="manage-feed.php?edit_id=<?= (int) $item['id'] ?>">แก้ไข</a>
                  <form method="post" style="display:inline" onsubmit="return confirm('ลบข้อความนี้?');">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
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
