-- mBlog — Phase 3: ระบบล็อกอิน/สิทธิ์
-- รันไฟล์นี้ตอนเริ่ม Phase 3 (ต้องรัน phase1_core.sql ไปก่อนแล้ว)
-- role เก็บไว้เป็นข้อมูลดิบเท่านั้น — เช็คสิทธิ์จริงในโค้ดผ่าน userCan() แบบ capability
-- ตามหลักการหลักของโปรเจกต์ ไม่เช็คชื่อ role ตรงๆ ในหน้าเว็บ

CREATE TABLE IF NOT EXISTS mblog_users (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    email VARCHAR(255) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'editor') NOT NULL DEFAULT 'editor',
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_mblog_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- เพิ่ม "เจ้าของบทความ" ตอนนี้เท่านั้น เพราะต้องมี mblog_users ให้ชี้ก่อน
-- NULL ได้ — บทความที่มีอยู่ก่อน Phase 3 ไม่มีเจ้าของ
ALTER TABLE mblog_articles
    ADD COLUMN author_id BIGINT UNSIGNED NULL AFTER category_id,
    ADD KEY idx_mblog_articles_author (author_id),
    ADD CONSTRAINT fk_mblog_articles_author FOREIGN KEY (author_id)
        REFERENCES mblog_users (id) ON DELETE SET NULL;
