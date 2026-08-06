<?php
require __DIR__ . '/includes/articles.php';
require __DIR__ . '/includes/staff.php';
require __DIR__ . '/includes/admin-nav.php';
requireLogin();

// No ?id= (or your own id) = editing yourself, open to any logged-in staff
// member. Anyone else's id needs manage_users — same capability staff.php's
// whole list page already requires — so an editor/author can't just type
// ?id=<someone else> in the URL and edit a colleague's account.
$targetId = isset($_GET['id']) ? (int) $_GET['id'] : (int) currentStaff()['id'];
$isSelf = $targetId === (int) currentStaff()['id'];
if (!$isSelf) {
    requireCapability('manage_users');
}

$target = getStaffById($targetId);
if (!$target) {
    renderErrorPage(404, 'ไม่พบทีมงานนี้');
}

$roleLabels = ['admin' => 'Admin', 'editor' => 'Editor', 'author' => 'Author'];
// Only someone with manage_users sees/can change the role field at all —
// editing your own profile without that capability (editor/author) never
// shows it, since role is an access-control decision, not personal profile
// data self-service should touch.
$canEditRole = staffCan('manage_users');

$errors = [];
$saved = isset($_GET['saved']);

$form = [
    'email' => $target['email'],
    'username' => $target['username'],
    'role' => $target['role'],
    'first_name' => $target['first_name'] ?? '',
    'last_name' => $target['last_name'] ?? '',
    'phone' => $target['phone'] ?? '',
    'line_id' => $target['line_id'] ?? '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $form['email'] = trim($_POST['email'] ?? '');
    $form['username'] = trim($_POST['username'] ?? '');
    $form['first_name'] = trim($_POST['first_name'] ?? '');
    $form['last_name'] = trim($_POST['last_name'] ?? '');
    $form['phone'] = trim($_POST['phone'] ?? '');
    $form['line_id'] = trim($_POST['line_id'] ?? '');
    $form['role'] = $canEditRole ? trim($_POST['role'] ?? $target['role']) : $target['role'];
    $password = $_POST['password'] ?? '';
    $removeAvatar = !empty($_POST['remove_avatar']);

    if ($form['email'] === '' || !filter_var($form['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'กรุณาใส่อีเมลที่ถูกต้อง';
    } elseif (staffEmailExists($form['email'], $targetId)) {
        $errors[] = 'อีเมลนี้มีผู้ใช้อยู่แล้ว';
    }
    // Same charset as slugs elsewhere on the site — letters/digits/underscore/
    // hyphen/dot only, no spaces — since this doubles as a login identifier
    // (see login.php) sitting next to email in the same lookup query.
    if ($form['username'] === '' || !preg_match('/^[a-zA-Z0-9_.-]{3,50}$/', $form['username'])) {
        $errors[] = 'Username ต้องมีอย่างน้อย 3 ตัวอักษร ใช้ได้แค่ a-z, 0-9, . _ - เท่านั้น';
    } elseif (staffUsernameExists($form['username'], $targetId)) {
        $errors[] = 'Username นี้มีผู้ใช้อยู่แล้ว';
    }
    if ($canEditRole && !array_key_exists($form['role'], $roleLabels)) {
        $errors[] = 'สิทธิ์ที่เลือกไม่ถูกต้อง';
    }
    if ($password !== '' && strlen($password) < 8) {
        $errors[] = 'รหัสผ่านใหม่ต้องมีอย่างน้อย 8 ตัวอักษร';
    }
    // Demoting yourself away from admin would strand you off the only page
    // that could ever undo it (manage_users needs admin) — same guard
    // staff.php used to enforce inline, now lives here instead.
    // Intentional exception to the capability-based rule (ROLE_CAPABILITIES,
    // includes/auth.php) — checks the literal 'admin' string on purpose,
    // same reasoning as the last-admin delete guard in staff.php: this asks
    // "would this leave admin's capabilities unreachable", not "can this
    // staff member do X", so staffCan() has no way to express it. If the
    // role structure ever changes (renamed, or '*' moved to a different
    // role), this needs a manual update too.
    if ($isSelf && $target['role'] === 'admin' && $canEditRole && $form['role'] !== 'admin') {
        $errors[] = 'เปลี่ยนสิทธิ์ตัวเองออกจาก admin ไม่ได้';
    }

    $profile = [
        'first_name' => $form['first_name'] !== '' ? $form['first_name'] : null,
        'last_name' => $form['last_name'] !== '' ? $form['last_name'] : null,
        'phone' => $form['phone'] !== '' ? $form['phone'] : null,
        'line_id' => $form['line_id'] !== '' ? $form['line_id'] : null,
    ];

    if (!$errors) {
        try {
            updateStaff($targetId, $form['email'], $form['username'], $form['role'], $profile);
            if ($password !== '') {
                updateStaffPassword($targetId, $password);
            }
            if ($removeAvatar) {
                removeStaffAvatar($targetId);
            }
            saveStaffAvatar($targetId);
            $redirect = 'profile.php?saved=1' . ($isSelf ? '' : '&id=' . $targetId);
            header('Location: ' . $redirect);
            exit;
        } catch (RuntimeException $e) {
            $errors[] = $e->getMessage();
        }
    }
}

$pageTitle = $isSelf ? 'โปรไฟล์ของฉัน' : 'แก้ไขโปรไฟล์: ' . $target['email'];
$showAdminSidebar = true;
$layout = render_header(compact('pageTitle', 'showAdminSidebar'));
?>
  <h1 class="article-title"><?= htmlspecialchars($pageTitle) ?></h1>
  <?php if (!$isSelf): ?>
    <p style="color:var(--text-muted); margin-top:-8px;"><a href="staff.php">&larr; กลับไปหน้าจัดการทีมงาน</a></p>
  <?php endif; ?>

  <div class="card">
    <?php if ($saved): ?><div class="settings-notice settings-notice-success">บันทึกแล้ว</div><?php endif; ?>
    <?php if ($errors): ?>
      <div class="settings-notice settings-notice-error">
        <?php foreach ($errors as $error): ?><div><?= htmlspecialchars($error) ?></div><?php endforeach; ?>
      </div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data">
      <?= csrfField() ?>

      <div class="field">
        <label for="avatar_file">รูปโปรไฟล์ (ไม่บังคับ — jpg/png/gif/webp สูงสุด 2MB)</label>
        <div class="site-asset-row">
          <?php if ($target['avatar_path']): ?>
            <img src="<?= htmlspecialchars($target['avatar_path']) ?>" alt="" class="site-asset-preview avatar-thumb-lg">
          <?php else: ?>
            <span class="avatar-thumb avatar-thumb-lg avatar-thumb-placeholder <?= avatarColorClass($target['id']) ?>"><?= htmlspecialchars(avatarInitial($target)) ?></span>
          <?php endif; ?>
          <input type="file" id="avatar_file" name="avatar_file" accept=".jpg,.jpeg,.png,.gif,.webp">
          <?php if ($target['avatar_path']): ?>
            <label><input type="checkbox" name="remove_avatar" value="1"> ลบรูปโปรไฟล์</label>
          <?php endif; ?>
        </div>
      </div>

      <div class="field">
        <label for="first_name">ชื่อ</label>
        <input type="text" id="first_name" name="first_name" value="<?= htmlspecialchars($form['first_name']) ?>">
      </div>
      <div class="field">
        <label for="last_name">นามสกุล</label>
        <input type="text" id="last_name" name="last_name" value="<?= htmlspecialchars($form['last_name']) ?>">
      </div>
      <div class="field">
        <label for="username">Username (ใช้ล็อกอินแทนอีเมลได้)</label>
        <input type="text" id="username" name="username" value="<?= htmlspecialchars($form['username']) ?>">
      </div>
      <div class="field">
        <label for="email">อีเมล</label>
        <input type="email" id="email" name="email" value="<?= htmlspecialchars($form['email']) ?>">
      </div>
      <div class="field">
        <label for="phone">เบอร์โทร (ไม่บังคับ)</label>
        <input type="text" id="phone" name="phone" value="<?= htmlspecialchars($form['phone']) ?>">
      </div>
      <div class="field">
        <label for="line_id">LINE ID (ไม่บังคับ)</label>
        <input type="text" id="line_id" name="line_id" value="<?= htmlspecialchars($form['line_id']) ?>">
      </div>
      <?php if ($canEditRole): ?>
        <div class="field">
          <label for="role">สิทธิ์</label>
          <select id="role" name="role">
            <?php foreach ($roleLabels as $value => $label): ?>
              <option value="<?= htmlspecialchars($value) ?>" <?= $value === $form['role'] ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      <?php endif; ?>
      <div class="field">
        <label for="password">รหัสผ่านใหม่ (เว้นว่างไว้ถ้าไม่เปลี่ยน)</label>
        <input type="password" id="password" name="password" autocomplete="new-password">
      </div>
      <button type="submit" class="btn">บันทึก</button>
    </form>
  </div>
<?php render_sidebar($layout); render_footer(); ?>
