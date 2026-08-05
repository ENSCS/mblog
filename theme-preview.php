<?php
// Component preview page — renders real UI pieces (buttons, cards, form
// fields, badges, links) using the actual site CSS classes, so editing
// colors in assets/base.css can be checked for overall look in one place
// instead of hunting through real pages. Complements color-reference.php
// (which lists hex values + usage in table form) with a page that shows
// every raw palette color and role token actually painted on components.
require __DIR__ . '/includes/articles.php';
require __DIR__ . '/includes/admin-nav.php';

$rawAccents = [
    ['--color-primary', '#cc785c'],
    ['--color-primary-hover', '#a9583e'],
    ['--color-primary-disabled', '#e6dfd8'],
    ['--color-accent-teal', '#5db8a6'],
    ['--color-accent-amber', '#e8a55a'],
    ['--color-on-primary', '#ffffff'],
];

$pageTitle = 'พรีวิวองค์ประกอบ';
$extraHead = <<<HTML
<style>
  .preview-swatch-strip { display: flex; flex-wrap: wrap; gap: 10px; }
  .preview-swatch { width: 96px; }
  .preview-swatch-color { height: 40px; border-radius: var(--radius-sm); border: 1px solid var(--card-border); }
  .preview-swatch-name { font-family: var(--font-mono); font-size: 10.5px; color: var(--text-muted); margin-top: 4px; word-break: break-all; }
  .preview-row { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; }
  .preview-alert { padding: 12px 16px; border-radius: var(--radius-md); font-size: 14px; }
  .preview-dropdown-demo { display: inline-block; min-width: 200px; padding: 14px 6px 6px; background: var(--dropdown-bg); border: 1px solid var(--card-border); border-radius: var(--radius-md); box-shadow: var(--dropdown-shadow); }
  .preview-dropdown-demo a { display: block; padding: 8px 10px; border-radius: var(--radius-sm); color: var(--text-primary); font-weight: 500; text-decoration: none; }
  .preview-dropdown-demo a:hover { background: var(--btn-secondary-bg-hover); }
  .preview-note { color: var(--text-muted); font-size: 13px; margin-top: -4px; }
  .preview-surface-canvas { background: var(--page-bg); border: 1px dashed var(--card-border); border-radius: var(--radius-md); padding: 16px; }
  .preview-surface-card { background: var(--card-bg); border: 1px solid var(--card-border); border-radius: var(--radius-md); padding: 14px; margin-top: 10px; }
  .preview-surface-soft { background: var(--btn-secondary-bg); border-radius: var(--radius-md); padding: 12px; margin-top: 10px; }
  .preview-hover-label { font-size: 11px; color: var(--text-muted); margin-top: 4px; text-align: center; }
