<?php
// คัดลอกไฟล์นี้เป็น config.php แล้วปรับค่าตามเครื่อง/เซิร์ฟเวอร์จริง
// config.php ไม่ถูก commit เข้า git (ดู .gitignore) เพราะจะเก็บ credentials จริงในอนาคต (เช่น DB)

// บังคับ timezone จาก config/settings.php ตายตัว — ไม่พึ่ง php.ini ของแต่ละเครื่อง/แต่ละ
// PHP ที่รัน (Apache vs CLI อาจตั้งค่าไม่ตรงกัน ทำให้เวลาที่เก็บใน DB ถูกอ่านกลับมาคลาดเคลื่อนได้)
require_once __DIR__ . '/includes/settings.php';
date_default_timezone_set(siteSetting('timezone', 'UTC'));

define('APP_ENV', 'local'); // 'local' | 'production'

define('UPLOADS_DIR', __DIR__ . '/uploads/');
define('LOG_DIR', __DIR__ . '/logs/');
define('BACKUP_DIR', __DIR__ . '/backups/');

// สร้างฐานข้อมูล + รันไฟล์ใน database/*.sql ก่อน (เริ่มจาก phase1_core.sql)
define('DB_HOST', '127.0.0.1'); // ใช้ TCP ไม่ใช่ 'localhost' (unix socket) — path ของ socket ต่างกันได้ระหว่างเครื่อง/PHP ที่ใช้
define('DB_NAME', 'mblog');
define('DB_USER', 'root');
define('DB_PASS', '');

require_once __DIR__ . '/includes/error-handling.php';
