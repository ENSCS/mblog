<?php
require __DIR__ . '/includes/users.php';
requireUserLogin();

// Self-service only — a user editing their own account. Username is
// intentionally not a form field at all (not just disabled) since it
// doubles as a login identifier (see login.php) and isn't meant to change
// after signup, unlike a staff member's (profile.php lets staff rename
// their own). Tier isn't here either — that's an admin-only decision, see
// users.php. Password reset for a user who's actually locked out is a
// separate admin tool (user-profile.php's "ใช้เบอร์โทรเป็นรหัส").
$user = currentUser();

$errors = [];
$saved = isset($_GET['saved']);

$form = [
    'email' => $user['email'],
    'first_name' => $user['first_name'] ?? '',
    'last_name' => $user['last_name'] ?? '',
    'phone' => $user['phone'] ?? '',
    'line_id' => $user['line_id'] ?? '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $form['email'] = trim($_POST['email'] ?? '');
    $form['first_name'] = trim($_POST['first_name'] ?? '');
    $form['last_name'] = trim($_POST['last_name'] ?? '');
    $form['phone'] = trim($_POST['phone'] ?? '');
    $form['line_id'] = trim($_POST['line_id'] ?? '');
    $password = $_POST['password'] ?? '';
    $removeAvatar = !empty($_POST['remove_avatar']);

    if ($form['email'] === '' || !filter_var($form['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'กรุณาใส่อีเมลที่ถูกต้อง';
    } elseif (userEmailExists($form['email'], (int) $user['id'])) {
        $errors[] = 'อีเมลนี้มีผู้ใช้อยู่แล้ว';
    }
    if ($form['first_name'] === '') {
        $errors[] = 'กรุณากรอกชื่อ';
    }
    if ($form['last_name'] === '') {
        $errors[] = 'กรุณากรอกนามสกุล';
    }
    if ($form['phone'] === '') {
        $errors[] = 'กรุณากรอกเบอร์โทร';
    }
    if ($password !== '' && strlen($password) < 8) {
        $errors[] = 'รหัสผ่านใหม่ต้องมีอย่างน้อย 8 ตัวอักษร';
    }

    $profile = [
        'first_name' => $form['first_name'],
        'last_name' => $form['last_name'],
        'phone' => $form['phone'],
        'line_id' => $form['line_id'] !== '' ? $form['line_id'] : null,
    ];

    if (!$errors) {
        try {
            updateUserProfile((int) $user['id'], $form['email'], $profile);
            if ($password !== '') {
                updateUserPassword((int) $user['id'], $password);
            }
            if ($removeAvatar) {
                removeUserAvatar((int) $user['id']);
            }
            saveUserAvatar((int) $user['id']);
            header('Location: my-profile.php?saved=1');
            exit;
        } catch (RuntimeException $e) {
            $errors[] = $e->getMessage();
        }
    }
}

// Re-fetch after a successful save so the avatar/removed-avatar state below
// reflects what just happened, same as profile.php does for staff.
$user = getUserById((int) $user['id']);

$pageTitle = 'โปรไฟล์ของฉัน';
$layout = render_header(compact('pageTitle'));
?>
  <h1 class="article-title"><?= htmlspecialchars($pageTitle) ?></h1>

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
          <?php if ($user['avatar_path']): ?>
            <img src="<?= htmlspecialchars($user['avatar_path']) ?>" alt="" class="site-asset-preview avatar-thumb-lg">
          <?php else: ?>
            <span class="avatar-thumb avatar-thumb-lg avatar-thumb-placeholder <?= avatarColorClass((int) $user['id']) ?>"><?= htmlspecialchars(avatarInitial($user)) ?></span>
          <?php endif; ?>
          <input type="file" id="avatar_file" name="avatar_file" accept=".jpg,.jpeg,.png,.gif,.webp">
          <?php if ($user['avatar_path']): ?>
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
        <label>Username</label>
        <input type="text" value="<?= htmlspecialchars($user['username']) ?>" disabled>
      </div>
      <div class="field">
        <label for="email">อีเมล</label>
        <input type="email" id="email" name="email" value="<?= htmlspecialchars($form['email']) ?>">
      </div>
      <div class="field">
        <label for="phone">เบอร์โทร</label>
        <input type="text" id="phone" name="phone" value="<?= htmlspecialchars($form['phone']) ?>">
      </div>
      <div class="field">
        <label for="line_id">LINE ID (ไม่บังคับ)</label>
        <input type="text" id="line_id" name="line_id" value="<?= htmlspecialchars($form['line_id']) ?>">
      </div>
      <div class="field">
        <label for="password">รหัสผ่านใหม่ (เว้นว่างไว้ถ้าไม่เปลี่ยน)</label>
        <input type="password" id="password" name="password" autocomplete="new-password">
      </div>
      <button type="submit" class="btn">บันทึก</button>
    </form>
  </div>
<?php render_sidebar($layout); render_footer(); ?>
