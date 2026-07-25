# คู่มือ Sync โปรเจกต์ mBlog ขึ้น GitHub ผ่าน Terminal

## ข้อมูล Repo

| รายการ | ค่า |
|--------|-----|
| Project path | `/Applications/XAMPP/xamppfiles/htdocs/z/mblog` |
| GitHub repo | `https://github.com/ENSCS/mblog.git` |
| Branch | `main` |
| Visibility | Public (โครงโค้ดเท่านั้น — เนื้อหาจริงจะแยกไป repo private ตามแผนใน `PLANNING.md`) |

---

## การ Sync ทุกครั้ง (3 ขั้นตอน)

### Step 1 — เข้าโฟลเดอร์โปรเจกต์

```bash
cd /Applications/XAMPP/xamppfiles/htdocs/z/mblog
```

### Step 2 — ดึงการเปลี่ยนแปลงจาก GitHub ก่อน (ป้องกัน conflict)

```bash
git pull origin main
```

### Step 3 — เช็คสถานะ แล้ว Push ไฟล์ที่แก้ไขขึ้น GitHub

```bash
git status
git add .
git commit -m "update"
git push origin main
```

> **ต่างจากคู่มือ Obsidian ตรงนี้**: repo นี้เป็น public และมี `.gitignore` กันไฟล์เนื้อหาจริง (`articles/*.json`, `uploads/*`, `config.php`) ไม่ให้หลุดขึ้นเว็บสาธารณะ — ก่อน `git add .` ทุกครั้งควรดูผล `git status` ให้แน่ใจว่าไม่มีไฟล์แปลกๆ ติดไปด้วย (ไม่ใช่ขั้นตอน optional แบบใน Obsidian vault ที่เป็น private)

---

## Commit Message แนะนำ

| สถานการณ์ | ข้อความ |
|-----------|---------|
| แก้ไขทั่วไป | `git commit -m "update"` |
| เพิ่มฟีเจอร์ใหม่ | `git commit -m "add draft/published status"` |
| แก้บั๊ก | `git commit -m "fix image caption overflow on mobile"` |
| อัปเดตแผนงาน | `git commit -m "update PLANNING.md roadmap"` |

---

## เช็คสถานะก่อน Push

```bash
# ดูว่าไฟล์ไหนเปลี่ยนแปลงบ้าง — เช็คว่าไม่มีไฟล์เนื้อหาจริงหลุดมาด้วย
git status

# ดู history การ commit
git log --oneline
```

---

## PAT (Personal Access Token)

เครื่องนี้ตั้ง `credential.helper=osxkeychain` ไว้ที่ระดับระบบอยู่แล้ว (เหมือนที่ตั้งไว้ตอนทำ Obsidian sync) ปกติจะไม่ถูกถามรหัสผ่านซ้ำ — Mac จำ PAT ให้จาก Keychain อัตโนมัติ

ถ้าวันไหนถูกถาม password อีกครั้ง (เช่น PAT หมดอายุ) ให้ใส่ PAT แทน:
- ไปสร้างใหม่ที่: GitHub → Settings → Developer settings → Personal access tokens → Tokens (classic)
- Scope ที่ต้องติ๊ก: ✅ repo

---

## Quick Reference (Copy-Paste ได้เลย)

เช็คก่อนเสมอ (ห้ามข้ามขั้นนี้ เพราะเป็น public repo):

```bash
cd /Applications/XAMPP/xamppfiles/htdocs/z/mblog && git pull origin main && git status
```

ดู `git status` แล้วโอเค ไม่มีไฟล์แปลกปลอม ค่อยรัน:

```bash
git add . && git commit -m "update" && git push origin main
```

---

## หมายเหตุ

ถ้าทำงานคนเดียวจากเครื่องเดียว `git pull` มักไม่มีอะไรให้ดึง (ไม่มีใครอื่น push เข้ามา) แต่ใส่ไว้เป็นนิสัยที่ดี เผื่อวันหนึ่งมีคนที่สองช่วยดูแลโค้ด หรือแก้จากหลายเครื่อง
