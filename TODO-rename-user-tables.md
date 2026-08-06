# TODO: เปลี่ยนชื่อตาราง mblog_users → mblog_staff, mblog_readers → mblog_users

สถานะ: **ทำเสร็จแล้ว** (2026-08-06) — RENAME TABLE รันแล้ว, แก้โค้ดทั้ง 6 ไฟล์แล้ว (ไม่ได้ rename ไฟล์ .php/.sql ตามที่แนะนำไว้ในขั้นตอนที่ 3 เพื่อลดความเสี่ยง), ทดสอบผ่านเบราว์เซอร์แล้ว (login, users.php list, setup.php 404) — grep ยืนยันไม่มี `mblog_users`/`mblog_readers` เหลือค้างนอกเหนือจาก PLANNING.md/BUILT.md ที่ตั้งใจพักไว้ตามขั้นตอนที่ 3

## บริบท/เหตุผล

ตอนนี้ (หลัง Phase 3 login/สิทธิ์เสร็จ):
- `mblog_users` = บัญชีทีมงานเว็บ (admin/editor/author) — ชื่อกว้างไป ไม่บอกว่าเป็นคนใน
- `mblog_readers` = ผู้ชมทั่วไป (ตารางเปล่า ยังไม่มีโค้ดใช้งานจริง รอฟีเจอร์คอมเมนต์/บทความล็อกรหัสในอนาคต)

ตกลงกันแล้วว่าจะสลับชื่อให้สื่อความหมายกว่าเดิม:
- `mblog_users` → **`mblog_staff`**
- `mblog_readers` → **`mblog_users`**

เหตุผล: "users" ทั่วไปมักหมายถึงกลุ่มคนใช้งานเว็บในวงกว้าง (ผู้ชม/สมาชิก) ส่วน "staff" สื่อชัดว่าเป็นคนดูแลเว็บ ตรงกับ role admin/editor/author ที่มีอยู่แล้ว

## ขั้นตอนที่ 1 — Migrate ฐานข้อมูลจริง

รันคำสั่งเดียว (MySQL `RENAME TABLE` สลับชื่อ 2 ตารางพร้อมกันแบบ atomic ในสเตทเมนต์เดียว ปลอดภัย ไม่มีช่วงชนชื่อระหว่างทาง — FK constraint `fk_mblog_articles_author` ที่ชี้ไปตาราง users เดิมจะตามไปเองอัตโนมัติเพราะ InnoDB ผูก FK ด้วย internal table id ไม่ใช่ชื่อ):

```sql
RENAME TABLE mblog_users TO mblog_staff, mblog_readers TO mblog_users;
```

**ทดสอบยืนยันหลังรัน:**
```sql
SELECT COUNT(*) FROM mblog_staff;   -- ควรได้แถวเดิมที่เคยอยู่ใน mblog_users (เช่น admin@mblog.test)
SELECT COUNT(*) FROM mblog_users;   -- ควรได้ 0 (คือ mblog_readers เดิม ยังว่าง)
SHOW CREATE TABLE mblog_articles;   -- เช็คว่า FK ยังชี้ไปตารางที่ถูกต้อง (มี author_id constraint อยู่)
```

ไม่ต้องแตะ FK constraint เอง ไม่ต้อง DROP/re-ADD — MySQL จัดการให้อัตโนมัติตอน RENAME TABLE

## ขั้นตอนที่ 2 — แก้โค้ดทุกจุด (7 ไฟล์ ยืนยันด้วย grep แล้วว่าครบ ไม่มีจุดอื่นอ้างอิงชื่อตารางนี้)

