-- mBlog — บันทึกความพยายามล็อกอินที่ผิดพลาด (login.php) เพื่อป้องกัน brute-force
-- ตรวจสอบก่อน password_verify() ทุกครั้ง (includes/auth.php: isLoginLocked()) — ถ้า
-- identifier เดียวกันผิดเกิน 5 ครั้ง หรือ IP เดียวกันผิดเกิน 10 ครั้ง ภายใน 15 นาทีล่าสุด
-- จะถูกบล็อกโดยไม่ต้องแตะรหัสผ่านเลย แถวเก่ากว่า 1 วันถูกล้างอัตโนมัติทุกครั้งที่มีการ
-- insert ใหม่ (recordFailedLogin()) ไม่ต้องมี cron แยก
CREATE TABLE IF NOT EXISTS mblog_login_attempts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    identifier VARCHAR(255) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    attempted_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_identifier_attempted (identifier, attempted_at),
    KEY idx_ip_attempted (ip_address, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
