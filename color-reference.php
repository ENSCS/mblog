<?php
// Reference page for the color system in assets/base.css — shows only the
// colors an organization would actually want to customize for their brand:
// 1) light-theme colors, 2) their dark twins, then how those propagate into
// role tokens (what components use). Everything else in base.css (status
// colors, tag badges, --dropdown-shadow, --color-accent-teal/-amber,
// --color-code-bg-dark) is intentionally left out — those are fixed system
// colors nothing here should change. Hex values are transcribed snapshots
// of base.css, not live CSS variables — deliberate: this page shows light
// AND dark values side by side, which a real var(--token) can't do.
require __DIR__ . '/includes/articles.php';
require __DIR__ . '/includes/admin-nav.php';
require_once __DIR__ . '/includes/theme-colors.php';

$lightPalette = [
    ['--color-brand-name', '#a9583e', 'ตัวอักษรชื่อแบรนด์ "mBlog\'26" บนแถบเมนู'],
    ['--color-primary', '#cc785c', 'ปุ่มหลัก (.btn), ลิงก์/เมนูแอดมินที่ active, แถบ pagination ปัจจุบัน — hover คำนวณจากค่านี้ (filter: brightness), ไม่มี token แยก'],
    ['--color-on-primary', '#ffffff', 'ตัวหนังสือบนพื้นสีหลัก (ในปุ่ม .btn)'],
    ['--color-canvas', '#efe9de', 'พื้นหลังทั้งหน้า + แถบเมนูบนสุด'],
    ['--color-card', '#faf9f5', 'พื้นหลังการ์ด/dropdown/input'],
    ['--color-hairline', '#e6dfd8', 'เส้นขอบการ์ด/ตาราง/input ทั้งหมด'],
    ['--color-ink', '#141413', 'ตัวอักษรหลัก (หัวข้อ)'],
    ['--color-body', '#3d3d3a', 'ตัวอักษรรอง (คำอธิบาย/label ฟอร์ม)'],
    ['--color-muted', '#6c6a64', 'ตัวอักษรจาง (วันที่/meta/empty state)'],
    ['--color-secondary', '#f5f0e8', 'พื้นหลังปุ่มรอง, badge เมนูแอดมิน, แถบกราฟสถิติ'],
    ['--color-link', '#0645ad', 'สีลิงก์ในเนื้อหาบทความ'],
];

$darkPalette = [
    ['--color-brand-name-dark', '#e39073', 'ตัวอักษรชื่อแบรนด์ — คนละเฉดจากโหมดสว่าง เพื่อคอนทราสต์บนพื้นเข้ม'],
    ['--color-primary-dark', '#d98a68', 'ปุ่มหลัก, ลิงก์/เมนูแอดมินที่ active — สว่างขึ้นจากโหมดสว่าง เพื่อคอนทราสต์กับพื้นเข้ม'],
    ['--color-on-primary-dark', '#141413', 'ตัวหนังสือบนพื้นสีหลัก — เปลี่ยนเป็นตัวเข้มแทนขาว เพราะขาวบน primary-dark คอนทราสต์เหลือแค่ ~2.7:1'],
    ['--color-canvas-dark', '#181715', 'พื้นหลังทั้งหน้า + แถบเมนูบนสุด + footer (ใช้ค่านี้ทั้ง 2 โหมด)'],
    ['--color-card-dark', '#252320', 'พื้นหลังการ์ด/dropdown/input'],
    ['--color-hairline-dark', '#34312b', 'เส้นขอบการ์ด/ตาราง/input ทั้งหมด'],
    ['--color-ink-dark', '#faf9f5', 'ตัวอักษรหลัก (หัวข้อ)'],
    ['--color-body-dark', '#cfccc4', 'ตัวอักษรรอง (คำอธิบาย/label ฟอร์ม)'],
    ['--color-muted-dark', '#a09d96', 'ตัวอักษรจาง (วันที่/meta) + ตัวอักษร footer (ใช้ค่านี้ทั้ง 2 โหมด)'],
    ['--color-secondary-dark', '#1f1d1b', 'พื้นหลังปุ่มรอง, badge เมนูแอดมิน, แถบกราฟสถิติ'],
    ['--color-link-dark', '#58a6ff', 'สีลิงก์ในเนื้อหาบทความ — คนละเฉดจากโหมดสว่าง เพื่อคอนทราสต์บนพื้นเข้ม'],
];

