<?php
require __DIR__ . '/config.php';
require __DIR__ . '/includes/users.php';
require __DIR__ . '/includes/staff.php';

$errors = [];
$registered = isset($_GET['registered']);

$form = [
    'first_name' => '',
    'last_name' => '',
    'username' => '',
    'email' => '',
    'phone' => '',
    'line_id' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $form['first_name'] = trim($_POST['first_name'] ?? '');
    $form['last_name'] = trim($_POST['last_name'] ?? '');
    $form['username'] = trim($_POST['username'] ?? '');
    $form['email'] = trim($_POST['email'] ?? '');
    $form['phone'] = trim($_POST['phone'] ?? '');
    $form['line_id'] = trim($_POST['line_id'] ?? '');
    $password = $_POST['password'] ?? '';
    $passwordConfirm = $_POST['password_confirm'] ?? '';

    if ($form['first_name'] === '') {
        $errors[] = 'กรุณากรอกชื่อ';
    }
    if ($form['last_name'] === '') {
        $errors[] = 'กรุณากรอกนามสกุล';
    }
    // Same charset/length rule as staff usernames (includes/staff.php,
    // profile.php) — letters/digits/underscore/hyphen/dot only, no spaces.
    // Checked against BOTH mblog_users and mblog_staff — login.php always
    // checks mblog_staff first and never falls through to mblog_users, so a
    // reader who self-registered a username colliding with an existing
    // staff member's would never be able to log in with it at all.
    if ($form['username'] === '' || !preg_match('/^[a-zA-Z0-9_.-]{3,50}$/', $form['username'])) {
        $errors[] = 'Username ต้องมีอย่างน้อย 3 ตัวอักษร ใช้ได้แค่ a-z, 0-9, . _ - เท่านั้น';
    } elseif (userUsernameExists($form['username']) || staffUsernameExists($form['username'])) {
        $errors[] = 'Username นี้มีผู้ใช้อยู่แล้ว';
    }
    if ($form['email'] === '' || !filter_var($form['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'กรุณาใส่อีเมลที่ถูกต้อง';
    } elseif (userEmailExists($form['email']) || staffEmailExists($form['email'])) {
        $errors[] = 'อีเมลนี้มีผู้ใช้อยู่แล้ว';
    }
    if ($form['phone'] === '') {
        $errors[] = 'กรุณากรอกเบอร์โทร';
    }
    if (strlen($password) < 8) {
        $errors[] = 'รหัสผ่านต้องมีอย่างน้อย 8 ตัวอักษร';
    } elseif ($password !== $passwordConfirm) {
        $errors[] = 'ยืนยันรหัสผ่านไม่ตรงกับรหัสผ่าน';
    }

    if (!$errors) {
        createUser($form['email'], $form['username'], $password, [
            'first_name' => $form['first_name'],
            'last_name' => $form['last_name'],
            'phone' => $form['phone'],
            'line_id' => $form['line_id'] !== '' ? $form['line_id'] : null,
        ]);
        header('Location: register.php?registered=1');
        exit;
    }
}

$pageTitle = 'สมัครสมาชิก';
$layout = render_header(compact('pageTitle'));
?>
  <div class="card" style="max-width:420px; margin:16px auto;">
    <h1 class="article-title" style="font-size:22px;">สมัครสมาชิก</h1>
    <?php if ($registered): ?>
      <div class="settings-notice settings-notice-success">สมัครสมาชิกสำเร็จ</div>
    <?php endif; ?>
    <?php if ($errors): ?>
      <div class="settings-notice settings-notice-error">
        <?php foreach ($errors as $error): ?><div><?= htmlspecialchars($error) ?></div><?php endforeach; ?>
      </div>
    <?php endif; ?>
    <form method="post">
      <?= csrfField() ?>
      <div class="field">
        <label for="first_name">ชื่อ <span style="color:var(--error-text);">*</span></label>
        <input type="text" id="first_name" name="first_name" required value="<?= htmlspecialchars($form['first_name']) ?>">
      </div>
      <div class="field">
        <label for="last_name">นามสกุล <span style="color:var(--error-text);">*</span></label>
        <input type="text" id="last_name" name="last_name" required value="<?= htmlspecialchars($form['last_name']) ?>">
      </div>
      <div class="field">
        <label for="username">Username <span style="color:var(--error-text);">*</span></label>
        <input type="text" id="username" name="username" required value="<?= htmlspecialchars($form['username']) ?>">
      </div>
      <div class="field">
        <label for="email">อีเมล <span style="color:var(--error-text);">*</span></label>
        <input type="email" id="email" name="email" required value="<?= htmlspecialchars($form['email']) ?>">
      </div>
      <div class="field">
        <label for="phone">เบอร์โทร <span style="color:var(--error-text);">*</span></label>
        <input type="text" id="phone" name="phone" required value="<?= htmlspecialchars($form['phone']) ?>">
      </div>
      <div class="field">
        <label for="line_id">LINE ID (ไม่บังคับ)</label>
        <input type="text" id="line_id" name="line_id" value="<?= htmlspecialchars($form['line_id']) ?>">
      </div>
      <div class="field">
        <label for="password">รหัสผ่าน <span style="color:var(--error-text);">*</span></label>
        <input type="password" id="password" name="password" required autocomplete="new-password">
      </div>
      <div class="field">
        <label for="password_confirm">ยืนยันรหัสผ่าน <span style="color:var(--error-text);">*</span></label>
        <input type="password" id="password_confirm" name="password_confirm" required autocomplete="new-password">
      </div>
      <button type="submit" class="btn">สมัครสมาชิก</button>
    </form>
  </div>
<?php render_sidebar($layout); render_footer(); ?>
