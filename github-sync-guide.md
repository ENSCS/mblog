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

## ย้ายเครื่องทำงาน (GitHub คือตัวกลางที่อัปเดตสุดเสมอ)

ทำงานหลายเครื่อง (เช่นสลับ Mac คนละตัว) ให้ยึดหลักว่า **GitHub คือความจริงชุดล่าสุดเสมอ** — ทุกครั้งที่ย้ายมาเริ่มงานที่เครื่องไหน ให้ดึงลงมาทับก่อนเริ่มแก้อะไรทั้งนั้น

```bash
cd /Applications/XAMPP/xamppfiles/htdocs/z/mblog
git pull origin main --no-rebase
```

`--no-rebase` คือสั่งให้ merge เข้าด้วยกันตรงๆ (ไม่ใช่เรียง history ใหม่) เผื่อเครื่องที่เพิ่งย้ายมามี commit ค้างที่ยังไม่ได้ push ไว้จากรอบก่อน

**ถ้าเจอ `error: Need to specify how to reconcile divergent branches`** แปลว่าเครื่องนี้มี commit ค้างที่ origin ไม่มี (เช่น commit ก่อนหน้าที่ push ไม่สำเร็จ) รันคำสั่งข้างบนซ้ำอีกครั้งหลังตั้งค่า reconcile ไว้ก่อน:

```bash
git config pull.rebase false
git pull origin main
```

ถ้า merge แล้วขึ้น `CONFLICT` ในไฟล์ไหน (เช่น `PLANNING.md`) — เนื่องจาก GitHub คือตัวกลางที่อัปเดตสุด ให้ **ยึดฝั่ง origin เป็นหลักเสมอ**:

```bash
git checkout --theirs <ชื่อไฟล์ที่ conflict>
git add <ชื่อไฟล์ที่ conflict>
git commit --no-edit
```

> ถ้าอยากชัวร์ว่าเครื่องนี้ตรงกับ GitHub เป๊ะๆ ไม่สนใจว่าจะมี commit ค้างอะไรอยู่หรือไม่ ใช้คำสั่งนี้แทนได้เลย (แรงกว่า — **ลบ commit/การแก้ไขที่ยังไม่ได้ push ทิ้งถาวร** ใช้เฉพาะตอนมั่นใจว่าไม่มีงานค้างที่อยากเก็บ):
> ```bash
> git fetch origin
> git reset --hard origin/main
> ```

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
