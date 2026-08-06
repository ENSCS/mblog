<?php
require __DIR__ . '/config.php';

// Already logged in — no reason to see the form again.
if (currentUser() !== null) {
    header('Location: admin.php');
    exit;
}

$redirectTarget = $_GET['redirect'] ?? $_POST['redirect'] ?? 'admin.php';
// Only ever follow a same-site relative path — an open redirect via ?redirect=
// would otherwise let a phishing link bounce a successful login to any host.
if (!preg_match('#^/?[a-zA-Z0-9_\-./]+\.php(\?.*)?$#', $redirectTarget)) {
    $redirectTarget = 'admin.php';
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $identifier = trim($_POST['identifier'] ?? '');
    $password = $_POST['password'] ?? '';

    // Accepts either — the same field doubles as email or username so
    // people can log in with whichever they remember.
    $stmt = db()->prepare('SELECT id, password_hash FROM mblog_staff WHERE email = ? OR username = ?');
    $stmt->execute([$identifier, $identifier]);
    $row = $stmt->fetch();

    // Same error message whether the account doesn't exist or the password
    // is wrong — a distinct "no such account" message would let an attacker
    // enumerate which emails/usernames have staff accounts.
    if (!$row || !password_verify($password, $row['password_hash'])) {
        $error = 'อีเมลหรือรหัสผ่านไม่ถูกต้อง';
    } else {
        // New session id on privilege change (login) — closes the session-
        // fixation window where an attacker who fixed a visitor's session id
        // before login could reuse the same id to hijack it after.
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $row['id'];
        header('Location: ' . $redirectTarget);
        exit;
    }
}

$pageTitle = 'เข้าสู่ระบบ';
$layout = render_header(compact('pageTitle'));
?>
  <div class="card" style="max-width:360px; margin:40px auto;">
    <h1 class="article-title" style="font-size:22px;">เข้าสู่ระบบ</h1>
    <?php if ($error !== ''): ?>
      <div class="settings-notice settings-notice-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <form method="post">
      <?= csrfField() ?>
      <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirectTarget) ?>">
      <div class="field">
        <label for="identifier">อีเมล หรือ Username</label>
        <input type="text" id="identifier" name="identifier" required autofocus autocomplete="username" value="<?= htmlspecialchars($_POST['identifier'] ?? '') ?>">
      </div>
      <div class="field">
        <label for="password">รหัสผ่าน</label>
        <input type="password" id="password" name="password" required>
      </div>
      <button type="submit" class="btn">เข้าสู่ระบบ</button>
    </form>
  </div>
<?php render_sidebar($layout); render_footer(); ?>
