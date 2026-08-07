<?php
require __DIR__ . '/includes/articles.php';
require __DIR__ . '/includes/users.php';
require __DIR__ . '/includes/admin-nav.php';
requireCapability('manage_users');

// Admin-only — viewing/details + "forgot password" reset for a user who
// contacted support. A user's own self-service editing (avatar, name,
// phone, LINE ID — never username) lives on the separate my-profile.php
// instead, since the two audiences turned out different enough (own-account
// editing vs. an admin's read-mostly support tool) not to share one page.
$id = (int) ($_GET['id'] ?? 0);
$user = getUserById($id);
if (!$user) {
    renderErrorPage(404, 'ไม่พบผู้ใช้นี้');
}

$tierLabels = ['free' => 'Free', 'paid' => 'Paid', 'premium' => 'Premium'];
$tierColors = ['free' => 'gray', 'paid' => 'blue', 'premium' => 'purple'];

$errors = [];
$saved = isset($_GET['saved']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    // One <form>, two submit buttons under the same password field — each
    // button sets its own 'password_action' value instead of splitting into
    // two separate forms, per how this was asked for ("2 ปุ่ม ล่างของช่อง
    // กรอกรหัส").
    $passwordAction = $_POST['password_action'] ?? '';

    if ($passwordAction === 'set_password') {
        $password = $_POST['password'] ?? '';
        if (strlen($password) < 8) {
            $errors[] = 'รหัสผ่านต้องมีอย่างน้อย 8 ตัวอักษร';
        } else {
            updateUserPassword($id, $password);
            header('Location: user-profile.php?id=' . $id . '&saved=1');
            exit;
        }
    } elseif ($passwordAction === 'use_phone') {
        if (empty($user['phone'])) {
            $errors[] = 'ผู้ใช้คนนี้ไม่มีเบอร์โทรในระบบ ตั้งรหัสผ่านแบบนี้ไม่ได้';
        } else {
            updateUserPassword($id, $user['phone']);
            header('Location: user-profile.php?id=' . $id . '&saved=1');
            exit;
        }
    }
}

$displayName = trim($user['first_name'] . ' ' . $user['last_name']);
$pageTitle = 'โปรไฟล์ผู้ใช้: ' . ($displayName !== '' ? $displayName : $user['username']);
$showAdminSidebar = true;
$layout = render_header(compact('pageTitle', 'showAdminSidebar'));
?>
  <h1 class="article-title"><?= htmlspecialchars($pageTitle) ?></h1>
  <p style="color:var(--text-muted); margin-top:-8px;"><a href="users.php">&larr; กลับไปหน้าจัดการผู้ใช้</a></p>

  <?php if ($saved): ?><div class="settings-notice settings-notice-success">บันทึกแล้ว</div><?php endif; ?>
  <?php if ($errors): ?>
    <div class="settings-notice settings-notice-error">
      <?php foreach ($errors as $error): ?><div><?= htmlspecialchars($error) ?></div><?php endforeach; ?>
    </div>
  <?php endif; ?>

  <div class="card">
    <h2 style="margin-top:0;">รายละเอียดโปรไฟล์</h2>
    <table class="admin-table">
      <tbody>
        <tr>
          <th style="width:160px;">รูปโปรไฟล์</th>
          <td>
            <?php if ($user['avatar_path']): ?>
              <img src="<?= htmlspecialchars($user['avatar_path']) ?>" alt="" class="avatar-thumb avatar-thumb-xl">
            <?php else: ?>
              <span class="avatar-thumb avatar-thumb-xl avatar-thumb-placeholder <?= avatarColorClass((int) $user['id']) ?>"><?= htmlspecialchars(avatarInitial($user)) ?></span>
            <?php endif; ?>
          </td>
        </tr>
        <tr><th style="width:160px;">User ID</th><td><?= (int) $user['id'] ?></td></tr>
        <tr><th>ชื่อ-นามสกุล</th><td><?= htmlspecialchars($displayName !== '' ? $displayName : '-') ?></td></tr>
        <tr><th>Username</th><td><?= htmlspecialchars($user['username']) ?></td></tr>
        <tr><th>อีเมล</th><td><?= htmlspecialchars($user['email']) ?></td></tr>
        <tr><th>เบอร์โทร</th><td><?= htmlspecialchars($user['phone'] ?? '-') ?></td></tr>
        <tr><th>LINE ID</th><td><?= htmlspecialchars($user['line_id'] ?? '-') ?></td></tr>
        <tr><th>สิทธิ์</th><td><span class="category-tag category-tag-<?= $tierColors[$user['tier']] ?? 'gray' ?>"><?= htmlspecialchars($tierLabels[$user['tier']] ?? $user['tier']) ?></span></td></tr>
        <tr><th>สมัครเมื่อ</th><td><?= relativeTimeTag($user['created_at']) ?></td></tr>
      </tbody>
    </table>
  </div>

  <div class="card">
    <h2 style="margin-top:0;">ตั้งรหัสผ่านใหม่ (เผื่อผู้ใช้ลืมรหัสผ่าน)</h2>
    <form method="post">
      <?= csrfField() ?>
      <div class="field">
        <label for="password">รหัสผ่านใหม่ (อย่างน้อย 8 ตัวอักษร)</label>
        <input type="password" id="password" name="password" autocomplete="new-password">
      </div>
      <button type="submit" name="password_action" value="set_password" class="btn">แก้ไขรหัสผ่าน</button>
      <button type="submit" name="password_action" value="use_phone" class="btn btn-secondary"
        <?= empty($user['phone']) ? 'disabled' : '' ?>
        onclick="return confirm('ตั้งรหัสผ่านของ &quot;<?= htmlspecialchars($user['username'], ENT_QUOTES) ?>&quot; เป็นเบอร์โทร &quot;<?= htmlspecialchars($user['phone'] ?? '', ENT_QUOTES) ?>&quot;?');">ใช้เบอร์โทรเป็นรหัส</button>
    </form>
  </div>
<?php render_sidebar($layout); render_footer(); ?>
