<?php
// คัดลอกไฟล์นี้เป็น config.php แล้วปรับค่าตามเครื่อง/เซิร์ฟเวอร์จริง
// config.php ไม่ถูก commit เข้า git (ดู .gitignore) เพราะจะเก็บ credentials จริงในอนาคต (เช่น DB)

define('APP_ENV', 'local'); // 'local' | 'production'

define('ARTICLES_DIR', __DIR__ . '/articles/');
define('UPLOADS_DIR', __DIR__ . '/uploads/');

// เผื่ออนาคตต่อ MySQL — ยังไม่ใช้งานตอนนี้
// define('DB_HOST', 'localhost');
// define('DB_NAME', 'mblog');
// define('DB_USER', '');
// define('DB_PASS', '');
