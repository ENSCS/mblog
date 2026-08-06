<?php
require __DIR__ . '/includes/articles.php';
require __DIR__ . '/includes/users.php';
require __DIR__ . '/includes/admin-nav.php';
requireCapability('manage_users');

$tierLabels = ['free' => 'Free', 'paid' => 'Paid', 'premium' => 'Premium'];
$tierColors = ['free' => 'gray', 'paid' => 'blue', 'premium' => 'purple'];

$search = trim($_GET['search'] ?? '');
$perPage = 25;
$page = max(1, (int) ($_GET['page'] ?? 1));
// Every action form posts back to this same search+page, same pattern as
// includes/manage-list.php's $actionTarget — so after processing, the admin
// lands back on the filtered/paginated view they were looking at, not a
// blank list reset to page 1.
$currentQuery = $_SERVER['QUERY_STRING'] ?? '';
$actionTarget = 'users.php' . ($currentQuery !== '' ? '?' . $currentQuery : '');

// Same current-GET-params-plus-override builder as includes/manage-list.php,
// used by the pagination links below to keep ?search= attached when flipping
// pages.
$buildUrl = function (array $overrides) {
    $params = array_merge($_GET, $overrides);
    foreach ($params as $key => $value) {
        if ($value === '' || $value === null) {
            unset($params[$key]);
        }
    }
    return 'users.php' . (count($params) ? '?' . http_build_query($params) : '');
};

$errors = [];
$saved = isset($_GET['saved']);
$deleted = isset($_GET['deleted']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'update_tier') {
        $id = (int) ($_POST['id'] ?? 0);
        $tier = $_POST['tier'] ?? '';
        if (!array_key_exists($tier, $tierLabels)) {
            $errors[] = 'สิทธิ์ที่เลือกไม่ถูกต้อง';
        } elseif ($id > 0) {
            updateUserTier($id, $tier);
            header('Location: ' . $actionTarget . ($currentQuery !== '' ? '&' : '?') . 'saved=1');
            exit;
        }
    } elseif ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            deleteUser($id);
            header('Location: ' . $actionTarget . ($currentQuery !== '' ? '&' : '?') . 'deleted=1');
            exit;
        }
    } elseif ($action === 'bulk') {
        $ids = array_map('intval', $_POST['ids'] ?? []);
        $bulkAction = $_POST['bulk_action'] ?? '';
        if ($bulkAction === 'delete') {
            bulkDeleteUsers($ids);
            header('Location: ' . $actionTarget . ($currentQuery !== '' ? '&' : '?') . 'deleted=1');
            exit;
        } elseif (in_array($bulkAction, ['set_free', 'set_paid', 'set_premium'], true)) {
            bulkUpdateUserTier($ids, substr($bulkAction, 4));
            header('Location: ' . $actionTarget . ($currentQuery !== '' ? '&' : '?') . 'saved=1');
            exit;
        }
    }
}

$result = getUsersForAdmin($search, $page, $perPage);
$users = $result['items'];
$totalPages = max(1, (int) ceil($result['total'] / $perPage));