$roleTokens = [
    'โครงหน้า (Chrome)' => [
        ['--page-bg', '--color-canvas', 'พื้นหลังทั้งหน้าเว็บ'],
        ['--topbar-bg', '--color-canvas', 'พื้นหลังแถบเมนูบนสุด'],
        ['--topbar-text', '--color-ink', 'ตัวอักษร/ไอคอนบนแถบเมนู, เมนู dropdown, submenu'],
        ['--topbar-border', '--color-hairline', 'เส้นขอบล่างแถบเมนูบนสุด'],
        ['--footer-bg', '--color-canvas-dark', 'พื้นหลัง footer — ล็อกใช้ค่า dark เสมอ ไม่สลับตามธีม'],
        ['--footer-text', '--color-muted-dark', 'ตัวอักษร footer — ล็อกใช้ค่า dark เสมอ ไม่สลับตามธีม'],
    ],
    'การ์ด / กล่องเนื้อหา' => [
        ['--card-bg', '--color-card', 'พื้นหลังการ์ด, sidebar item, ฟองข้อความฟีด, tab ที่เลือกอยู่, tooltip'],
        ['--card-border', '--color-hairline', 'เส้นขอบการ์ด, ตารางแอดมิน, blockquote, เส้นคั่น (hr)'],
        ['--dropdown-bg', '--color-card', 'พื้นหลังเมนู dropdown'],
    ],
    'ตัวอักษร' => [
        ['--text-primary', '--color-ink', 'หัวข้อบทความ, ยอดตัวเลขใน dashboard card'],
        ['--text-secondary', '--color-body', 'คำอธิบายการ์ด, label ฟอร์ม, ลิงก์ pagination'],
        ['--text-muted', '--color-muted', 'วันที่ relative, empty state, ข้อความอธิบายในหน้าตั้งค่า'],
        ['--text-on-primary', '--color-on-primary', 'ตัวหนังสือในปุ่มหลัก, เมนูแอดมิน active, เลข pagination ปัจจุบัน'],
        ['--link-color', '--color-link', 'ลิงก์ในเนื้อหาบทความ (.rich-content a)'],
        ['--brand-name-color', '--color-brand-name', 'ชื่อแบรนด์ "mBlog\'26" บนแถบเมนู'],
    ],
    'ฟอร์ม / ปุ่มรอง' => [
        ['--input-bg', '--color-card', 'พื้นหลังกล่องกรอกข้อมูล'],
        ['--input-border', '--color-hairline', 'เส้นขอบกล่องกรอกข้อมูล'],
        ['--input-text', '--color-ink', 'ตัวหนังสือในกล่องกรอกข้อมูล'],
        ['--btn-secondary-bg', '--color-secondary', 'พื้นหลังปุ่มรอง (.btn-secondary), badge เมนูแอดมิน, แถบกราฟสถิติ'],
        ['--btn-secondary-border', '--color-hairline', 'เส้นขอบปุ่มรอง'],
        ['--btn-secondary-text', '--color-ink', 'ตัวหนังสือปุ่มรอง'],
        ['--btn-secondary-bg-hover', '--color-card', 'พื้นหลังปุ่มรอง/tab/ปุ่ม pagination ตอน hover'],
    ],
];

// Swaps each entry's hardcoded default hex for the actual value currently
// applied (custom, if theme-editor.php has any saved — otherwise identical
// to the default). $palette entries are ['--color-x' or '--color-x-dark',
// hex, usage]; $currentValues is getThemeColors()['light'|'dark'], keyed by
// CUSTOM_THEME_KEYS (e.g. 'brand-name', no --color-/-dark affixes).
function withCurrentValues(array $palette, array $currentValues): array
{
    return array_map(function (array $entry) use ($currentValues) {
        [$name, $defaultHex, $usage] = $entry;
        $key = preg_replace('/^--color-/', '', $name);
        $key = preg_replace('/-dark$/', '', $key);
        return [$name, $currentValues[$key] ?? $defaultHex, $usage];
    }, $palette);
}

function renderSwatchGrid(array $items): void
{
    echo '<div class="color-ref-grid">';
    foreach ($items as [$name, $hex, $usage]) {
        echo '<div class="color-ref-card">';
        echo '<div class="color-ref-swatch" style="background:' . htmlspecialchars($hex) . '"></div>';
        echo '<div class="color-ref-body">';
        echo '<div class="color-ref-name">' . htmlspecialchars($name) . '</div>';
        echo '<div class="color-ref-hex">' . htmlspecialchars($hex) . '</div>';
        echo '<div class="color-ref-usage">' . htmlspecialchars($usage) . '</div>';
        echo '</div></div>';
    }
    echo '</div>';
}

function renderRoleTokenTable(array $groups): void
{
    echo '<div class="color-ref-token-grid">';
    foreach ($groups as $groupLabel => $items) {
        echo '<h3 class="color-ref-subgroup color-ref-token-group-label">' . htmlspecialchars($groupLabel) . '</h3>';
        foreach ($items as [$name, $mapsTo, $usage]) {
            echo '<div class="color-ref-token-name">' . htmlspecialchars($name) . '</div>';
            echo '<div class="color-ref-maps">' . htmlspecialchars($mapsTo) . '</div>';
            echo '<div class="color-ref-token-usage">' . htmlspecialchars($usage) . '</div>';
        }
    }
    echo '</div>';
}

