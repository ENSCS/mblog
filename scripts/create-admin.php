<?php
// สร้างบัญชีทีมงาน (mblog_staff) จาก command line — ใช้สร้าง admin คนแรกตอนตั้งเว็บใหม่
// (ไม่มีหน้าเว็บสมัครเองได้ ตั้งใจ — ต้องมี admin อยู่แล้วถึงจะสร้างทีมงานอื่นผ่าน staff.php ได้
// ตัวแรกสุดเลยต้องมาจาก CLI นี้เท่านั้น) ใช้ต่อได้ด้วยถ้าลืมรหัสผ่าน admin คนเดียวที่มี
// (ลบแถวแล้วสร้างใหม่ ยังไม่มีฟีเจอร์ "ลืมรหัสผ่าน" ผ่านหน้าเว็บ)
//
// รัน:  php scripts/create-admin.php you@example.com "รหัสผ่านของคุณ" [admin|editor|author] [username]
// role ไม่ใส่ = admin (กรณีใช้บ่อยที่สุดคือสร้างคนแรก)
// username ไม่ใส่ = ใช้ส่วนหน้า @ ของอีเมลแทน (เหมือน backfill ตอน migrate ใน
// database/phase4_staff_profile.sql)

require __DIR__ . '/../config.php';
require __DIR__ . '/../includes/staff.php';

$email = trim($argv[1] ?? '');
$password = $argv[2] ?? '';
$role = $argv[3] ?? 'admin';
$username = trim($argv[4] ?? '') ?: null;

if ($email === '' || $password === '') {
    fwrite(STDERR, "Usage: php scripts/create-admin.php <email> <password> [admin|editor|author] [username]\n");
    exit(1);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Invalid email: $email\n");
    exit(1);
}
if (strlen($password) < 8) {
    fwrite(STDERR, "Password must be at least 8 characters.\n");
    exit(1);
}
if (!in_array($role, ['admin', 'editor', 'author'], true)) {
    fwrite(STDERR, "Role must be admin, editor, or author.\n");
    exit(1);
}

if (staffEmailExists($email)) {
    fwrite(STDERR, "A staff member with that email already exists.\n");
    exit(1);
}
if ($username !== null && staffUsernameExists($username)) {
    fwrite(STDERR, "A staff member with that username already exists — pass a different one as the 4th argument.\n");
    exit(1);
}
// No explicit username = derive from the email's local part, same
// collision-safe (-2, -3, ...) logic staff.php's "add new user" and
// setup.php's first-admin form both rely on.
$username = $username ?? generateStaffUsernameFromEmail($email);

createStaff($email, $username, $password, $role);

echo "Created {$role} user: {$email} (username: {$username})\n";