### `database/phase3_users.sql`
ไฟล์นี้เป็น migration ที่รันไปแล้วครั้งเดียวบน DB จริง (ไม่ได้ re-run ซ้ำ) — แก้เนื้อไฟล์ให้ตรงกับสถานะใหม่ไว้เป็นหลักฐาน/สำหรับติดตั้งเครื่องใหม่ในอนาคต:
- บรรทัด 6: `CREATE TABLE IF NOT EXISTS mblog_users` → `mblog_staff`
- บรรทัด 13: `UNIQUE KEY uq_mblog_users_email` → `uq_mblog_staff_email`
- บรรทัด 18: `ALTER TABLE mblog_users MODIFY COLUMN role ...` → `ALTER TABLE mblog_staff MODIFY COLUMN role ...`
- บรรทัด 20: คอมเมนต์ "ต้องมี mblog_users ให้ชี้ก่อน" → "mblog_staff"
- บรรทัด 26: `REFERENCES mblog_users (id)` → `REFERENCES mblog_staff (id)`

**พิจารณาเปลี่ยนชื่อไฟล์ด้วย** — `database/phase3_users.sql` → `database/phase3_staff.sql` (ให้สอดคล้องชื่อตาราง) — ถ้าเปลี่ยนต้องอัปเดตทุกจุดที่อ้างชื่อไฟล์นี้ด้วย (เช่น PLANNING.md ถ้ามีลิงก์ถึง — grep `phase3_users.sql` ก่อนเปลี่ยนชื่อไฟล์)

### `database/phase3b_readers.sql`
- บรรทัด 1: คอมเมนต์ "แยกจาก mblog_users โดยตั้งใจ" → "แยกจาก mblog_staff โดยตั้งใจ"
- บรรทัด 10: `CREATE TABLE IF NOT EXISTS mblog_readers` → `mblog_users`
- บรรทัด 16: `UNIQUE KEY uq_mblog_readers_email` → `uq_mblog_users_email`

**พิจารณาเปลี่ยนชื่อไฟล์ด้วย** — `database/phase3b_readers.sql` → `database/phase3b_users.sql`

### `includes/auth.php`
- บรรทัด 3-4: คอมเมนต์อธิบาย `mblog_users`/`mblog_readers` → สลับเป็น `mblog_staff`/`mblog_users`
- บรรทัด 57: `SELECT id, email, role, created_at FROM mblog_users WHERE id = ?` → `FROM mblog_staff WHERE id = ?` (อยู่ใน `currentUser()`)

### `includes/users.php`
ไฟล์นี้ทั้งไฟล์เป็น data layer ของบัญชีทีมงาน — **พิจารณาเปลี่ยนชื่อไฟล์เป็น `includes/staff.php`** ด้วยเพื่อให้สอดคล้อง (ถ้าเปลี่ยน ต้องแก้ทุกจุดที่ `require`/`require_once` ไฟล์นี้ — ดูรายการด้านล่าง "ไฟล์ที่ require includes/users.php")
- บรรทัด 2: คอมเมนต์ "staff accounts (mblog_users: ...)" → "mblog_staff"
- บรรทัด 17: `FROM mblog_users ORDER BY created_at` → `FROM mblog_staff ...`
- บรรทัด 25: `SELECT COUNT(*) FROM mblog_users` → `FROM mblog_staff`
- บรรทัด 30: `FROM mblog_users WHERE id = ?` → `FROM mblog_staff ...`
- บรรทัด 39: `FROM mblog_users WHERE email = ? AND id != ?` → `FROM mblog_staff ...`
- บรรทัด 47: `INSERT INTO mblog_users (...)` → `INSERT INTO mblog_staff (...)`
- บรรทัด 55: `UPDATE mblog_users SET email = ?, role = ?` → `UPDATE mblog_staff ...`
- บรรทัด 64: `UPDATE mblog_users SET password_hash = ?` → `UPDATE mblog_staff ...`
- บรรทัด 70: `DELETE FROM mblog_users WHERE id = ?` → `DELETE FROM mblog_staff ...`

ถ้าเปลี่ยนชื่อไฟล์เป็น `includes/staff.php` — **ไฟล์ที่ require ไฟล์นี้ต้องแก้ตาม** (ยืนยันแล้วด้วย grep ทั้ง `includes/users.php` แบบเต็ม path และ `/users.php` แบบ relative — เพราะ `includes/admin-nav.php` เรียกแบบ relative path `__DIR__ . '/users.php'` ไม่ใช่เต็ม path เลยต้อง grep 2 แบบ): `includes/admin-nav.php` (บรรทัด 7, `require_once __DIR__ . '/users.php'`), `users.php` (บรรทัด 3), `setup.php` (บรรทัด 3) — grep `require.*users\.php` ทั้งโปรเจกต์อีกรอบตอนลงมือทำจริงเผื่อมีจุดอื่นเพิ่มมาทีหลัง

