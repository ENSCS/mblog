<?php
require __DIR__ . '/includes/sidebar.php';
require __DIR__ . '/includes/admin-nav.php';

$saved = isset($_GET['saved']);
$deleted = isset($_GET['deleted']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            deleteSidebarItem($id);
        }
        header('Location: sidebar-items.php?deleted=1');
        exit;
    }

    if ($action === 'reorder') {
        // Every row's number input is always present (see the <input form=
        // "reorder-form"> pattern below), so the keys of sort_order[] are the
        // authoritative full set of item ids in this submit — active[] only
        // has a key for rows whose checkbox was checked (unchecked
        // checkboxes don't submit at all).
        foreach ($_POST['sort_order'] ?? [] as $id => $order) {
            $id = (int) $id;
            $isActive = isset($_POST['active'][$id]) ? 1 : 0;
            updateSidebarItemOrder($id, (int) $order, $isActive);
        }
        header('Location: sidebar-items.php?saved=1');
        exit;
    }
}

$items = getAllSidebarItems();

$pageTitle = 'จัดการ Sidebar';
$topbarActions = adminTopbarActions(['<a href="sidebar-item-editor.php">+ เพิ่มรายการใหม่</a>']);
$showAdminSidebar = true;
include __DIR__ . '/partials/header.php';
?>
  <h1 class="article-title">จัดการ Sidebar</h1>
  <div class="card">
    <?php if ($saved): ?><div class="settings-notice settings-notice-success">บันทึกแล้ว</div><?php endif; ?>
    <?php if ($deleted): ?><div class="settings-notice settings-notice-success">ลบแล้ว</div><?php endif; ?>

    <?php if (empty($items)): ?>
      <div class="empty-state">ยังไม่มีรายการ sidebar — <a href="sidebar-item-editor.php">เพิ่มรายการแรก</a></div>
    <?php else: ?>
      <form method="post" id="reorder-form"><input type="hidden" name="action" value="reorder"></form>
      <div class="table-scroll">
        <table class="admin-table">
          <thead>
            <tr><th>รายการ</th><th>แสดง</th><th>ลำดับ</th><th></th></tr>
          </thead>
          <tbody>
            <?php foreach ($items as $item): ?>
              <tr>
                <td>
                  <?php if ($item['image']): ?>
                    <img src="<?= htmlspecialchars($item['image']) ?>" alt="" style="max-width:60px; max-height:40px; object-fit:cover; border-radius:4px; vertical-align:middle; margin-right:8px;">
                  <?php endif; ?>
                  <?= htmlspecialchars($item['title']) ?>
                </td>
                <td><input type="checkbox" name="active[<?= $item['id'] ?>]" value="1" <?= $item['is_active'] ? 'checked' : '' ?> form="reorder-form"></td>
                <td><input type="number" name="sort_order[<?= $item['id'] ?>]" value="<?= (int) $item['sort_order'] ?>" form="reorder-form" style="max-width:80px;"></td>
                <td class="row-actions">
                  <a href="sidebar-item-editor.php?id=<?= $item['id'] ?>">แก้ไข</a>
                  <form method="post" style="display:inline" onsubmit="return confirm('ยืนยันลบ &quot;<?= htmlspecialchars($item['title'], ENT_QUOTES) ?>&quot;?');">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= $item['id'] ?>">
                    <button type="submit" class="link-danger">ลบ</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <button type="submit" form="reorder-form" class="btn" style="margin-top:12px;">บันทึกลำดับ/สถานะ</button>
    <?php endif; ?>
  </div>
<?php include __DIR__ . '/partials/footer.php'; ?>
