-- mBlog — Phase 4: แท็ก (many-to-many กับบทความ)
-- รันไฟล์นี้ตอนเริ่ม Phase 4 (ต้องรัน phase1_core.sql ไปก่อนแล้ว)

CREATE TABLE IF NOT EXISTS mblog_tags (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    slug VARCHAR(100) NOT NULL,
    name VARCHAR(100) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_mblog_tags_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Junction table — 1 บทความมีได้หลายแท็ก, 1 แท็กใช้ได้หลายบทความ
CREATE TABLE IF NOT EXISTS mblog_article_tag (
    article_id BIGINT UNSIGNED NOT NULL,
    tag_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (article_id, tag_id),
    KEY idx_mblog_article_tag_tag (tag_id),
    CONSTRAINT fk_mblog_article_tag_article FOREIGN KEY (article_id)
        REFERENCES mblog_articles (id) ON DELETE CASCADE,
    CONSTRAINT fk_mblog_article_tag_tag FOREIGN KEY (tag_id)
        REFERENCES mblog_tags (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