$pageTitle = 'จัดการผู้ใช้';
$showAdminSidebar = true;
$footerScripts = '<script src="assets/manage-list.js' . '?v=' . @filemtime(__DIR__ . '/assets/manage-list.js') . '" defer></script>';
$layout = render_header(compact('pageTitle', 'showAdminSidebar'));
?>
  <h1 class="article-title">จัดการผู้ใช้</h1>
  <p style="color:var(--text-muted); margin-top:-8px;">
    บัญชีผู้ใช้ทั่วไป — สมัครเองผ่านหน้า <a href="register.php">สมัครสมาชิก</a> คนละกลุ่มกับทีมงานเว็บ
    (<a href="staff.php">จัดการทีมงาน</a>) เริ่มต้นทุกบัญชีเป็น Free เสมอ ปรับเป็น Paid/Premium ได้จากตารางนี้
  </p>

  <div class="card">
    <?php if ($saved): ?><div class="settings-notice settings-notice-success">บันทึกแล้ว</div><?php endif; ?>
    <?php if ($deleted): ?><div class="settings-notice settings-notice-success">ลบผู้ใช้แล้ว</div><?php endif; ?>
    <?php if ($errors): ?>
      <div class="settings-notice settings-notice-error">
        <?php foreach ($errors as $error): ?><div><?= htmlspecialchars($error) ?></div><?php endforeach; ?>
      </div>
    <?php endif; ?>

    <?php if (empty($users)): ?>
      <form method="get" class="filter-bar">
        <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="ค้นหา User ID, ชื่อ, นามสกุล, เบอร์โทร, อีเมล...">
        <button type="submit" class="btn btn-secondary">ค้นหา</button>
        <?php if ($search !== ''): ?>
          <a href="users.php">ล้างการค้นหา</a>
        <?php endif; ?>
      </form>
      <div class="empty-state"><?= $search !== '' ? 'ไม่พบผู้ใช้ที่ตรงกับ "' . htmlspecialchars($search) . '"' : 'ยังไม่มีผู้ใช้สมัครสมาชิก' ?></div>
    <?php else: ?>
      <?php
      // Sibling forms, not nested — checkboxes live in the table but attach
      // to #bulk-form via form="bulk-form" (same split as includes/manage-list.php).
      // The two forms sit in a shared flex row purely for layout — search
      // (GET) and bulk actions (POST) stay functionally independent forms.
      ?>
      <div class="toolbar-row">
        <form method="get" class="filter-bar">
          <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="ค้นหา User ID, ชื่อ, นามสกุล, เบอร์โทร, อีเมล...">
          <button type="submit" class="btn btn-secondary">ค้นหา</button>
          <?php if ($search !== ''): ?>
            <a href="users.php">ล้างการค้นหา</a>
          <?php endif; ?>
        </form>

        <form method="post" action="<?= htmlspecialchars($actionTarget) ?>" id="bulk-form" class="bulk-bar">
          <?= csrfField() ?>
          <input type="hidden" name="action" value="bulk">
          <select name="bulk_action" id="bulk-action-select">
            <option value="">การดำเนินการเป็นชุด</option>
            <option value="set_free">ตั้งเป็น Free</option>
            <option value="set_paid">ตั้งเป็น Paid</option>
            <option value="set_premium">ตั้งเป็น Premium</option>
            <option value="delete">ลบ</option>
          </select>
          <button type="submit" class="btn btn-secondary">นำไปใช้</button>
        </form>
      </div>

      <div class="table-scroll">
        <table class="admin-table">
          <thead>
            <tr>
              <th><input type="checkbox" id="select-all"></th>
              <th>User ID</th>
              <th>ชื่อ</th>
              <th>Username</th>
              <th>อีเมล</th>
              <th>เบอร์โทร</th>
              <th>LINE ID</th>
              <th>สิทธิ์</th>
              <th>สมัครเมื่อ</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($users as $u): ?>
              <tr>
                <td><input type="checkbox" name="ids[]" value="<?= (int) $u['id'] ?>" class="row-select" form="bulk-form"></td>
                <td><a href="user-profile.php?id=<?= (int) $u['id'] ?>"><?= (int) $u['id'] ?></a></td>
                <td><a href="user-profile.php?id=<?= (int) $u['id'] ?>"><?= htmlspecialchars(trim($u['first_name'] . ' ' . $u['last_name'])) ?></a></td>
                <td><?= htmlspecialchars($u['username']) ?></td>
                <td><?= htmlspecialchars($u['email']) ?></td>
                <td><?= htmlspecialchars($u['phone'] ?? '-') ?></td>
                <td><?= htmlspecialchars($u['line_id'] ?? '-') ?></td>
                <td>
                  <form method="post" action="<?= htmlspecialchars($actionTarget) ?>" style="display:inline-flex; align-items:center; gap:6px;">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="update_tier">
                    <input type="hidden" name="id" value="<?= $u['id'] ?>">
                    <span class="category-tag category-tag-<?= $tierColors[$u['tier']] ?? 'gray' ?>"><?= htmlspecialchars($tierLabels[$u['tier']] ?? $u['tier']) ?></span>
                    <select name="tier" onchange="this.form.submit()">
                      <?php foreach ($tierLabels as $value => $label): ?>
                        <option value="<?= htmlspecialchars($value) ?>" <?= $value === $u['tier'] ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                      <?php endforeach; ?>
                    </select>
                  </form>
                </td>
                <td><?= relativeTimeTag($u['created_at']) ?></td>
                <td class="row-actions">
                  <form method="post" action="<?= htmlspecialchars($actionTarget) ?>" style="display:inline" onsubmit="return confirm('ยืนยันลบผู้ใช้ &quot;<?= htmlspecialchars($u['email'], ENT_QUOTES) ?>&quot;?');">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= $u['id'] ?>">
                    <button type="submit" class="link-danger">ลบ</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>

    <?php if ($totalPages > 1): ?>
      <div class="pagination">
        <?php if ($page > 1): ?><a href="<?= htmlspecialchars($buildUrl(['page' => $page - 1])) ?>">&laquo; ก่อนหน้า</a><?php endif; ?>
        <span>หน้า <?= $page ?> จาก <?= $totalPages ?></span>
        <?php if ($page < $totalPages): ?><a href="<?= htmlspecialchars($buildUrl(['page' => $page + 1])) ?>">ถัดไป &raquo;</a><?php endif; ?>
      </div>
    <?php endif; ?>
  </div>
<?php render_sidebar($layout); render_footer(compact('footerScripts')); ?>
