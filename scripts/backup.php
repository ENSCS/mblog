<?php
// สคริปต์ backup ข้อมูล — บีบอัดทั้งเว็บ (database ทุกตาราง ยกเว้น
// mblog_pageview_log + uploads/) ลง backups/backup-YYYYMMDD-HHMMSS.zip
// Logic จริงอยู่ที่ includes/backup.php (ใช้ร่วมกับปุ่ม "Backup ตอนนี้" ใน
// backup.php หน้าแอดมิน) ไฟล์นี้เป็นแค่ CLI wrapper บางๆ
// ไม่รวม config.php/credentials ในนี้ — ควรเก็บสำเนาไว้แยกต่างหากเอง (เช่น password manager)
// ไม่ควรปนกับ backup ทั่วไปที่อาจส่งต่อ/แชร์กัน
//
// รันเอง:  php scripts/backup.php
//
// ตั้งให้รันอัตโนมัติทุกคืน (ยังไม่ได้ตั้งให้ — เพิ่มบรรทัดนี้เองใน crontab ถ้าต้องการ):
//   0 2 * * * /usr/bin/php /path/to/mblog/scripts/backup.php >> /path/to/mblog/logs/backup.log 2>&1
//
// backup ที่ได้ยังอยู่บนเครื่อง/เซิร์ฟเวอร์เดียวกัน — ตามหลัก 3-2-1 ควรคัดลอกออกไปเก็บที่อื่นด้วย
// (คนละดิสก์ / cloud storage) เป็นขั้นตอนที่ต้องทำเพิ่มเอง สคริปต์นี้ยังไม่ได้ส่งออกให้อัตโนมัติ
// — ดาวน์โหลดด้วยมือได้จากหน้า backup.php ในแอดมิน

require __DIR__ . '/../config.php';
require __DIR__ . '/../includes/backup.php';

const RETENTION_DAYS = 14;

$backup = createBackupArchive();
$sizeKb = round($backup['size'] / 1024, 1);
echo "Backup created: {$backup['path']} ({$sizeKb} KB)\n";

$deleted = pruneOldBackups(RETENTION_DAYS);
if ($deleted > 0) {
    echo "Removed {$deleted} backup(s) older than " . RETENTION_DAYS . " days\n";
}
