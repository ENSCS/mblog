<?php
require __DIR__ . '/includes/articles.php';
require __DIR__ . '/includes/staff.php';
require __DIR__ . '/includes/admin-nav.php';
requireCapability('manage_users');

$roleLabels = ['admin' => 'Admin', 'editor' => 'Editor', 'author' => 'Author'];
$roleColors = ['admin' => 'purple', 'editor' => 'blue', 'author' => 'green'];

$errors = [];
$saved = isset($_GET['saved']);
$deleted = isset($_GET['deleted']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    verifyCsrf();
    $id = (int) ($_POST['id'] ?? 0);
    // Can't delete your own account (would lock the session out mid-request
    // with no recovery) or the last remaining admin (would lock everyone
    // out — no self-service "become admin" path exists once that happens).
    if ($id === (int) currentStaff()['id']) {
        $errors[] = 'ลบบัญชีตัวเองไม่ได้';
    } else {
        $target = getStaffById($id);
        // Intentional exception to the capability-based rule (ROLE_CAPABILITIES,
        // includes/auth.php) — this asks "would zero admins be left", not
        // "can this staff member do X", so staffCan() has no way to express
        // it. Checks the literal 'admin' string on purpose; if the role
        // structure ever changes (renamed, or '*' moved to a different
        // role), this needs a manual update too — same exception as
        // profile.php's self-demote guard.
        $adminCount = count(array_filter(getAllStaff(), fn($s) => $s['role'] === 'admin'));
        if ($target && $target['role'] === 'admin' && $adminCount <= 1) {
            $errors[] = 'ลบไม่ได้ — ต้องมี admin เหลืออย่างน้อย 1 คนเสมอ';
        } elseif ($id > 0) {
            deleteStaff($id);
            header('Location: staff.php?deleted=1');
            exit;
        }
    }
}

$form = ['email' => ''];

// Just email + password here — everything else (name, username, phone, LINE
// ID, avatar, role) is set afterward on profile.php, same page used to edit
// any existing account. Keeps "add someone" a 2-field action instead of the
// full profile form up front. Role defaults to 'author' (createStaff()'s own
// default) — least-privilege until an admin deliberately promotes them.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create') {
    verifyCsrf();
    $form['email'] = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($form['email'] === '' || !filter_var($form['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'กรุณาใส่อีเมลที่ถูกต้อง';
    } elseif (staffEmailExists($form['email'])) {
        $errors[] = 'อีเมลนี้มีผู้ใช้อยู่แล้ว';
    }
    if (strlen($password) < 8) {
        $errors[] = 'รหัสผ่านต้องมีอย่างน้อย 8 ตัวอักษร';
    }

    if (!$errors) {
        $username = generateStaffUsernameFromEmail($form['email']);
        createStaff($form['email'], $username, $password);
        header('Location: staff.php?saved=1');
        exit;
    }
}

$staffList = getAllStaff();

$pageTitle = 'จัดการทีมงาน';
$showAdminSidebar = true;
$layout = render_header(compact('pageTitle', 'showAdminSidebar'));
?>
  <h1 class="article-title">จัดการทีมงาน</h1>
  <p style="color:var(--text-muted); margin-top:-8px;">
    บัญชีทีมงานเว็บ (admin/editor/author) — คนละกลุ่มกับผู้ใช้ทั่วไป ไม่มีระบบสมัครเอง
    ต้องสร้างให้จากหน้านี้เท่านั้น — admin แก้ไข/เผยแพร่บทความได้ทุกคน editor เหมือนกันแต่เข้าตั้งค่าเว็บไม่ได้ author แก้ได้แค่บทความตัวเอง
    เข้าสู่ระบบได้ทั้งด้วยอีเมลหรือ username — แก้ไขโปรไฟล์ (ชื่อ, username, สิทธิ์, รูป ฯลฯ) ได้ที่หน้าโปรไฟล์ของแต่ละคน
  </p>

  <div class="card">
    <?php if ($saved): ?><div class="settings-notice settings-notice-success">บันทึกแล้ว</div><?php endif; ?>
    <?php if ($deleted): ?><div class="settings-notice settings-notice-success">ลบทีมงานแล้ว</div><?php endif; ?>
    <?php if ($errors): ?>
      <div class="settings-notice settings-notice-error">
        <?php foreach ($errors as $error): ?><div><?= htmlspecialchars($error) ?></div><?php endforeach; ?>
      </div>
    <?php endif; ?>

    <?php if (empty($staffList)): ?>
      <div class="empty-state">ยังไม่มีทีมงาน</div>
    <?php else: ?>
      <div class="table-scroll">
        <table class="admin-table">
          <thead><tr><th></th><th>ชื่อ</th><th>Username</th><th>อีเมล</th><th>สิทธิ์</th><th>สร้างเมื่อ</th><th></th></tr></thead>
          <tbody>
            <?php foreach ($staffList as $s): ?>
              <tr>
                <td>
                  <?php if ($s['avatar_path']): ?>
                    <img src="<?= htmlspecialchars($s['avatar_path']) ?>" alt="" class="avatar-thumb">
                  <?php else: ?>
                    <span class="avatar-thumb avatar-thumb-placeholder <?= avatarColorClass($s['id']) ?>"><?= htmlspecialchars(avatarInitial($s)) ?></span>
                  <?php endif; ?>
                </td>
                <td><?= htmlspecialchars(userDisplayName($s)) ?><?= (int) $s['id'] === (int) currentStaff()['id'] ? ' (คุณ)' : '' ?></td>
                <td><?= htmlspecialchars($s['username']) ?></td>
                <td><?= htmlspecialchars($s['email']) ?></td>
                <td><span class="category-tag category-tag-<?= $roleColors[$s['role']] ?? 'gray' ?>"><?= htmlspecialchars($roleLabels[$s['role']] ?? $s['role']) ?></span></td>
                <td><?= relativeTimeTag($s['created_at']) ?></td>
                <td class="row-actions">
                  <a href="profile.php?id=<?= $s['id'] ?>">แก้ไข</a>
                  <?php if ((int) $s['id'] !== (int) currentStaff()['id']): ?>
                    <form method="post" style="display:inline" onsubmit="return confirm('ยืนยันลบทีมงาน &quot;<?= htmlspecialchars($s['email'], ENT_QUOTES) ?>&quot;?');">
                      <?= csrfField() ?>
                      <input type="hidden" name="action" value="delete">
                      <input type="hidden" name="id" value="<?= $s['id'] ?>">
                      <button type="submit" class="link-danger">ลบ</button>
                    </form>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

  <div class="card">
    <h2 style="margin-top:0;">เพิ่มทีมงานใหม่</h2>
    <form method="post">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="create">
      <div class="field">
        <label for="email">อีเมล</label>
        <input type="email" id="email" name="email" value="<?= htmlspecialchars($form['email']) ?>">
      </div>
      <div class="field">
        <label for="password">รหัสผ่าน</label>
        <input type="password" id="password" name="password" autocomplete="new-password">
      </div>
      <button type="submit" class="btn">เพิ่มทีมงาน</button>
    </form>
  </div>
<?php render_sidebar($layout); render_footer(); ?>
