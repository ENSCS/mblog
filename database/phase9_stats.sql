-- mBlog — Phase 9: สถิติยอดอ่านต่อบทความ
-- รันไฟล์นี้ตอนเริ่ม Phase 9 (ต้องรัน phase1_core.sql ไปก่อนแล้ว)
-- แยกจาก mblog_articles ตั้งใจ — ยอดอ่านเป็นข้อมูลที่ระบบนับเองทุก pageview (เขียนถี่มาก)
-- ต่างธรรมชาติกับข้อมูลที่คนเขียนกรอกเอง (title/content/...) ใน mblog_articles
-- ตั้งใจเก็บแค่ยอดรวม ไม่ทำ event log ราย pageview — อะไรที่ละเอียดกว่านี้ (รายวัน, unique
-- visitor, referrer) ให้ใช้เครื่องมือ traffic analytics ภายนอกตามที่ PLANNING.md ระบุไว้

CREATE TABLE IF NOT EXISTS mblog_article_stats (
    article_id BIGINT UNSIGNED NOT NULL,
    view_count INT UNSIGNED NOT NULL DEFAULT 0,
    updated_at DATETIME NULL,
    PRIMARY KEY (article_id),
    CONSTRAINT fk_mblog_article_stats_article FOREIGN KEY (article_id)
        REFERENCES mblog_articles (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
