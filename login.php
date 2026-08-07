<?php
require __DIR__ . '/config.php';

// One shared login form for both staff (mblog_staff) and general users
// (mblog_users) — already logged in as either = no reason to see the form
// again. Staff takes priority if somehow both sessions exist at once.
if (currentStaff() !== null) {
    header('Location: admin.php');
    exit;
} elseif (currentUser() !== null) {
    header('Location: index.php');
    exit;
}

$requestedRedirect = $_GET['redirect'] ?? $_POST['redirect'] ?? '';
// Only ever follow a same-site relative path — an open redirect via ?redirect=
// would otherwise let a phishing link bounce a successful login to any host.
// The (?!/) after the optional leading slash rejects a *second* leading
// slash (e.g. "//evil.com/x.php") — browsers treat "//host/path" as
// protocol-relative, so without this a value starting with "//" would pass
// the character-class check below (it only allows letters/digits/./-/_)
// while still redirecting off-site.
$requestedRedirect = preg_match('#^/?(?!/)[a-zA-Z0-9_\-./]+\.php(\?.*)?$#', $requestedRedirect) ? $requestedRedirect : '';
// Same explicit ?redirect= wins for either account type (e.g. bounced back
// here from a comment box) — only the *fallback* differs, since admin.php
// means nothing to a general user and index.php is a downgrade for staff
// muscle memory landing on their dashboard.
$staffRedirectTarget = $requestedRedirect !== '' ? $requestedRedirect : 'admin.php';
$userRedirectTarget = $requestedRedirect !== '' ? $requestedRedirect : 'index.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $identifier = trim($_POST['identifier'] ?? '');
    $password = $_POST['password'] ?? '';
    $clientIp = $_SERVER['REMOTE_ADDR'] ?? '';

    // Checked before ever touching the DB for a password row — a locked-out
    // request does zero password_verify() work, which is the whole point
    // against a scripted brute-force run. Same wording as a wrong password
    // below so a locked-out attacker can't distinguish the two and use that
    // to detect exactly when they got throttled.
    if (isLoginLocked($identifier, $clientIp)) {
        $error = 'อีเมลหรือรหัสผ่านไม่ถูกต้อง';
    } else {
        // Accepts either — the same field doubles as email or username so
        // people can log in with whichever they remember. Staff table checked
        // first (mblog_staff is the smaller, privileged namespace) — if the
        // identifier matches a staff account, only that account's password is
        // ever checked, never falling through to try it as a general user too,
        // even if the same identifier also happens to exist in mblog_users (the
        // two tables enforce uniqueness independently, see database/phase3b_readers.sql).
        $stmt = db()->prepare('SELECT id, password_hash FROM mblog_staff WHERE email = ? OR username = ?');
        $stmt->execute([$identifier, $identifier]);
        $staffRow = $stmt->fetch();

        if ($staffRow) {
            if (password_verify($password, $staffRow['password_hash'])) {
                // New session id on privilege change (login) — closes the
                // session-fixation window where an attacker who fixed a
                // visitor's session id before login could reuse the same id to
                // hijack it after.
                session_regenerate_id(true);
                $_SESSION['staff_id'] = (int) $staffRow['id'];
                clearLoginAttempts($identifier);
                header('Location: ' . $staffRedirectTarget);
                exit;
            }
            recordFailedLogin($identifier, $clientIp);
            // Same error message in every failure branch below — a distinct
            // "no such account" message would let an attacker enumerate which
            // emails/usernames have accounts, staff or general user.
            $error = 'อีเมลหรือรหัสผ่านไม่ถูกต้อง';
        } else {
            $stmt = db()->prepare('SELECT id, password_hash FROM mblog_users WHERE email = ? OR username = ?');
            $stmt->execute([$identifier, $identifier]);
            $userRow = $stmt->fetch();

            if ($userRow && password_verify($password, $userRow['password_hash'])) {
                session_regenerate_id(true);
                $_SESSION['user_id'] = (int) $userRow['id'];
                clearLoginAttempts($identifier);
                header('Location: ' . $userRedirectTarget);
                exit;
            }
            recordFailedLogin($identifier, $clientIp);
            $error = 'อีเมลหรือรหัสผ่านไม่ถูกต้อง';
        }
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
      <input type="hidden" name="redirect" value="<?= htmlspecialchars($requestedRedirect) ?>">
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
