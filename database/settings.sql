-- mBlog — ค่าตั้งค่าเว็บ (ย้ายจาก config/settings.php ตอนทำหน้าแอดมิน settings.php)
-- รันไฟล์นี้ตอนเริ่มทำหน้าตั้งค่า (ต้องรัน phase1_core.sql ไปก่อนแล้ว)
-- setting_key ไม่ใช้ชื่อ "key" ตรงๆ เพราะเป็นคำสงวนของ MySQL

CREATE TABLE IF NOT EXISTS mblog_settings (
    setting_key VARCHAR(100) NOT NULL,
    value TEXT NOT NULL,
    PRIMARY KEY (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ค่าเดิมจาก config/settings.php ให้เว็บใช้งานต่อได้ทันทีหลังย้าย
INSERT INTO mblog_settings (setting_key, value) VALUES
    ('site_name', 'mBlog Web'),
    ('timezone', 'Asia/Bangkok'),
    ('owner_email', 'mblog@mblogofficial.com'),
    ('footer_tagline', 'สร้างด้วย PHP ล้วนๆ กับ ai อยากทำเองใช้เอง'),
    ('articles_per_page', '10')
ON DUPLICATE KEY UPDATE value = VALUES(value);
