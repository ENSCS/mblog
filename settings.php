<?php
require __DIR__ . '/config.php';

// Curated to what this single-owner Thai blog actually needs — not a full
// 400-entry IANA timezone picker nobody here would use.
$timezoneOptions = ['Asia/Bangkok' => 'Asia/Bangkok (ไทย)', 'UTC' => 'UTC'];

// Saves an uploaded site asset (logo/favicon) to uploads/site/, replacing any
// previous file for the same slot regardless of its old extension — unlike
// per-article content images (which accumulate in mblog_images), there's only
// ever one current logo/favicon, so the old file would otherwise be an orphan
// nobody serves but nobody cleans up either. Returns the new relative path,
// or null if this submit didn't include a file for $fieldName.
function saveSiteAsset(string $fieldName, string $slotName, array $allowedExt, int $maxBytes): ?string
{
    if (!isset($_FILES[$fieldName]) || $_FILES[$fieldName]['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($_FILES[$fieldName]['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('อัปโหลดไฟล์ไม่สำเร็จ');
    }

    $file = $_FILES[$fieldName];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExt, true)) {
        throw new RuntimeException('ไฟล์ต้องเป็นสกุล ' . implode('/', $allowedExt) . ' เท่านั้น');
    }
    if ($file['size'] > $maxBytes) {
        throw new RuntimeException('ไฟล์ใหญ่เกินไป (สูงสุด ' . round($maxBytes / 1024 / 1024, 1) . 'MB)');
    }
    // SVG has no raster dimensions getimagesize() can reliably read, so only
    // enforce the "really is an image" check for the raster formats.
    if ($ext !== 'svg' && @getimagesize($file['tmp_name']) === false) {
        throw new RuntimeException('ไฟล์นี้ไม่ใช่รูปภาพที่ถูกต้อง');
    }

    $dir = UPLOADS_DIR . 'site/';
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        throw new RuntimeException('สร้างโฟลเดอร์อัปโหลดไม่สำเร็จ');
    }
    foreach (glob($dir . $slotName . '.*') as $old) {
        unlink($old);
    }

    $filename = $slotName . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], $dir . $filename)) {
        throw new RuntimeException('บันทึกไฟล์ไม่สำเร็จ');
    }

    return 'uploads/site/' . $filename;
}

function removeSiteAsset(string $slotName): void
{
    foreach (glob(UPLOADS_DIR . 'site/' . $slotName . '.*') as $old) {
        unlink($old);
    }
}

$values = [
    'site_name' => siteSetting('site_name'),
    'timezone' => siteSetting('timezone'),
    'owner_email' => siteSetting('owner_email'),
    'footer_tagline' => siteSetting('footer_tagline'),
    'articles_per_page' => siteSetting('articles_per_page', 10),
    'site_logo' => siteSetting('site_logo', ''),
    'site_favicon' => siteSetting('site_favicon', ''),
];
$errors = [];
$saved = isset($_GET['saved']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $values['site_name'] = trim($_POST['site_name'] ?? '');
    $values['timezone'] = trim($_POST['timezone'] ?? '');
    $values['owner_email'] = trim($_POST['owner_email'] ?? '');
    $values['footer_tagline'] = trim($_POST['footer_tagline'] ?? '');
    $values['articles_per_page'] = trim($_POST['articles_per_page'] ?? '');

    if ($values['site_name'] === '') {
        $errors[] = 'กรุณาใส่ชื่อเว็บ';
    }
    if (!array_key_exists($values['timezone'], $timezoneOptions)) {
        $errors[] = 'เขตเวลาที่เลือกไม่ถูกต้อง';
    }
    if ($values['owner_email'] !== '' && !filter_var($values['owner_email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'รูปแบบอีเมลไม่ถูกต้อง';
    }
    if (!ctype_digit((string) $values['articles_per_page']) || (int) $values['articles_per_page'] < 1) {
        $errors[] = 'จำนวนบทความต่อหน้าต้องเป็นตัวเลขมากกว่า 0';
    }

    // File uploads are only handled once the fields above are already valid —
    // no point writing/deleting files on disk for a submit we're going to
    // reject anyway.
    if (!$errors) {
        try {
            if (!empty($_POST['remove_site_logo'])) {
                removeSiteAsset('logo');
                $values['site_logo'] = '';
            } else {
                $newLogo = saveSiteAsset('site_logo_file', 'logo', ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'], 2 * 1024 * 1024);
                if ($newLogo !== null) {
                    $values['site_logo'] = $newLogo;
                }
            }
            if (!empty($_POST['remove_site_favicon'])) {
                removeSiteAsset('favicon');
                $values['site_favicon'] = '';
            } else {
                $newFavicon = saveSiteAsset('site_favicon_file', 'favicon', ['ico', 'png', 'svg', 'gif'], 1024 * 1024);
                if ($newFavicon !== null) {
                    $values['site_favicon'] = $newFavicon;
                }
            }
        } catch (RuntimeException $e) {
            $errors[] = $e->getMessage();
        }
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
    <form method="post" enctype="multipart/form-data">
      <div class="field">
        <label for="site_name">ชื่อเว็บ</label>
        <input type="text" id="site_name" name="site_name" value="<?= htmlspecialchars($values['site_name']) ?>">
      </div>
      <div class="field">
        <label for="site_logo_file">โลโก้เว็บ (ไม่บังคับ — แสดงหน้าชื่อเว็บบน header, ย่อให้พอดีสูงไม่เกิน 55px กว้างไม่เกิน 80px เสมอ)</label>
        <?php if ($values['site_logo']): ?>
          <div class="featured-image-preview" style="display:flex; align-items:center;">
            <img src="<?= htmlspecialchars($values['site_logo']) ?>" alt="" style="max-height:55px; max-width:80px; object-fit:contain;">
            <label style="margin-left:12px; font-weight:normal;"><input type="checkbox" name="remove_site_logo" value="1"> ลบโลโก้</label>
          </div>
        <?php endif; ?>
        <input type="file" id="site_logo_file" name="site_logo_file" accept=".jpg,.jpeg,.png,.gif,.webp,.svg">
      </div>
      <div class="field">
        <label for="site_favicon_file">Favicon (ไม่บังคับ — ไอคอนบนแท็บเบราว์เซอร์ แนะนำไฟล์สี่เหลี่ยมจัตุรัส .ico/.png/.svg)</label>
        <?php if ($values['site_favicon']): ?>
          <div class="featured-image-preview" style="display:flex; align-items:center;">
            <img src="<?= htmlspecialchars($values['site_favicon']) ?>" alt="" style="max-height:32px; max-width:32px; object-fit:contain;">
            <label style="margin-left:12px; font-weight:normal;"><input type="checkbox" name="remove_site_favicon" value="1"> ลบ favicon</label>
          </div>
        <?php endif; ?>
        <input type="file" id="site_favicon_file" name="site_favicon_file" accept=".ico,.png,.svg,.gif">
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
      <div class="field">
        <label for="articles_per_page">จำนวนบทความต่อหน้า (หน้ารายการบทความ)</label>
        <input type="number" id="articles_per_page" name="articles_per_page" value="<?= htmlspecialchars((string) $values['articles_per_page']) ?>" min="1" style="max-width:100px;">
      </div>
      <button type="submit" class="btn">บันทึก</button>
    </form>
  </div>
<?php include __DIR__ . '/partials/footer.php'; ?>
