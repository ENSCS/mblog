-- mBlog — ถังขยะบทความ (ทำตอนสร้าง manage-articles.php)
-- รันไฟล์นี้ตอนเริ่มทำระบบถังขยะ (ต้องรัน phase1_core.sql ไปก่อนแล้ว)
--
-- ใช้คอลัมน์ deleted_at แยกต่างหาก แทนการเพิ่มค่า 'trash' เข้าไปใน status ENUM
-- เพราะ status (draft/published) ต้องคงค่าเดิมไว้ตอนอยู่ในถังขยะ — ไม่งั้นตอนกู้คืน
-- จะไม่รู้ว่าบทความนั้นควรกลับไปเป็นร่างหรือเผยแพร่แล้วกันแน่

ALTER TABLE mblog_articles
    ADD COLUMN deleted_at DATETIME NULL AFTER published_at,
    ADD KEY idx_mblog_articles_deleted (deleted_at);
