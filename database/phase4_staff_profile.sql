-- mBlog — Phase 4: โปรไฟล์เต็มสำหรับ staff (username/ชื่อ-นามสกุล/เบอร์โทร/LINE ID/avatar)
-- ใช้ username แทน หรือคู่กับ email ตอน login ได้ทั้งคู่ (ดู login.php)
-- โครงสร้างเดียวกันถูก mirror ไปที่ mblog_users (ผู้ชมทั่วไป) ด้วย เผื่อใช้อนาคต
-- แม้ยังไม่มีโค้ดฝั่งนั้นใช้งานจริงตอนนี้ก็ตาม

-- mblog_staff มีแถวข้อมูลอยู่แล้ว (อย่างน้อย admin คนแรก) — เพิ่มคอลัมน์ username แบบ
-- NULL ก่อน, backfill จาก email, แล้วค่อยบังคับ NOT NULL + UNIQUE ทีหลัง กัน insert
-- ล้มเหลวตอนเพิ่มคอลัมน์ (ต่างจาก mblog_users ที่ว่างเปล่า ทำ NOT NULL ได้เลยรอบเดียว)
ALTER TABLE mblog_staff
    ADD COLUMN username VARCHAR(50) NULL AFTER email,
    ADD COLUMN first_name VARCHAR(100) NULL AFTER username,
    ADD COLUMN last_name VARCHAR(100) NULL AFTER first_name,
    ADD COLUMN phone VARCHAR(30) NULL AFTER last_name,
    ADD COLUMN line_id VARCHAR(100) NULL AFTER phone,
    ADD COLUMN avatar_path VARCHAR(500) NULL AFTER line_id;

UPDATE mblog_staff SET username = SUBSTRING_INDEX(email, '@', 1) WHERE username IS NULL;

ALTER TABLE mblog_staff
    MODIFY COLUMN username VARCHAR(50) NOT NULL,
    ADD UNIQUE KEY uq_mblog_staff_username (username);

-- mblog_users (ผู้ชมทั่วไป) ยังว่างเปล่าอยู่ (ตารางเปล่า ไม่มีโค้ดใช้งานจริง) — เพิ่ม
-- username แบบ NOT NULL + UNIQUE ได้เลยรอบเดียว ไม่มีแถวเก่าให้ backfill
ALTER TABLE mblog_users
    ADD COLUMN username VARCHAR(50) NOT NULL AFTER email,
    ADD COLUMN first_name VARCHAR(100) NULL AFTER username,
    ADD COLUMN last_name VARCHAR(100) NULL AFTER first_name,
    ADD COLUMN phone VARCHAR(30) NULL AFTER last_name,
    ADD COLUMN line_id VARCHAR(100) NULL AFTER phone,
    ADD COLUMN avatar_path VARCHAR(500) NULL AFTER line_id,
    ADD UNIQUE KEY uq_mblog_users_username (username);
