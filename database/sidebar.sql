-- mBlog — sidebar items (แบนเนอร์/ประกาศ/ป้ายโฆษณา/ป้ายลิงก์ ข้าง sidebar)
-- ตารางแยกจาก mblog_articles โดยตั้งใจ (ดูเหตุผลที่คุยกันไว้ก่อนทำ) — sidebar item
-- ไม่ใช่หน้าที่มี URL ของตัวเอง เลยไม่ต้องมี slug/status(draft-published)/SEO/
-- tag/category/redirect เหมือนบทความ ใช้ is_active+sort_order ตรงงานกว่า

CREATE TABLE IF NOT EXISTS mblog_sidebar_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    content LONGTEXT NULL,
    image VARCHAR(500) NULL,
    link_url VARCHAR(500) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
