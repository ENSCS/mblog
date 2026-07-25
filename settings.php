<?php
require __DIR__ . '/config.php';

// Curated to what this single-owner Thai blog actually needs — not a full
// 400-entry IANA timezone picker nobody here would use.
$timezoneOptions = ['Asia/Bangkok' => 'Asia/Bangkok (ไทย)', 'UTC' => 'UTC'];

$values = [
    'site_name' => siteSetting('site_name'),
    'timezone' => siteSetting('timezone'),
    'owner_email' => siteSetting('owner_email'),
    'footer_tagline' => siteSetting('footer_tagline'),
];
$errors = [];
$saved = isset($_GET['saved']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $values['site_name'] = trim($_POST['site_name'] ?? '');
    $values['timezone'] = trim($_POST['timezone'] ?? '');
    $values['owner_email'] = trim($_POST['owner_email'] ?? '');
    $values['footer_tagline'] = trim($_POST['footer_tagline'] ?? '');

    if ($values['site_name'] === '') {
        $errors[] = 'กรุณาใส่ชื่อเว็บ';
    }
    if (!array_key_exists($values['timezone'], $timezoneOptions)) {
        $errors[] = 'เขตเวลาที่เลือกไม่ถูกต้อง';
    }
    if ($values['owner_email'] !== '' && !filter_var($values['owner_email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'รูปแบบอีเมลไม่ถูกต้อง';
    }

    // Redirect-after-POST so a page refresh after saving doesn't resubmit the
    // form, and so the next load re-reads settings fresh from the DB instead
    // of relying on getSettings()'s in-request cache.
    if (!$errors) {
        updateSiteSettings($values);
        header('Location: settings.php?saved=1');
        exit;
    }
}

$pageTitle = 'ตั้งค่าเว็บ — ' . siteSetting('site_name');
$topbarActions = '<a href="editor.php">+ เขียนบทความใหม่</a>';
include __DIR__ . '/partials/header.php';
?>
  <h1 class="article-title">ตั้งค่าเว็บ</h1>
  <div class="card">
    <?php if ($saved): ?>
      <div class="settings-notice settings-notice-success">บันทึกการตั้งค่าแล้ว</div>
    <?php endif; ?>
    <?php if ($errors): ?>
      <div class="settings-notice settings-notice-error">
        <?php foreach ($errors as $error): ?>
          <div><?= htmlspecialchars($error) ?></div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
    <form method="post">
      <div class="field">
        <label for="site_name">ชื่อเว็บ</label>
        <input type="text" id="site_name" name="site_name" value="<?= htmlspecialchars($values['site_name']) ?>">
      </div>
      <div class="field">
        <label for="timezone">เขตเวลา</label>
        <select id="timezone" name="timezone">
          <?php foreach ($timezoneOptions as $tz => $label): ?>
            <option value="<?= htmlspecialchars($tz) ?>" <?= $tz === $values['timezone'] ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label for="owner_email">อีเมลเจ้าของเว็บ (ไม่บังคับ)</label>
        <input type="text" id="owner_email" name="owner_email" value="<?= htmlspecialchars($values['owner_email']) ?>" placeholder="you@example.com">
      </div>
      <div class="field">
        <label for="footer_tagline">ข้อความท้ายเว็บ (footer tagline, ไม่บังคับ)</label>
        <input type="text" id="footer_tagline" name="footer_tagline" value="<?= htmlspecialchars($values['footer_tagline']) ?>">
      </div>
      <button type="submit" class="btn">บันทึก</button>
    </form>
  </div>
<?php include __DIR__ . '/partials/footer.php'; ?>
