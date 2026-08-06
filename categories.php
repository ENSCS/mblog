<?php
require __DIR__ . '/includes/articles.php';
require __DIR__ . '/includes/admin-nav.php';
requireCapability('manage_categories');

// Matches the .category-tag-* classes in assets/components.css — a color
// picker limited to this fixed set (instead of a free hex input) guarantees
// every badge stays legible, same reasoning as the color column's own comment
// in database/phase1_core.sql.
$colorOptions = ['gray' => 'เทา', 'blue' => 'ฟ้า', 'green' => 'เขียว', 'purple' => 'ม่วง',
    'orange' => 'ส้ม', 'pink' => 'ชมพู', 'yellow' => 'เหลือง'];

$errors = [];
$saved = isset($_GET['saved']);
$deleted = isset($_GET['deleted']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    verifyCsrf();
    $id = (int) ($_POST['id'] ?? 0);
    if ($id > 0) {
        deleteCategory($id);
    }
    header('Location: categories.php?deleted=1');
    exit;
}

$editId = isset($_GET['edit']) ? (int) $_GET['edit'] : null;
$editingCategory = $editId ? getCategoryById($editId) : null;

$form = [
    'id' => $editingCategory['id'] ?? '',
    'name' => $editingCategory['name'] ?? '',
    'slug' => $editingCategory['slug'] ?? '',
    'color' => $editingCategory['color'] ?? 'gray',
    'sort_order' => $editingCategory['sort_order'] ?? '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save') {
    verifyCsrf();
    $form['id'] = trim($_POST['id'] ?? '');
    $form['name'] = trim($_POST['name'] ?? '');
    $form['slug'] = trim($_POST['slug'] ?? '');
    $form['color'] = trim($_POST['color'] ?? '');
    $form['sort_order'] = trim($_POST['sort_order'] ?? '');

    $id = $form['id'] !== '' ? (int) $form['id'] : null;
    $slug = sanitizeSlug($form['slug'] !== '' ? $form['slug'] : $form['name']);

    if ($form['name'] === '') {
        $errors[] = 'กรุณาใส่ชื่อหมวดหมู่';
    }
    if ($slug === '') {
        $errors[] = 'สร้าง slug จากชื่อไม่ได้ กรุณาใส่ชื่อ/slug ที่มีตัวอักษรหรือตัวเลข';
    } elseif (categorySlugExists($slug, $id)) {
        $errors[] = 'slug นี้ถูกใช้กับหมวดหมู่อื่นแล้ว — ลองเปลี่ยนชื่อหรือใส่ slug เอง';
    }
    if (!array_key_exists($form['color'], $colorOptions)) {
        $errors[] = 'สีที่เลือกไม่ถูกต้อง';
    }

    if (!$errors) {
        $sortOrder = $form['sort_order'] !== '' ? (int) $form['sort_order'] : nextCategorySortOrder();
        if ($id !== null) {
            updateCategory($id, $slug, $form['name'], $form['color'], $sortOrder);
        } else {
            createCategory($slug, $form['name'], $form['color'], $sortOrder);
        }
        header('Location: categories.php?saved=1');
        exit;
    }
}

$categories = getAllCategories();

$pageTitle = 'จัดการหมวดหมู่';
$showAdminSidebar = true;
$layout = render_header(compact('pageTitle', 'showAdminSidebar'));
?>
  <h1 class="article-title">จัดการหมวดหมู่</h1>

  <div class="card">
    <?php if ($saved): ?><div class="settings-notice settings-notice-success">บันทึกหมวดหมู่แล้ว</div><?php endif; ?>
    <?php if ($deleted): ?><div class="settings-notice settings-notice-success">ลบหมวดหมู่แล้ว</div><?php endif; ?>
    <?php if ($errors): ?>
      <div class="settings-notice settings-notice-error">
        <?php foreach ($errors as $error): ?><div><?= htmlspecialchars($error) ?></div><?php endforeach; ?>
      </div>
    <?php endif; ?>

    <?php if (empty($categories)): ?>
      <div class="empty-state">ยังไม่มีหมวดหมู่</div>
    <?php else: ?>
      <div class="table-scroll">
        <table class="admin-table">
          <thead>
            <tr><th>หมวดหมู่</th><th>Slug</th><th>บทความ</th><th>ลำดับ</th><th></th></tr>
          </thead>
          <tbody>
            <?php foreach ($categories as $cat): ?>
              <?php $articleCount = countArticlesInCategory($cat['id']); ?>
              <tr>
                <td><span class="category-tag category-tag-<?= htmlspecialchars($cat['color']) ?>"><?= htmlspecialchars($cat['name']) ?></span></td>
                <td><code><?= htmlspecialchars($cat['slug']) ?></code></td>
                <td><?= $articleCount ?></td>
                <td><?= (int) $cat['sort_order'] ?></td>
                <td class="row-actions">
                  <a href="categories.php?edit=<?= $cat['id'] ?>">แก้ไข</a>
                  <form method="post" style="display:inline" onsubmit="return confirm(<?= $articleCount > 0
                      ? "'บทความ {$articleCount} ชิ้นใช้หมวดนี้อยู่ — ลบแล้วจะไม่มีหมวดหมู่ (ไม่ลบบทความ) ยืนยันลบ?'"
                      : "'ยืนยันลบหมวดหมู่นี้?'" ?>);">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= $cat['id'] ?>">
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

  <div class="card">
    <h2 style="margin-top:0;"><?= $editingCategory ? 'แก้ไขหมวดหมู่' : 'เพิ่มหมวดหมู่ใหม่' ?></h2>
    <form method="post">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="id" value="<?= htmlspecialchars((string) $form['id']) ?>">
      <div class="field">
        <label for="name">ชื่อหมวดหมู่</label>
        <input type="text" id="name" name="name" value="<?= htmlspecialchars($form['name']) ?>">
      </div>
      <div class="field">
        <label for="slug">Slug (ไม่บังคับ — ไม่ใส่จะสร้างจากชื่อให้อัตโนมัติ)</label>
        <input type="text" id="slug" name="slug" value="<?= htmlspecialchars($form['slug']) ?>" placeholder="เว้นว่างไว้ให้สร้างอัตโนมัติจากชื่อ">
      </div>
      <div class="field">
        <label for="color">สีป้าย</label>
        <select id="color" name="color">
          <?php foreach ($colorOptions as $value => $label): ?>
            <option value="<?= htmlspecialchars($value) ?>" <?= $value === $form['color'] ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label for="sort_order">ลำดับ (เลขน้อยแสดงก่อน — เว้นว่างไว้ให้ต่อท้ายอัตโนมัติ)</label>
        <input type="number" id="sort_order" name="sort_order" value="<?= htmlspecialchars((string) $form['sort_order']) ?>" style="max-width:100px;">
      </div>
      <button type="submit" class="btn"><?= $editingCategory ? 'บันทึกการแก้ไข' : 'เพิ่มหมวดหมู่' ?></button>
      <?php if ($editingCategory): ?>
        <a href="categories.php" class="btn btn-secondary">ยกเลิก</a>
      <?php endif; ?>
    </form>
  </div>
<?php render_sidebar($layout); render_footer(); ?>
