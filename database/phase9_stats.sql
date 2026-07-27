-- mBlog — Phase 9: สถิติยอดอ่านต่อบทความ
-- รันไฟล์นี้ตอนเริ่ม Phase 9 (ต้องรัน phase1_core.sql ไปก่อนแล้ว)
-- แยกจาก mblog_articles ตั้งใจ — ยอดอ่านเป็นข้อมูลที่ระบบนับเองทุก pageview (เขียนถี่มาก)
-- ต่างธรรมชาติกับข้อมูลที่คนเขียนกรอกเอง (title/content/...) ใน mblog_articles
-- เก็บไว้แยกเพื่ออ่านเร็ว (โชว์ยอดบนหน้าเว็บ) ไม่ต้อง COUNT() จาก mblog_pageview_log
-- (ด้านล่าง) ทุกครั้งที่มีคนเปิดหน้า

CREATE TABLE IF NOT EXISTS mblog_article_stats (
    article_id BIGINT UNSIGNED NOT NULL,
    view_count INT UNSIGNED NOT NULL DEFAULT 0,
    updated_at DATETIME NULL,
    PRIMARY KEY (article_id),
    CONSTRAINT fk_mblog_article_stats_article FOREIGN KEY (article_id)
        REFERENCES mblog_articles (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- mblog_pageview_log: log ราย pageview ของ "ทั้งเว็บ" ไม่ใช่แค่บทความ (คุยกันวันที่
-- 2026-07-27 — เริ่มจากนับแค่หน้าบทความ แล้วขยายเป็นทุกหน้าเพราะอยากเห็นสถิติคนเข้าเว็บ
-- โดยรวมด้วย) ตัดเรื่อง geo/ประเทศออกเพราะซับซ้อนเกินไป ให้เครื่องมือนอกเช่น Google
-- Analytics ทำหน้าที่นั้นแทน ตั้งใจไม่เก็บ IP ดิบเลย เก็บแค่ visitor_hash (HMAC ของ IP
-- ผสม salt ที่หมุนรายวัน — ดู includes/stats.php) เพื่อนับ unique visitor ได้โดยไม่ต้องแบก
-- ความเสี่ยง PDPA จากการเก็บ IP ตรงๆ — salt หมุนทุกวันแปลว่า hash ของคนคนเดียวกันจะ
-- เปลี่ยนทุกวัน track ข้ามวันไม่ได้ (ตั้งใจ ไม่ใช่บั๊ก)
--
-- page_type/page_path: รองรับหน้าที่ไม่ใช่บทความ (category/tag/หน้าคงที่/ค้นหา/และหน้า
-- ใหม่ในอนาคตที่ยังไม่รู้จัก) โดยไม่ต้องแก้ schema เพิ่มทุกครั้งที่มีหน้าใหม่ — page_type
-- เป็นแค่ string อิสระ (article/articles_list/category/tag/page/search/other) ส่วน
-- page_path เก็บ REQUEST_URI จริงไว้ (เช่น "category.php?slug=xxx") ใช้แยกว่าเป็น
-- entity ไหนตอนดูรายงานทีหลัง แทนที่จะต้องมี FK column แยกต่อประเภทเอนทิตี
--
-- article_id: NULL ได้แล้ว (เดิมบังคับ NOT NULL ตอนยังนับแค่หน้าบทความ) — มีค่าเฉพาะตอน
-- page_type='article' เท่านั้น เพื่อยัง join กับ mblog_article_stats ได้เหมือนเดิม
--
-- device_type/os: parse จาก user_agent ด้วย regex ธรรมดา (includes/stats.php) — ข้อจำกัด
-- ที่ยอมรับแล้ว: iPad ตั้งแต่ iPadOS 13+ (2019) ปลอมตัวเป็น Macintosh ในเบราว์เซอร์เริ่มต้น
-- ทำให้ iPad ส่วนใหญ่ถูกนับเป็น device_type=desktop/os=macos แทน (ไม่ทำ JS beacon แก้
-- เพราะ ROI ต่ำเทียบความซับซ้อนที่เพิ่ม)
--
-- ยังไม่มี retention/purge policy อัตโนมัติ — ตารางนี้จะโตเรื่อยๆ ตามยอดเข้าชม (ยิ่งนับทุก
-- หน้าแล้วยิ่งโตเร็วขึ้น) ถ้าจำนวนมากขึ้นควรเพิ่ม cron ลบ log เก่ากว่า N วันทีหลัง (ยังไม่ทำ
-- ตอนนี้เพราะยังไม่จำเป็น)
CREATE TABLE IF NOT EXISTS mblog_pageview_log (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    article_id BIGINT UNSIGNED NULL,
    page_type VARCHAR(20) NOT NULL DEFAULT 'other',
    page_path VARCHAR(255) NULL,
    visitor_hash CHAR(64) NOT NULL,
    user_agent MEDIUMTEXT NULL,
    device_type VARCHAR(20) NOT NULL DEFAULT 'other',
    os VARCHAR(20) NOT NULL DEFAULT 'other',
    is_bot TINYINT(1) NOT NULL DEFAULT 0,
    referrer MEDIUMTEXT NULL,
    viewed_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_mblog_pageview_log_article (article_id, viewed_at),
    KEY idx_mblog_pageview_log_page_type (page_type, viewed_at),
    CONSTRAINT fk_mblog_pageview_log_article FOREIGN KEY (article_id)
        REFERENCES mblog_articles (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
