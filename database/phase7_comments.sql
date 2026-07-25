-- mBlog — Phase 7: คอมเมนต์/รีวิว
-- รันไฟล์นี้ตอนเริ่ม Phase 7 (ต้องรัน phase1_core.sql และ phase3_users.sql ไปก่อนแล้ว
-- เพราะ moderation ต้องมีระบบล็อกอิน)

CREATE TABLE IF NOT EXISTS mblog_comments (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    article_id BIGINT UNSIGNED NOT NULL,
    parent_id BIGINT UNSIGNED NULL,
    author_name VARCHAR(100) NOT NULL,
    author_email VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    status ENUM('pending', 'approved', 'spam') NOT NULL DEFAULT 'pending',
    ip_address VARCHAR(45) NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_mblog_comments_article_status (article_id, status),
    KEY idx_mblog_comments_parent (parent_id),
    CONSTRAINT fk_mblog_comments_article FOREIGN KEY (article_id)
        REFERENCES mblog_articles (id) ON DELETE CASCADE,
    CONSTRAINT fk_mblog_comments_parent FOREIGN KEY (parent_id)
        REFERENCES mblog_comments (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- หมายเหตุ: Review+rating ของหน้าคอร์สเป็นตารางแยกอีกอัน ไม่รวมไว้ที่นี่
-- เพราะระบบขายคอร์สอยู่นอกขอบเขต Roadmap นี้ (ดู PLANNING.md หัวข้อ "นอกขอบเขต")
