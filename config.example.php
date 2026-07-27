<?php
// คัดลอกไฟล์นี้เป็น config.php แล้วปรับค่าตามเครื่อง/เซิร์ฟเวอร์จริง
// config.php ไม่ถูก commit เข้า git (ดู .gitignore) เพราะจะเก็บ credentials จริงในอนาคต (เช่น DB)

// สร้างฐานข้อมูล + รันไฟล์ใน database/*.sql ก่อน (เริ่มจาก phase1_core.sql)
// DB credentials ต้อง define ก่อน require settings.php ด้านล่าง เพราะค่าตั้งค่าเว็บอยู่ใน
// ตาราง mblog_settings แล้ว — siteSetting() ต้องใช้ db() ซึ่งต้องมีค่าพวกนี้อยู่แล้วตอนถูกเรียก
define('DB_HOST', '127.0.0.1'); // ใช้ TCP ไม่ใช่ 'localhost' (unix socket) — path ของ socket ต่างกันได้ระหว่างเครื่อง/PHP ที่ใช้
define('DB_NAME', 'mblog');
define('DB_USER', 'root');
define('DB_PASS', '');

// บังคับ timezone จาก mblog_settings ตายตัว — ไม่พึ่ง php.ini ของแต่ละเครื่อง/แต่ละ
// PHP ที่รัน (Apache vs CLI อาจตั้งค่าไม่ตรงกัน ทำให้เวลาที่เก็บใน DB ถูกอ่านกลับมาคลาดเคลื่อนได้)
require_once __DIR__ . '/includes/settings.php';
date_default_timezone_set(siteSetting('timezone', 'UTC'));

define('APP_ENV', 'local'); // 'local' | 'production'

define('UPLOADS_DIR', __DIR__ . '/uploads/');
define('LOG_DIR', __DIR__ . '/logs/');
define('BACKUP_DIR', __DIR__ . '/backups/');

// สุ่มค่าใหม่ตอน copy ไปเป็น config.php จริง (เช่น php -r "echo bin2hex(random_bytes(32));")
// ห้ามเปลี่ยนอีกทีหลังตั้งแล้ว — ใช้ผสมกับ IP + วันที่คำนวณ visitor_hash (includes/stats.php)
define('STATS_HASH_SECRET', 'change-me-to-a-random-64-char-hex-string');

require_once __DIR__ . '/includes/error-handling.php';
