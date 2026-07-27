-- mBlog — ฟีดข่าวสั้น (เช่น ประกาศกำไรหุ้น ส่งมาจาก pipeline ภายนอกอัตโนมัติ)
-- ตารางแยกจาก mblog_articles โดยตั้งใจ (เหตุผลเดียวกับ sidebar.sql) — ข้อความสั้น
-- ไม่มีเนื้อหายาว ไม่ต้องมี slug/SEO/หมวดหมู่/แท็ก/รูปปก ความถี่สูงกว่าบทความมาก
--
-- id เป็นตัวขับ "อะไรใหม่" ทั้งหมด (ทั้งลำดับแสดงผลและตัว cursor ที่หน้า feed.php
-- ใช้ poll หาแถวใหม่ — ดู api/feed-poll.php) ไม่ใช้ published_at เป็นตัวตัดสิน
-- เพราะข้อความพวกนี้เป็นแบบเรียลไทม์ ไม่มีเคสต้องย้อนเวลาแบบไฟล์ Markdown นำเข้า
CREATE TABLE IF NOT EXISTS mblog_feed_items (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    content TEXT NOT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
