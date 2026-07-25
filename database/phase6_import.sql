-- mBlog — Phase 6: ระบบออโต้นำเข้าบทความ (เช่น สรุป YouTube รายวัน)
-- รันไฟล์นี้ตอนเริ่ม Phase 6 (ต้องรัน phase1_core.sql ไปก่อนแล้ว)
-- ไม่มีตารางใหม่ — แค่เพิ่มคอลัมน์เก็บ URL ต้นฉบับไว้อ้างอิง/ให้เครดิตตามที่ PLANNING.md ระบุ
-- บทความที่นำเข้าทางนี้ต้องบันทึกเป็น status='draft' เสมอ (เช็คในโค้ดตอน insert ไม่ใช่ constraint ของ DB)

ALTER TABLE mblog_articles
    ADD COLUMN source_url VARCHAR(500) NULL AFTER published_at;