### `scripts/create-admin.php`
- บรรทัด 2: คอมเมนต์ "สร้างบัญชีทีมงาน (mblog_users)" → "(mblog_staff)"
- บรรทัด 33: `SELECT id FROM mblog_users WHERE email = ?` → `FROM mblog_staff ...`
- บรรทัด 40: `INSERT INTO mblog_users (...)` → `INSERT INTO mblog_staff (...)`

### `login.php`
- บรรทัด 24: `SELECT id, password_hash FROM mblog_users WHERE email = ?` → `FROM mblog_staff ...`

### `setup.php`
- บรรทัด 6, 10: คอมเมนต์อธิบาย "mblog_users ยังว่าง"/"ลบทุกแถวใน mblog_users" → "mblog_staff"
- โค้ดจริงไม่มี SQL ตรงๆ ในไฟล์นี้ (เรียกผ่าน `countUsers()`/`createUser()` จาก includes/users.php) — แก้แค่คอมเมนต์

### ไฟล์ที่ **ไม่ต้องแตะ** (ใช้ผ่านฟังก์ชันเสมอ ไม่มี SQL ตรงๆ อ้างชื่อตาราง)
`users.php` (หน้าเว็บ), `includes/admin-nav.php`, `api/save.php`, `editor.php` — ยืนยันแล้วด้วย grep ว่าไม่มี `mblog_users`/`mblog_readers` ปรากฏตรงๆ ในไฟล์เหล่านี้

## ขั้นตอนที่ 3 — อัปเดตเอกสาร (ถ้าต้องการ — ไม่บังคับ ทำทีหลังได้)

`PLANNING.md`/`BUILT.md` มีพูดถึง `mblog_users` และ `database/phase3_users.sql` ในหลายจุด (หัวข้อ Phase 3, ตารางฐานข้อมูล) — **ตามหลักที่เคยตกลงกันไว้ ห้ามแก้เอกสารพวกนี้เองโดยไม่มีคนสั่งชัดเจน** รอให้สั่งทีหลังตอนจะอัปเดตเอกสาร Phase 3 ทั้งชุด (น่าจะทำพร้อมกับตอนอัปเดต BUILT.md ที่ค้างอยู่จาก Phase 3 เดิมด้วย)

## ลำดับการทำจริงที่แนะนำ

1. Backup DB ก่อนเสมอ (`mysqldump` หรือใช้ `backup.php` ที่มีอยู่แล้วในเว็บ)
2. รัน `RENAME TABLE` (ขั้นตอนที่ 1)
3. แก้โค้ดทุกไฟล์ (ขั้นตอนที่ 2) — **ตัดสินใจก่อนว่าจะเปลี่ยนชื่อไฟล์ `includes/users.php`/`database/phase3*.sql` ด้วยหรือไม่** (ถ้าไม่อยากเสี่ยง ให้แก้แค่เนื้อหา SQL ข้างในพอ ไม่ต้อง rename ไฟล์ก็ได้ ลดจุดที่ต้องแก้ตาม)
4. ทดสอบผ่านเบราว์เซอร์จริง: login ด้วย admin เดิมยังเข้าได้ปกติ, `users.php` list/create/edit/delete ยังทำงาน, `setup.php` ยัง 404 ตามปกติ (เพราะ mblog_staff ยังมีแถว), `scripts/create-admin.php` สร้างบัญชีใหม่ได้
5. `grep -rn "mblog_users\|mblog_readers"` ทั้งโปรเจกต์อีกรอบ ต้องไม่เจอเหลือเลย (ยกเว้นในเอกสาร PLANNING.md/BUILT.md ที่ตั้งใจพักไว้ตามขั้นตอนที่ 3)
