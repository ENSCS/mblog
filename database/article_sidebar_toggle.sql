-- mBlog — per-article/per-page sidebar override
-- NULL = ยึดตาม sidebar_enabled ของเว็บ (mblog_settings) แบบ live, ไม่ใช่ snapshot
-- 1    = บังคับเปิด sidebar สำหรับบทความ/หน้านี้ ไม่สน setting เว็บ
-- 0    = บังคับปิด sidebar สำหรับบทความ/หน้านี้ ไม่สน setting เว็บ
-- ไม่ตั้ง DEFAULT ตายตัวเป็น 1/0 เพราะ NULL คือ "ตามเว็บ" ซึ่งเป็นพฤติกรรมเดิมของ
-- บทความทุกอันก่อนมี column นี้ — ปล่อยว่างไว้ ไม่กระทบบทความเก่าเลย

ALTER TABLE mblog_articles
    ADD COLUMN show_sidebar TINYINT(1) NULL DEFAULT NULL AFTER featured_image;
