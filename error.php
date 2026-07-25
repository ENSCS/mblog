<?php
// เพจแสดง error กลาง เรียกผ่าน renderErrorPage() ใน includes/error-handling.php
// ตั้งใจให้ไม่พึ่งพา config/menu/includes อื่นเลย เพื่อไม่ให้พังซ้ำถ้าปัญหาต้นตอ
// มาจากจุดที่ไฟล์เหล่านั้นโหลดไม่สำเร็จอยู่แล้ว
$errorCode = $errorCode ?? 500;
$errorMessage = $errorMessage ?? 'เกิดข้อผิดพลาดบางอย่าง';
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>ข้อผิดพลาด <?= htmlspecialchars((string) $errorCode) ?> — mBlog</title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<div class="topbar">
  <div class="container">
    <a href="index.php" class="brand">mBlog</a>
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
