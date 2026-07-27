-- mBlog — รวมฟีเจอร์บทความที่คุยกันไว้ก่อนทำระบบ backup (2026-07-28)
-- ไม่ใช้ชื่อ "phaseN" เพราะเลข 10 ถูกจับจองไว้แล้วใน PLANNING.md (Phase 10 = Production
-- readiness) — ไฟล์นี้คือ Phase 11 ใน Roadmap (ดูหัวข้อ 13/Phase 11 ใน PLANNING.md)
-- รันไฟล์นี้ตอนเริ่ม Phase 11 (ต้องรัน phase1_core.sql ไปก่อนแล้ว)
--
-- สโคปที่คุยกันไว้ (คุยกัน 2026-07-28 ก่อนวางระบบ backup ให้ schema solid ก่อน):
--   1. Private post            -> เพิ่มค่า 'private' ใน status
--   2. Scheduled publishing    -> เพิ่มค่า 'scheduled' ใน status (ใช้ published_at เดิม)
--   3. Post expiration         -> เพิ่ม expires_at (cron ที่เช็ค/flip สถานะเป็นงานแยกทีหลัง ยังไม่ทำตอนนี้)
--   4. Revision history        -> ไม่ทำ (ตัดสินใจแล้ว)
--   5. Sticky/ปักหมุด          -> ไม่มีในไฟล์นี้ ใช้ mblog_settings key 'sticky_article_ids'
--                                 (ค่าเป็น JSON array ของ article id ตาม pattern ของ WP
--                                 sticky_posts ใน wp_options) แทนคอลัมน์ใน mblog_articles
--   6. SEO override ต่อบทความ  -> เพิ่ม seo_title / seo_description / seo_noindex
--   7. Hierarchical pages      -> เพิ่ม parent_id (self-reference, ใช้เฉพาะ type='page')
--   8. Custom fields (meta)    -> ไม่ทำ ไม่มี use case จริงรองรับตอนนี้ (รอจนกว่าจะมีความ
--                                 ต้องการแบบ flexible อย่างน้อย 2 อย่างขึ้นไปค่อยทำเป็นตารางแยก)
--
-- เรื่อง "รหัสก่อนเข้าอ่าน" (password-protect บทความ) อยู่คนละไฟล์ (phase11) เพราะเป็นฟีเจอร์
-- คนละธรรมชาติ (ต้องมีกลไก unlock/session/cookie ฝั่งโค้ดเพิ่ม ไม่ใช่แค่ schema เฉยๆ)

-- ==========================================================
-- 1+2. status ENUM ขยายเพิ่ม 'private' และ 'scheduled'
-- 'published' ยังคงความหมายเดิม (published_at <= NOW() แล้วจริง) — 'scheduled' คือ
-- published_at ตั้งไว้เป็นอนาคต ต้องมี query ฝั่งโค้ดที่ query หน้าเว็บสาธารณะกรอง
-- "status='published' AND published_at <= NOW()" เพิ่ม (ตอนนี้โค้ดเช็คแค่ status='published'
-- เฉยๆ — ดู includes/articles.php getArticle()/fetchArticles() ต้องแก้ตอนเริ่มทำฟีเจอร์นี้จริง)
-- 'private' คือบทความที่มี URL เปิดได้ถ้ารู้ลิงก์+login แอดมิน แต่ไม่โผล่ใน list/RSS/
-- search/sitemap สาธารณะเลย (ต่างจาก draft ตรงที่ยังมี published_at ของจริงได้)
-- ==========================================================
ALTER TABLE mblog_articles
    MODIFY COLUMN status ENUM('draft', 'published', 'private', 'scheduled') NOT NULL DEFAULT 'draft';

-- ==========================================================
-- 3. Post expiration — จุดจบของช่วงเวลาที่เผยแพร่ (published_at เก็บได้แค่จุดเริ่ม)
-- NULL = ไม่มีวันหมดอายุ (พฤติกรรมเดิมของบทความทุกอันก่อนมีคอลัมน์นี้)
-- การ auto-flip สถานะตอนหมดอายุเป็นงาน cron ฝั่งแอป ยังไม่ได้ทำในไฟล์นี้
-- ==========================================================
ALTER TABLE mblog_articles
    ADD COLUMN expires_at DATETIME NULL AFTER published_at;

-- ==========================================================
-- 6. SEO override ต่อบทความ — ค่าเริ่มต้น NULL แปลว่า "ใช้ auto-generate เหมือนเดิม"
-- (ดู article.php ตอนนี้ที่ดึง meta description จาก articleExcerpt() เสมอ)
-- seo_noindex แยกจาก status เพราะบทความ published ปกติก็อยากตั้ง noindex ได้
-- (เช่น บทความซ้ำ/บทความทดสอบที่อยากให้คนเปิดลิงก์ตรงได้แต่ไม่อยากให้ google index)
-- ==========================================================
ALTER TABLE mblog_articles
    ADD COLUMN seo_title VARCHAR(255) NULL AFTER title,
    ADD COLUMN seo_description VARCHAR(300) NULL AFTER excerpt,
    ADD COLUMN seo_noindex TINYINT(1) NOT NULL DEFAULT 0 AFTER seo_description;

-- ==========================================================
-- 7. Hierarchical pages — self-reference แบบเดียวกับ mblog_menu_items.parent_id
-- ใช้เฉพาะแถวที่ type='page' เท่านั้น (ไม่บังคับด้วย constraint เพราะ MySQL เช็ค cross-column
-- condition แบบนี้ตรงๆ ไม่ได้ — คุมที่โค้ด editor.php ตอน validate แทน)
-- กันเวียนเป็นวง (parent ชี้กลับมาที่ตัวเอง/ลูกตัวเอง) ก็ต้องเช็คที่โค้ดเช่นกัน ไม่ใช่ระดับ DB
-- ==========================================================
ALTER TABLE mblog_articles
    ADD COLUMN parent_id BIGINT UNSIGNED NULL AFTER category_id,
    ADD KEY idx_mblog_articles_parent (parent_id),
    ADD CONSTRAINT fk_mblog_articles_parent FOREIGN KEY (parent_id)
        REFERENCES mblog_articles (id) ON DELETE SET NULL;

-- ==========================================================
-- 5. Sticky — ไม่มี ALTER TABLE ในไฟล์นี้ตั้งใจ เก็บที่ mblog_settings แทน
-- ค่า value เป็น JSON array เช่น '[45,102]' — id ที่ไม่มีอยู่แล้วจะแค่ไม่โชว์อะไร
-- (id ไม่ถูก MySQL เอามาใช้ซ้ำ ไม่มีความเสี่ยงชี้ผิดบทความ) แต่ตอนลบบทความถาวรใน
-- bulkPermanentlyDeleteArticles() ควรเพิ่ม logic เคลียร์ id ออกจาก array นี้ให้สะอาดด้วย
-- ==========================================================
INSERT INTO mblog_settings (setting_key, value) VALUES ('sticky_article_ids', '[]')
ON DUPLICATE KEY UPDATE value = value;
