# mBlog

เว็บบล็อกที่สร้างเองด้วย PHP ล้วนๆ (ไม่ใช้ WordPress) กำลังพัฒนาแบบเริ่มจากส่วนเล็กๆ ก่อน ดูแผนงานทั้งหมดได้ที่ [PLANNING.md](PLANNING.md)

## ตอนนี้ทำอะไรได้แล้ว

ระบบเขียนบทความแบบ WYSIWYG (เก็บข้อมูลเป็นไฟล์ ยังไม่ต่อฐานข้อมูล):

- เขียน/แก้ไข/อ่านบทความ ([editor.php](editor.php), [article.php](article.php), [index.php](index.php))
- จัดฟอร์แมตข้อความ: หัวข้อ H1–H3, ตัวหนา/เอียง/ขีดเส้นใต้, สีตัวอักษร, จัดกึ่งกลาง/ซ้าย/ขวา, bullet/numbered list
- แทรกรูปภาพ พร้อมลากปรับขนาด, จัดตำแหน่งซ้าย/กลาง/ขวา, ใส่คำบรรยายใต้ภาพ
- ใส่ลิงก์ได้ทั้งบนข้อความและบนรูปภาพ + auto-link (พิมพ์/วาง URL แล้วกลายเป็นลิงก์ให้เอง)
- Blockquote, code block พร้อม syntax highlighting (สไตล์ GitHub) และปุ่มคัดลอกโค้ด
- เส้นคั่นบทความ
- รองรับมือถือ (responsive)
- ระบบร่าง/เผยแพร่ (draft/published) แยกปุ่ม "บันทึกร่าง"/"เผยแพร่" ในตัวเขียน พร้อมหน้ารายการร่าง ([drafts.php](drafts.php))
- หมวดหมู่แบบง่าย (เลือกจาก dropdown ตอนเขียนบทความ)
- SEO พื้นฐาน: สรุปสั้น (excerpt) + featured image (อัปโหลดเองหรือใช้รูปแรกในเนื้อหาอัตโนมัติ), meta description, Open Graph/Twitter Card, canonical tag, JSON-LD, [sitemap.php](sitemap.php), [robots.txt](robots.txt)
- หน้า error กลาง (404/500) ที่หน้าตาเข้ากับเว็บ + log ข้อผิดพลาดลงไฟล์ + สคริปต์ backup ข้อมูล พร้อม retention ([scripts/backup.php](scripts/backup.php))

## ยังไม่มี

ล็อกอิน/สิทธิ์ผู้ใช้, ฐานข้อมูล, แท็กเต็มรูปแบบ, คอมเมนต์, ค้นหา, สถิติ — อยู่ในแผนแล้ว ดูรายละเอียดที่ [PLANNING.md](PLANNING.md)

## รันโปรเจกต์

1. คัดลอก `config.example.php` เป็น `config.php` (ไฟล์นี้ไม่ถูก commit เข้า git)
2. ใช้ PHP + Apache (เช่น XAMPP) ชี้ document root มาที่โฟลเดอร์นี้ แล้วเปิด `index.php`
