-- mBlog — ระบบสมัครสมาชิกผู้อ่าน (register.php) + สิทธิ์การใช้งาน (tier)
-- mblog_users (ผู้ชมทั่วไป) มีคอลัมน์ identity ครบอยู่แล้ว (database/phase3b_readers.sql,
-- phase4_staff_profile.sql) เหลือแค่คอลัมน์ tier ที่ยังไม่มี — ตารางว่างอยู่จริง (ยังไม่มี
-- โค้ดฝั่งไหนใช้งานมาก่อนหน้านี้) เพิ่ม NOT NULL DEFAULT ได้เลยรอบเดียวไม่ต้อง backfill
-- 'free' เป็นค่าเริ่มต้นของทุกบัญชีที่สมัครเอง — 'paid'/'premium' ตั้งได้จากหน้าแอดมิน
-- (manage-readers.php) เท่านั้นตอนนี้ ยังไม่มีระบบชำระเงินที่อัปเกรดเองอัตโนมัติ

ALTER TABLE mblog_users
    ADD COLUMN tier ENUM('free', 'paid', 'premium') NOT NULL DEFAULT 'free' AFTER line_id;
