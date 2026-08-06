<?php
require __DIR__ . '/config.php';
require __DIR__ . '/includes/users.php';

// The entire safety of this page rests on this one check running first,
// before anything else — works only while mblog_staff is completely empty
// (fresh install, before scripts/create-admin.php or this page has ever
// created anyone). The instant one user exists, this always renders a plain
// 404 no matter what, permanently and with no way to re-open it short of
// deleting every row in mblog_staff directly in the database — same "only
// works once, then closes itself forever" pattern WordPress's own
// wp-admin/install.php uses. 404 (not 403) on purpose: doesn't even hint
// that a setup flow ever existed here.
if (countUsers() > 0) {
    renderErrorPage(404, 'ไม่พบหน้านี้');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $passwordConfirm = $_POST['password_confirm'] ?? '';

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'กรุณาใส่อีเมลที่ถูกต้อง';
    } elseif (strlen($password) < 8) {
        $error = 'รหัสผ่านต้องมีอย่างน้อย 8 ตัวอักษร';
    } elseif ($password !== $passwordConfirm) {
        $error = 'รหัสผ่านทั้งสองช่องไม่ตรงกัน';
    } elseif (countUsers() > 0) {
        // Re-checked at submit time, not just page-load time — closes the
        // narrow race where someone else (e.g. scripts/create-admin.php run
        // over SSH in another terminal) created the first account in the
        // gap between this page loading and this form being submitted.
        $error = 'มีผู้ใช้ในระบบแล้ว — ตั้งค่าไม่ได้อีก ไปหน้าเข้าสู่ระบบแทน';
    } else {
        // Username picked automatically from the email's local part — a
        // proper one can always be set later from profile.php.
        $id = createUser($email, generateUsernameFromEmail($email), $password, 'admin');
        // Log them straight in — they just proved they can write to this
        // exact database, no separate login step needed after.
        session_regenerate_id(true);
        $_SESSION['user_id'] = $id;
        header('Location: admin.php');
        exit;
    }
}

$pageTitle = 'ตั้งค่าเริ่มต้น';
$layout = render_header(compact('pageTitle'));
?>
  <div class="card" style="max-width:360px; margin:40px auto;">
    <h1 class="article-title" style="font-size:22px;">สร้างบัญชี admin คนแรก</h1>
    <p style="color:var(--text-muted); font-size:13px;">
      เว็บนี้ยังไม่มีผู้ใช้เลย — สร้างบัญชี admin คนแรกที่นี่ หน้านี้จะปิดตัวเองถาวรทันทีที่สร้างเสร็จ
    </p>
    <?php if ($error !== ''): ?>
      <div class="settings-notice settings-notice-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <form method="post">
      <?= csrfField() ?>
      <div class="field">
        <label for="email">อีเมล</label>
        <input type="email" id="email" name="email" required autofocus value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
      </div>
      <div class="field">
        <label for="password">รหัสผ่าน (อย่างน้อย 8 ตัวอักษร)</label>
        <input type="password" id="password" name="password" required autocomplete="new-password">
      </div>
      <div class="field">
        <label for="password_confirm">ยืนยันรหัสผ่าน</label>
        <input type="password" id="password_confirm" name="password_confirm" required autocomplete="new-password">
      </div>
      <button type="submit" class="btn">สร้างบัญชี</button>
    </form>
  </div>
<?php render_sidebar($layout); render_footer(); ?>
