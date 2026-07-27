<?php
require __DIR__ . '/includes/menu.php';
require __DIR__ . '/includes/admin-nav.php';

$errors = [];
$saved = isset($_GET['saved']);
$deleted = isset($_GET['deleted']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $id = (int) ($_POST['id'] ?? 0);
    if ($id > 0) {
        deleteMenuItem($id);
    }
    header('Location: menu.php?deleted=1');
    exit;
}

$editId = isset($_GET['edit']) ? (int) $_GET['edit'] : null;
$editingItem = $editId ? getMenuItemById($editId) : null;

$form = [
    'id' => $editingItem['id'] ?? '',
    'label' => $editingItem['label'] ?? '',
    'href' => $editingItem['href'] ?? '',
    'parent_id' => $editingItem['parent_id'] ?? '',
    'sort_order' => $editingItem['sort_order'] ?? '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save') {
    $form['id'] = trim($_POST['id'] ?? '');
    $form['label'] = trim($_POST['label'] ?? '');
    $form['href'] = trim($_POST['href'] ?? '');
    $form['parent_id'] = trim($_POST['parent_id'] ?? '');
    $form['sort_order'] = trim($_POST['sort_order'] ?? '');

    $id = $form['id'] !== '' ? (int) $form['id'] : null;
    $parentId = $form['parent_id'] !== '' ? (int) $form['parent_id'] : null;

    if ($form['label'] === '') {
        $errors[] = 'กรุณาใส่ชื่อเมนู';
    }
    if ($form['href'] === '') {
        $errors[] = 'กรุณาใส่ลิงก์';
    }
    if ($parentId !== null) {
        // PDO returns id as a string, not an int — cast so the strict
        // in_array() below compares like types instead of always failing.
        $topLevelIds = array_map('intval', array_column(getTopLevelMenuItems(), 'id'));
        if (!in_array($parentId, $topLevelIds, true)) {
            $errors[] = 'เมนูหลักที่เลือกไม่ถูกต้อง';
        } elseif ($id !== null && $parentId === $id) {
            $errors[] = 'เมนูไม่สามารถเป็นเมนูย่อยของตัวเองได้';
        }
    }

    if (!$errors) {
        $sortOrder = $form['sort_order'] !== '' ? (int) $form['sort_order'] : nextMenuSortOrder($parentId);
        if ($id !== null) {
            updateMenuItem($id, $parentId, $form['label'], $form['href'], $sortOrder);
        } else {
            createMenuItem($parentId, $form['label'], $form['href'], $sortOrder);
        }
        header('Location: menu.php?saved=1');
        exit;
    }
}

$allItems = getAllMenuItems();
$topLevelItems = getTopLevelMenuItems();
$topItems = array_values(array_filter($allItems, fn($item) => $item['parent_id'] === null));
$childrenByParent = [];
foreach ($allItems as $item) {
    if ($item['parent_id'] !== null) {
        $childrenByParent[$item['parent_id']][] = $item;
    }
}