$view = ($_GET['view'] ?? 'current') === 'default' ? 'default' : 'current';
if ($view === 'current') {
    $currentColors = getThemeColors();
    $lightPalette = withCurrentValues($lightPalette, $currentColors['light']);
    $darkPalette = withCurrentValues($darkPalette, $currentColors['dark']);
}

$pageTitle = 'ชุดสีของเว็บ';
$extraHead = <<<HTML
<style>
  .color-ref-subgroup { font-size: 13px; font-weight: 700; color: var(--text-secondary); margin: 24px 0 10px; }
  .color-ref-subgroup:first-child { margin-top: 0; }
  .color-ref-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 12px; }
  .color-ref-card { background: var(--card-bg); border: 1px solid var(--card-border); border-radius: var(--radius-md); overflow: hidden; }
  .color-ref-swatch { height: 48px; }
  .color-ref-body { padding: 10px 12px 12px; }
  .color-ref-name { font-family: var(--font-mono); font-size: 12px; font-weight: 600; word-break: break-all; }
  .color-ref-hex { font-family: var(--font-mono); font-size: 11px; color: var(--text-muted); margin-top: 2px; }
  .color-ref-usage { font-size: 12.5px; color: var(--text-secondary); margin-top: 8px; line-height: 1.45; }
  .color-ref-token-grid { display: grid; grid-template-columns: max-content max-content 1fr; column-gap: 28px; align-items: baseline; }
  .color-ref-token-group-label { grid-column: 1 / -1; }
  .color-ref-token-name, .color-ref-maps, .color-ref-token-usage { padding: 7px 0; border-bottom: 1px solid var(--card-border); font-size: 13px; }
  .color-ref-token-name { font-family: var(--font-mono); font-weight: 600; white-space: nowrap; }
  .color-ref-maps { font-family: var(--font-mono); font-size: 11.5px; color: var(--color-primary); white-space: nowrap; }
  .color-ref-token-usage { color: var(--text-secondary); }
</style>
HTML;
$topbarActions = adminTopbarActions();
$showAdminSidebar = true;
$layout = render_header(compact('pageTitle', 'extraHead', 'topbarActions', 'showAdminSidebar'));
?>
  <h1 class="article-title">ชุดสีของเว็บ</h1>
  <p style="color:var(--text-muted); margin-top:-8px;">
    สีที่ปรับตามแบรนด์ได้ 2 กลุ่ม (สว่าง/มืด) พร้อม Role Token บอกจุดที่นำไปใช้ —
    สีระบบอื่นๆ (badge, สถานะ, เงา ฯลฯ) ล็อกไว้ตายตัว ไม่แสดงที่นี่
  </p>

  <div class="status-tabs">
    <a href="color-reference.php" class="status-tab <?= $view === 'current' ? 'status-tab-active' : '' ?>">ค่าที่ใช้ตอนนี้</a>
    <a href="color-reference.php?view=default" class="status-tab <?= $view === 'default' ? 'status-tab-active' : '' ?>">ค่าเริ่มต้น</a>
  </div>
  <?php if ($view === 'current' && !hasCustomThemeColors()): ?>
    <p style="color:var(--text-muted); font-size:13px; margin-top:-10px;">ยังไม่เคยปรับแต่งชุดสี — ค่าที่ใช้ตอนนี้เหมือนค่าเริ่มต้นทุกจุด</p>
  <?php endif; ?>

  <div class="card">
    <h2 class="admin-section-title" style="margin-top:0;">1. ธีมสว่าง (Light)</h2>
    <?php renderSwatchGrid($lightPalette); ?>
  </div>

  <div class="card">
    <h2 class="admin-section-title" style="margin-top:0;">2. ธีมมืด (Dark)</h2>
    <p style="color:var(--text-muted); font-size:13px; margin-top:-4px;">
      คู่ <code>-dark</code> ของแต่ละสีในกลุ่มที่ 1 ชื่อเดียวกันบวก <code>-dark</code> ต่อท้าย
    </p>
    <?php renderSwatchGrid($darkPalette); ?>
  </div>

  <div class="card">
    <h2 class="admin-section-title" style="margin-top:0;">Role Token — เอาสีทั้ง 2 กลุ่มไปใช้ตรงไหนบ้าง</h2>
    <p style="color:var(--text-muted); font-size:13px; margin-top:-4px;">
      แก้สีในกลุ่ม 1-2 ด้านบน แล้ว role token พวกนี้จะเปลี่ยนตามอัตโนมัติ (ไม่ต้องแก้ที่นี่)
    </p>
    <?php renderRoleTokenTable($roleTokens); ?>
  </div>
<?php render_sidebar($layout); render_footer(); ?>
