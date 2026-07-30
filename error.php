<?php
// เพจแสดง error กลาง เรียกผ่าน renderErrorPage() ใน includes/error-handling.php
$errorCode = $errorCode ?? 500;
$errorMessage = $errorMessage ?? 'เกิดข้อผิดพลาดบางอย่าง';

// Try the real site chrome (partials/header.php/footer.php) first, so an
// error page looks like the rest of the site — captured in an output
// buffer so a *partial* render (chrome throws halfway through, e.g. the
// menu/sidebar queries inside header.php hit an unreachable DB) never
// leaks broken HTML: only echoed if the whole thing finished without
// throwing. Falls back to the bare-bones standalone page below otherwise —
// this is the exact scenario this file exists to survive, so the fallback
// itself must not depend on config.php/siteSetting()/DB at all.
ob_start();
$renderedFullChrome = false;
try {
    $pageTitle = 'ข้อผิดพลาด ' . $errorCode;
    $layout = render_header(compact('pageTitle'));
    ?>
      <h1 class="article-title"><?= htmlspecialchars((string) $errorCode) ?></h1>
      <div class="card">
        <p><?= htmlspecialchars($errorMessage) ?></p>
        <p><a href="index.php">กลับหน้าแรก</a></p>
      </div>
    <?php
    render_sidebar($layout);
    render_footer();
    $renderedFullChrome = true;
} catch (Throwable $e) {
    // Swallow — fall through to the standalone fallback below. Whatever
    // broke (DB down, etc.) already has nowhere else left to be logged from
    // here; set_exception_handler() already logged the *original* error
    // that got us into renderErrorPage() in the first place.
}

if ($renderedFullChrome) {
    echo ob_get_clean();
    return;
}
ob_end_clean();

// Fallback: no config.php/siteSetting()/DB dependency at all, so this
// cannot fail a second time no matter what broke above.
$fallbackSiteName = defined('SITE_NAME_FALLBACK') ? SITE_NAME_FALLBACK : 'mBlog';
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>ข้อผิดพลาด <?= htmlspecialchars((string) $errorCode) ?> — <?= htmlspecialchars($fallbackSiteName) ?></title>
<link rel="stylesheet" href="assets/base.css">
<link rel="stylesheet" href="assets/layout.css">
<link rel="stylesheet" href="assets/components.css">
</head>
<body>
<div class="topbar">
  <div class="container">
    <a href="index.php" class="brand"><?= htmlspecialchars($fallbackSiteName) ?></a>
  </div>
</div>
<div class="container">
  <div class="empty-state">
    <h1 style="font-size:48px;margin-bottom:8px;color:#374151;"><?= htmlspecialchars((string) $errorCode) ?></h1>
    <p><?= htmlspecialchars($errorMessage) ?></p>
    <p><a href="index.php">กลับหน้าแรก</a></p>
  </div>
</div>
</body>
</html>