$pageTitle = 'จัดการเมนู';
$topbarActions = adminTopbarActions(['<a href="editor.php">+ เขียนบทความใหม่</a>']);
$showAdminSidebar = true;
include __DIR__ . '/partials/header.php';
?>
  <h1 class="article-title">จัดการเมนู</h1>

  <div class="card">
    <?php if ($saved): ?><div class="settings-notice settings-notice-success">บันทึกเมนูแล้ว</div><?php endif; ?>
    <?php if ($deleted): ?><div class="settings-notice settings-notice-success">ลบเมนูแล้ว</div><?php endif; ?>
    <?php if ($errors): ?>
      <div class="settings-notice settings-notice-error">
        <?php foreach ($errors as $error): ?><div><?= htmlspecialchars($error) ?></div><?php endforeach; ?>
      </div>
    <?php endif; ?>

    <?php if (empty($topItems)): ?>
      <div class="empty-state">ยังไม่มีเมนู</div>
    <?php else: ?>
      <div class="table-scroll">
        <table class="admin-table">
          <thead>
            <tr><th>ชื่อเมนู</th><th>ลิงก์</th><th>ลำดับ</th><th></th></tr>
          </thead>
          <tbody>
            <?php foreach ($topItems as $item): ?>
              <?php $childCount = count($childrenByParent[$item['id']] ?? []); ?>
              <tr>
                <td><?= htmlspecialchars($item['label']) ?></td>
                <td><code><?= htmlspecialchars($item['href']) ?></code></td>
                <td><?= (int) $item['sort_order'] ?></td>
                <td class="row-actions">
                  <a href="menu.php?edit=<?= $item['id'] ?>">แก้ไข</a>
                  <form method="post" style="display:inline" onsubmit="return confirm(<?= $childCount > 0
                      ? "'ลบ " . htmlspecialchars($item['label'], ENT_QUOTES) . " จะลบเมนูย่อยอีก {$childCount} รายการไปด้วย ยืนยันลบ?'"
                      : "'ยืนยันลบเมนูนี้?'" ?>);">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= $item['id'] ?>">
                    <button type="submit" class="link-danger">ลบ</button>
                  </form>
                </td>
              </tr>
              <?php foreach ($childrenByParent[$item['id']] ?? [] as $child): ?>
                <tr>
                  <td class="admin-table-child">&#8618; <?= htmlspecialchars($child['label']) ?></td>
                  <td><code><?= htmlspecialchars($child['href']) ?></code></td>
                  <td><?= (int) $child['sort_order'] ?></td>
                  <td class="row-actions">
                    <a href="menu.php?edit=<?= $child['id'] ?>">แก้ไข</a>
                    <form method="post" style="display:inline" onsubmit="return confirm('ยืนยันลบเมนูนี้?');">
                      <input type="hidden" name="action" value="delete">
                      <input type="hidden" name="id" value="<?= $child['id'] ?>">
                      <button type="submit" class="link-danger">ลบ</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

  <div class="card">
    <h2 style="margin-top:0;"><?= $editingItem ? 'แก้ไขเมนู' : 'เพิ่มเมนูใหม่' ?></h2>
    <form method="post">
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="id" value="<?= htmlspecialchars((string) $form['id']) ?>">
      <div class="field">
        <label for="label">ชื่อเมนู</label>
        <input type="text" id="label" name="label" value="<?= htmlspecialchars($form['label']) ?>">
      </div>
      <div class="field">
        <label for="href">ลิงก์ (relative path เช่น <code>articles.php</code> หรือ <code>category.php?slug=xxx</code>)</label>
        <input type="text" id="href" name="href" value="<?= htmlspecialchars($form['href']) ?>">
      </div>
      <div class="field">
        <label for="parent_id">เมนูหลัก (ไม่เลือก = เป็นเมนูหลักเอง)</label>
        <select id="parent_id" name="parent_id">
          <option value="">— ไม่มี (เมนูหลัก) —</option>
          <?php foreach ($topLevelItems as $top): ?>
            <?php if ($editingItem && (int) $editingItem['id'] === (int) $top['id']) continue; ?>
            <option value="<?= $top['id'] ?>" <?= (string) $form['parent_id'] === (string) $top['id'] ? 'selected' : '' ?>><?= htmlspecialchars($top['label']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label for="sort_order">ลำดับ (เลขน้อยแสดงก่อน — เว้นว่างไว้ให้ต่อท้ายอัตโนมัติ)</label>
        <input type="number" id="sort_order" name="sort_order" value="<?= htmlspecialchars((string) $form['sort_order']) ?>" style="max-width:100px;">
      </div>
      <button type="submit" class="btn"><?= $editingItem ? 'บันทึกการแก้ไข' : 'เพิ่มเมนู' ?></button>
      <?php if ($editingItem): ?>
        <a href="menu.php" class="btn btn-secondary">ยกเลิก</a>
      <?php endif; ?>
    </form>
  </div>
<?php include __DIR__ . '/partials/footer.php'; ?>