</style>
HTML;
$topbarActions = adminTopbarActions();
$showAdminSidebar = true;
$layout = render_header(compact('pageTitle', 'extraHead', 'topbarActions', 'showAdminSidebar'));
?>
  <h1 class="article-title">พรีวิวองค์ประกอบ</h1>
  <p style="color:var(--text-muted); margin-top:-8px;">
    องค์ประกอบ UI จริงของเว็บ (ปุ่ม/การ์ด/ฟอร์ม/ป้าย/ลิงก์) รวมไว้หน้าเดียวเพื่อดูภาพรวมความสวยงามเวลาปรับสี —
    แถบเมนูบนสุด/footer/ชื่อแบรนด์ของหน้านี้เองก็ใช้สีจริงอยู่แล้ว ไม่ต้องจำลองแยก
    ดูรายชื่อตัวแปรสีแบบละเอียดพร้อมจุดใช้งานได้ที่ <a href="color-reference.php">ชุดสีของเว็บ</a>
  </p>

  <div class="card">
    <h2 class="admin-section-title" style="margin-top:0;">พื้นหลัง (Surfaces)</h2>
    <p class="preview-note">พื้นหลังทั้งหน้านี้ (นอกกล่องเส้นประด้านล่าง) มาจาก <code>--page-bg → --color-canvas</code> อยู่แล้ว — ไล่ระดับต่อจากนี้ทีละชั้น:</p>
    <div class="preview-surface-canvas">
      <div class="preview-note" style="margin-top:0;">กล่องนี้ = <code>--page-bg → --color-canvas</code> (เส้นประ = <code>--card-border → --color-hairline</code>)</div>
      <div class="preview-surface-card">
        <div class="preview-note" style="margin-top:0;">กล่องนี้ = <code>--card-bg → --color-surface-card</code> (เส้นขอบ = <code>--card-border → --color-hairline</code>)</div>
        <div class="preview-surface-soft">
          <div class="preview-note" style="margin-top:0;">กล่องนี้ = <code>--btn-secondary-bg → --color-surface-soft</code></div>
        </div>
      </div>
    </div>
  </div>

  <div class="card">
    <h2 class="admin-section-title" style="margin-top:0;">สีเน้น (Raw Palette accent)</h2>
    <p class="preview-note">ไม่มีคู่ dark แยก — ค่าเดียวกันทั้ง 2 ธีมอยู่แล้ว</p>
    <div class="preview-swatch-strip">
      <?php foreach ($rawAccents as [$name, $hex]): ?>
        <div class="preview-swatch">
          <div class="preview-swatch-color" style="background:<?= htmlspecialchars($hex) ?>"></div>
          <div class="preview-swatch-name"><?= htmlspecialchars($name) ?></div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="card">
    <h2 class="admin-section-title" style="margin-top:0;">ตัวอักษร</h2>
    <h1 style="margin:0 0 8px;">หัวข้อ H1 — text-primary</h1>
    <h2 style="margin:0 0 8px;">หัวข้อ H2 — text-primary</h2>
    <h3 style="margin:0 0 8px;">หัวข้อ H3 — text-primary</h3>
    <p style="color:var(--text-secondary); margin:0 0 8px;">ข้อความเนื้อหาปกติ (text-secondary) ใช้กับคำอธิบายการ์ด, label ฟอร์ม, และเนื้อหาทั่วไปของหน้าเว็บ</p>
    <p style="color:var(--text-muted); margin:0 0 8px; font-size:13px;">ข้อความจาง (text-muted) ใช้กับวันที่ เมตาดาต้า empty state — 2 ชั่วโมงที่แล้ว</p>
    <div class="rich-content" style="overflow:visible;">
      <p style="margin:0 0 4px;">ลิงก์ปกติ <a href="#">เอาเมาส์ไปวางก็ได้</a> — สีมาจาก <code>link-color</code> (สโคปแค่ .rich-content/.ql-editor เหมือนในบทความจริง)</p>
      <p style="margin:0;">ตอน hover (บังคับโชว์ ไม่ต้องเอาเมาส์ไปวาง): <a href="#" style="filter:brightness(0.8);">เอาเมาส์ไปวางเพื่อดู hover</a></p>
    </div>
  </div>

  <div class="card">
    <h2 class="admin-section-title" style="margin-top:0;">ปุ่ม</h2>
    <p class="preview-note">แถวบน = ปกติ, แถวล่าง = ตอน hover (บังคับโชว์สีไว้เลย ไม่ต้องเอาเมาส์ไปวาง)</p>
    <div class="preview-row">
      <button type="button" class="btn">ปุ่มหลัก (.btn) — color-primary</button>
      <button type="button" class="btn btn-secondary">ปุ่มรอง (.btn-secondary) — btn-secondary-bg</button>
    </div>
    <div class="preview-row" style="margin-top:10px;">
      <button type="button" class="btn" style="background:var(--color-primary-hover);">ปุ่มหลัก ตอน hover — color-primary-hover</button>
      <button type="button" class="btn btn-secondary" style="background:var(--btn-secondary-bg-hover);">ปุ่มรอง ตอน hover — btn-secondary-bg-hover</button>
    </div>
  </div>

  <div class="card">
    <h2 class="admin-section-title" style="margin-top:0;">ฟอร์ม</h2>
    <div class="field">
      <label>ช่องกรอกข้อความ (input-bg / input-border / input-text)</label>
      <input type="text" value="ตัวอย่างข้อความในกล่อง">
    </div>
    <div class="field" style="margin-bottom:0;">
      <label>เลือกตัวเลือก (select)</label>
      <select>
        <option>ตัวเลือกที่ 1</option>
        <option>ตัวเลือกที่ 2</option>
      </select>
    </div>
  </div>

  <div class="card">
    <h2 class="admin-section-title" style="margin-top:0;">ป้ายสถานะ</h2>
    <div class="preview-row">
      <span class="status-badge status-draft">ร่าง</span>
      <span class="status-badge status-published">เผยแพร่แล้ว</span>
      <span class="status-badge status-scheduled">ตั้งเวลา</span>
    </div>
    <div class="preview-row" style="margin-top:12px;">
      <div class="preview-alert" style="background:var(--success-bg); color:var(--success-text);">แจ้งเตือนสำเร็จ (success-bg / success-text)</div>
      <div class="preview-alert" style="background:var(--warning-bg); color:var(--warning-text);">แจ้งเตือนเตือน (warning-bg / warning-text)</div>
      <div class="preview-alert" style="background:var(--error-bg); color:var(--error-text);">แจ้งเตือนผิดพลาด (error-bg / error-text)</div>
    </div>
  </div>

  <div class="card">
    <h2 class="admin-section-title" style="margin-top:0;">ป้ายหมวดหมู่ (7 โทน)</h2>
    <div class="preview-row">
      <span class="category-tag category-tag-gray">เทา</span>
      <span class="category-tag category-tag-blue">ฟ้า</span>
      <span class="category-tag category-tag-green">เขียว</span>
      <span class="category-tag category-tag-purple">ม่วง</span>
      <span class="category-tag category-tag-orange">ส้ม</span>
      <span class="category-tag category-tag-pink">ชมพู</span>
      <span class="category-tag category-tag-yellow">เหลือง</span>
    </div>
  </div>

  <div class="card">
    <h2 class="admin-section-title" style="margin-top:0;">เมนู (แถบเมนูบนสุด)</h2>
    <p class="preview-note">พื้น = <code>--color-primary</code> (ค่าเดียวกันทั้ง 2 ธีม), ตัวหนังสือ = <code>--text-on-primary → --color-on-primary</code> — ปกติจาง (opacity 0.75) hover เข้มเต็ม (opacity 1) ไม่ได้เปลี่ยนสี แค่เปลี่ยนความทึบ</p>
    <div class="topbar">
      <div class="topbar-nav-row" style="border-radius:var(--radius-md); padding:8px 16px;">
        <div class="topbar-menu">
          <a href="#">เมนูปกติ</a>
          <a href="#" style="opacity:1;">เมนู ตอน hover</a>
        </div>
      </div>
    </div>
  </div>

  <div class="card">
    <h2 class="admin-section-title" style="margin-top:0;">เมนู Dropdown (เมนูย่อย)</h2>
    <p class="preview-note">พื้นหลัง = <code>--dropdown-bg → --color-surface-card</code>, ตัวหนังสือ = <code>--text-primary → --color-ink</code>, เงา = <code>--dropdown-shadow</code> — รายการที่ 2 บังคับโชว์สี hover ไว้ (<code>--btn-secondary-bg-hover</code>)</p>
    <div class="preview-dropdown-demo">
      <a href="#">รายการเมนูที่ 1</a>
      <a href="#" style="background:var(--btn-secondary-bg-hover);">รายการเมนูที่ 2 (ตอน hover)</a>
      <a href="#">รายการเมนูที่ 3</a>
    </div>
  </div>
<?php render_sidebar($layout); render_footer(); ?>
