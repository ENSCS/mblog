-- mBlog — Phase 3b: ตารางผู้ชมทั่วไป (แยกจาก mblog_staff โดยตั้งใจ)
-- ทีมงานเว็บ (admin/editor/author) กับผู้ชมทั่วไปคนละกลุ่มกันเด็ดขาด ไม่ปนกันแม้จะทั้งคู่
-- "login" ได้ในความหมายกว้างๆ — กันสิทธิ์ทีมงานรั่วไปปนกับบัญชีผู้ชม และแยกง่ายเวลาจัดการ/ลบทิ้งทั้งกลุ่ม
--
-- ตารางนี้สร้างไว้เปล่าๆ ก่อนตามที่ตกลงกันไว้ — ยังไม่มีหน้า/โค้ดฝั่งไหนอ้างอิงเลย
-- (รอฟีเจอร์คอมเมนต์ / บทความล็อกรหัสผ่านที่จะมาทีหลัง) คอลัมน์เก็บแค่ identity ขั้นต่ำสุด
-- โดยตั้งใจ ไม่เดาฟิลด์เพิ่มล่วงหน้า (ดู PLANNING.md หัวข้อ 13 "Custom fields" สำหรับหลักการเดียวกัน
-- ที่ใช้อยู่แล้วในโปรเจกต์นี้ — รอความต้องการจริงก่อนค่อยออกแบบ schema เพิ่ม)

CREATE TABLE IF NOT EXISTS mblog_users (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    email VARCHAR(255) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_mblog_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
