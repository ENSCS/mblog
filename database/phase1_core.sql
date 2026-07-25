-- mBlog — Phase 1: บทความ + รูปภาพ + หมวดหมู่ + เมนู
-- รันไฟล์นี้ตอนเริ่ม Phase 1 ข้อสุดท้าย (สลับ includes/articles.php ให้อ่าน MySQL แทนไฟล์ json)
-- ลำดับตารางในไฟล์นี้ตั้งใจให้ตรงกับลำดับ FK dependency (categories/menu_items ก่อน เพราะไม่มีใครอ้างถึง,
-- แล้วค่อย articles ที่อ้าง categories, แล้วค่อย images ที่อ้าง articles)

-- ==========================================================
-- mblog_categories — หมวดหมู่บทความ (1 บทความ = 1 หมวด)
-- แทนที่ config/categories.php เดิม
-- ==========================================================
CREATE TABLE IF NOT EXISTS mblog_categories (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    slug VARCHAR(100) NOT NULL,
    name VARCHAR(100) NOT NULL,
    -- Color token, not a raw hex code — maps to a pre-defined, contrast-safe
    -- CSS class (.category-tag-<color> in assets/components.css). Keeps the
    -- badge color "data" (can change without a code deploy) while guaranteeing
    -- it always looks right, instead of letting any arbitrary hex be picked.
    color VARCHAR(20) NOT NULL DEFAULT 'gray',
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_mblog_categories_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ข้อมูลเดิมจาก config/categories.php ให้เว็บใช้งานต่อได้ทันทีหลังย้าย
INSERT INTO mblog_categories (slug, name, color, sort_order, created_at) VALUES
    ('general', 'ทั่วไป', 'gray', 1, NOW()),
    ('technology', 'เทคโนโลยี', 'blue', 2, NOW()),
    ('personal', 'บันทึกส่วนตัว', 'purple', 3, NOW());

-- ==========================================================
-- mblog_menu_items — เมนูเว็บ รองรับเมนูย่อยผ่าน parent_id (self-reference)
-- แทนที่ config/menu.php เดิม
-- ==========================================================
CREATE TABLE IF NOT EXISTS mblog_menu_items (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    parent_id INT UNSIGNED NULL,
    label VARCHAR(100) NOT NULL,
    href VARCHAR(255) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    KEY idx_mblog_menu_items_parent_sort (parent_id, sort_order),
    CONSTRAINT fk_mblog_menu_items_parent FOREIGN KEY (parent_id)
        REFERENCES mblog_menu_items (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ข้อมูลเดิมจาก config/menu.php
INSERT INTO mblog_menu_items (parent_id, label, href, sort_order) VALUES
    (NULL, 'บทความ', 'index.php', 1),
    (NULL, 'ร่าง', 'drafts.php', 2);

-- ==========================================================
-- mblog_articles — ตารางหลัก แทนที่ articles/*.json
-- ==========================================================
CREATE TABLE IF NOT EXISTS mblog_articles (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    slug VARCHAR(255) NOT NULL,
    title VARCHAR(255) NOT NULL,
    content LONGTEXT NOT NULL,
    excerpt VARCHAR(300) NULL,
    category_id INT UNSIGNED NULL,
    featured_image VARCHAR(500) NULL,
    status ENUM('draft', 'published') NOT NULL DEFAULT 'draft',
    type ENUM('post', 'page') NOT NULL DEFAULT 'post',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    published_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_mblog_articles_slug (slug),
    KEY idx_mblog_articles_status_published (status, published_at),
    KEY idx_mblog_articles_category (category_id),
    CONSTRAINT fk_mblog_articles_category FOREIGN KEY (category_id)
        REFERENCES mblog_categories (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==========================================================
-- mblog_images — รูปที่แทรกในเนื้อหา ผูกกับบทความด้วย article_id
-- เพื่อ backup/migrate เฉพาะบทความได้แม่นยำ (ไม่ต้อง parse HTML เดา path)
-- ไม่ผูกกับ mblog_articles.featured_image (เก็บเป็น path ตรงๆ) กัน circular FK
-- ==========================================================
CREATE TABLE IF NOT EXISTS mblog_images (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    article_id BIGINT UNSIGNED NOT NULL,
    path VARCHAR(500) NOT NULL,
    caption VARCHAR(255) NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_mblog_images_article (article_id),
    CONSTRAINT fk_mblog_images_article FOREIGN KEY (article_id)
        REFERENCES mblog_articles (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==========================================================
-- mblog_slug_redirects — slug เก่าของบทความ (เก็บตอนแก้ slug ผ่าน editor)
-- ผูกด้วย article_id ไม่ใช่ slug ใหม่ตรงๆ กันเป็นโซ่ต่อกันถ้าเปลี่ยนหลายรอบ —
-- redirect จะไปดู slug ปัจจุบันของบทความสดๆ เสมอ ไม่ว่าจะเปลี่ยนมากี่รอบ
-- ==========================================================
CREATE TABLE IF NOT EXISTS mblog_slug_redirects (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    old_slug VARCHAR(255) NOT NULL,
    article_id BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_mblog_slug_redirects_old_slug (old_slug),
    KEY idx_mblog_slug_redirects_article (article_id),
    CONSTRAINT fk_mblog_slug_redirects_article FOREIGN KEY (article_id)
        REFERENCES mblog_articles (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
