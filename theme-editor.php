<?php
require __DIR__ . '/config.php';
require __DIR__ . '/includes/theme-colors.php';
require __DIR__ . '/includes/admin-nav.php';
requireCapability('manage_theme');

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'reset') {
        resetThemeColors();
        header('Location: theme-editor.php?reset=1');
        exit;
    }
    if ($action === 'save') {
        $decoded = json_decode($_POST['colors_json'] ?? '', true);
        if (!is_array($decoded)) {
            $errors[] = 'ข้อมูลสีที่ส่งมาอ่านไม่ได้ ลองใหม่อีกครั้ง';
        } else {
            saveThemeColors($decoded);
            header('Location: theme-editor.php?saved=1');
            exit;
        }
    }
}

$current = getThemeColors();
$isCustom = hasCustomThemeColors();
$saved = isset($_GET['saved']);
$wasReset = isset($_GET['reset']);

$labels = [
    'brand-name' => 'ชื่อแบรนด์',
    'primary' => 'สีหลัก (ปุ่ม/เมนู)',
    'on-primary' => 'ตัวหนังสือบนสีหลัก',
    'canvas' => 'พื้นหลังหน้าเว็บ',
    'card' => 'พื้นหลังการ์ด',
    'hairline' => 'เส้นขอบ',
    'ink' => 'ตัวอักษรหลัก',
    'body' => 'ตัวอักษรรอง',
    'muted' => 'ตัวอักษรจาง',
    'secondary' => 'พื้นหลังปุ่มรอง',
    'link' => 'ลิงก์',
];

$pageTitle = 'ปรับแต่งชุดสี';
$extraHead = <<<HTML
<style>
  .te-tabs { display: flex; gap: 8px; margin-bottom: 16px; }
  .te-tab { flex: 1; padding: 10px; border-radius: var(--radius-md); border: 1px solid var(--card-border); background: var(--card-bg); color: var(--text-secondary); cursor: pointer; font-size: 14px; font-family: inherit; }
  .te-tab.active { background: var(--color-primary); color: var(--text-on-primary); border-color: var(--color-primary); font-weight: 500; }
  .te-grid { display: grid; grid-template-columns: 340px minmax(0, 1fr); gap: 20px; align-items: start; }
  .te-grid > div { min-width: 0; }
  @media (max-width: 900px) { .te-grid { grid-template-columns: minmax(0, 1fr); } }
  .te-panel { display: none; }
  .te-panel.active { display: block; }
  .te-field { margin-bottom: 14px; }
  .te-field label { display: block; font-size: 13px; color: var(--text-secondary); margin-bottom: 4px; }
  .te-field select { width: auto; height: 36px; padding: 0 10px; border: 1px solid var(--input-border); border-radius: var(--radius-md); background: var(--input-bg); color: var(--input-text); font-family: inherit; font-size: 14px; }
  .te-color-pair { display: flex; gap: 6px; }
  .te-color-pair input[type=color] { width: 40px; height: 36px; flex-shrink: 0; padding: 2px; border: 1px solid var(--input-border); border-radius: var(--radius-md); background: var(--input-bg); cursor: pointer; }
  .te-color-pair input[type=text] { flex: 1; min-width: 0; height: 36px; padding: 0 10px; border: 1px solid var(--input-border); border-radius: var(--radius-md); background: var(--input-bg); color: var(--input-text); font-family: var(--font-mono); font-size: 13px; }
  .te-adv-row { display: grid; grid-template-columns: 105px minmax(0, 1fr) minmax(0, 1fr); gap: 8px; align-items: center; margin-bottom: 8px; font-size: 13px; }
  .te-adv-row label { color: var(--text-secondary); }
  .te-adv-row .te-color-pair input[type=color] { width: 30px; height: 30px; flex-shrink: 0; }
  .te-adv-row .te-color-pair input[type=text] { height: 30px; font-size: 12px; padding: 0 6px; min-width: 0; }
  .te-adv-head { display: grid; grid-template-columns: 105px minmax(0, 1fr) minmax(0, 1fr); gap: 8px; font-size: 12px; color: var(--text-muted); margin-bottom: 6px; }
  .te-preview { border-radius: 12px; padding: 20px; }
  .te-pv-card { border-radius: var(--radius-lg); padding: 16px; margin-top: 14px; }
  .te-pv-row { display: flex; gap: 10px; margin-top: 10px; flex-wrap: wrap; }
  .te-actions { display: flex; gap: 10px; margin-top: 20px; flex-wrap: wrap; }
</style>
HTML;
$showAdminSidebar = true;
$layout = render_header(compact('pageTitle', 'extraHead', 'showAdminSidebar'));
?>
  <h1 class="article-title">ปรับแต่งชุดสี</h1>
  <p style="color:var(--text-muted); margin-top:-8px;">
    ปรับสีแบรนด์/องค์กรของเว็บนี้ พร้อมพรีวิวสด ไม่ต้องกดบันทึกก่อนถึงจะเห็นผล —
    ดูตัวแปรสีแบบละเอียดได้ที่ <a href="color-reference.php">ชุดสีของเว็บ</a>
    สถานะตอนนี้: <?= $isCustom ? '<strong>ใช้ชุดสีที่ปรับแต่งไว้</strong>' : 'ยังไม่เคยปรับแต่ง (ใช้ค่าเริ่มต้น)' ?>
  </p>

  <?php if ($saved): ?><div class="settings-notice settings-notice-success">บันทึกชุดสีแล้ว</div><?php endif; ?>
  <?php if ($wasReset): ?><div class="settings-notice settings-notice-success">คืนค่าชุดสีเริ่มต้นแล้ว</div><?php endif; ?>
  <?php foreach ($errors as $e): ?><div class="settings-notice settings-notice-error"><?= htmlspecialchars($e) ?></div><?php endforeach; ?>

  <div class="card">
    <div class="te-tabs">
      <button type="button" class="te-tab active" data-tab="simple">โหมดง่าย</button>
      <button type="button" class="te-tab" data-tab="advanced">โหมดขั้นสูง</button>
    </div>

    <div class="te-grid">
      <div>
        <div class="te-panel active" data-panel="simple">
          <div class="te-field">
            <label for="s-primary-hex">สีหลัก (ปุ่ม/เมนู)</label>
            <div class="te-color-pair">
              <input type="color" id="s-primary" value="<?= htmlspecialchars($current['light']['primary']) ?>">
              <input type="text" id="s-primary-hex" value="<?= htmlspecialchars($current['light']['primary']) ?>" maxlength="7" spellcheck="false">
            </div>
          </div>
          <div class="te-field">
            <label for="s-tone">โทนพื้นหลัง</label>
            <select id="s-tone">
              <option value="39,25,91">ครีมอุ่น</option>
              <option value="0,0,92">เทากลาง</option>
              <option value="0,0,100">ขาวล้วน</option>
              <option value="216,24,96">เทาฟ้า</option>
            </select>
          </div>
          <div class="te-field">
            <label for="s-dark-tone">โทนพื้นหลัง (ธีมมืด)</label>
            <select id="s-dark-tone">
              <option value="black">ดำ (เดิม)</option>
              <option value="blue">ออกสีน้ำเงิน</option>
              <option value="green">ออกสีเขียว</option>
              <option value="red">ออกสีแดง</option>
              <option value="purple">ออกสีม่วง</option>
            </select>
          </div>
          <div class="te-field">
            <label for="s-link-hex">สีลิงก์</label>
            <div class="te-color-pair">
              <input type="color" id="s-link" value="<?= htmlspecialchars($current['light']['link']) ?>">
              <input type="text" id="s-link-hex" value="<?= htmlspecialchars($current['light']['link']) ?>" maxlength="7" spellcheck="false">
            </div>
          </div>
          <p class="preview-note" style="color:var(--text-muted); font-size:12px;">ค่าอื่นๆ (พื้นหลัง/ตัวอักษร/เส้นขอบ ทั้ง 2 ธีม) คำนวณอัตโนมัติจาก 3 ค่านี้ — สลับไป "โหมดขั้นสูง" เพื่อดู/แก้ค่าที่คำนวณได้ทีละตัว</p>
        </div>

        <div class="te-panel" data-panel="advanced">
          <div class="te-adv-head"><span>สี</span><span>สว่าง</span><span>มืด</span></div>
          <?php foreach (CUSTOM_THEME_KEYS as $key): ?>
            <div class="te-adv-row">
              <label><?= htmlspecialchars($labels[$key]) ?></label>
              <div class="te-color-pair">
                <input type="color" data-adv-mode="light" data-adv-key="<?= $key ?>" value="<?= htmlspecialchars($current['light'][$key]) ?>">
                <input type="text" data-adv-mode="light" data-adv-key="<?= $key ?>" value="<?= htmlspecialchars($current['light'][$key]) ?>" maxlength="7" spellcheck="false">
              </div>
              <div class="te-color-pair">
                <input type="color" data-adv-mode="dark" data-adv-key="<?= $key ?>" value="<?= htmlspecialchars($current['dark'][$key]) ?>">
                <input type="text" data-adv-mode="dark" data-adv-key="<?= $key ?>" value="<?= htmlspecialchars($current['dark'][$key]) ?>" maxlength="7" spellcheck="false">
              </div>
            </div>
          <?php endforeach; ?>
        </div>

        <form method="post" id="te-form">
          <?= csrfField() ?>
          <input type="hidden" name="action" value="save">
          <input type="hidden" name="colors_json" id="te-colors-json">
          <div class="te-actions">
            <button type="submit" class="btn">บันทึก</button>
            <button type="button" class="btn btn-secondary" id="te-export"><i></i>Export</button>
            <button type="button" class="btn btn-secondary" id="te-import-btn">Import</button>
            <input type="file" id="te-import-file" accept="application/json" style="display:none;">
          </div>
        </form>
        <form method="post" onsubmit="return confirm('คืนค่าชุดสีเริ่มต้น ล้างการปรับแต่งทั้งหมด?');" style="margin-top:8px;">
          <?= csrfField() ?>
          <input type="hidden" name="action" value="reset">
          <button type="submit" class="btn btn-secondary">คืนค่าเริ่มต้น</button>
        </form>
        <p style="margin-top:16px;"><a href="theme-preview.php">ดูตัวอย่างองค์ประกอบทั้งหมด &rarr;</a></p>
      </div>

      <div>
        <label id="te-theme-toggle-label" style="font-size:13px; color:var(--text-muted);">พรีวิว:</label>
        <button type="button" class="btn btn-secondary" id="te-preview-theme-toggle" style="margin-left:8px; margin-bottom:10px;">สลับดูธีมมืด</button>
        <div class="te-preview" id="te-preview">
          <span id="pv-brand" style="display:block; font-size:27px; font-weight:700; margin-bottom:12px;">mBlog'26</span>
          <div id="pv-navbar" style="border-radius:8px; padding:10px 16px; margin-bottom:16px; display:flex; gap:20px; align-items:center;">
            <span id="pv-nav-1" style="font-weight:500; font-size:14px;">บทความ</span>
            <span id="pv-nav-2" style="font-weight:500; font-size:14px; opacity:0.75;">Feed</span>
            <span id="pv-nav-3" style="font-weight:500; font-size:14px; opacity:0.75;">หมวดหมู่</span>
          </div>
          <div class="te-pv-card" id="pv-card">
            <p id="pv-ink" style="margin:0 0 6px; font-weight:700; font-size:16px;">หัวข้อตัวอย่าง</p>
            <p id="pv-body" style="margin:0 0 6px; font-size:13px;">ข้อความเนื้อหา อ่านลิงก์เช่น <a href="#" id="pv-link" style="text-decoration:underline;" onclick="return false;">www.youtube.com</a> ในบทความได้</p>
            <p id="pv-muted" style="margin:0; font-size:12px;">ข้อความจาง เช่น วันที่ 2 ชั่วโมงที่แล้ว</p>
            <div class="te-pv-row" style="margin-top:12px;">
              <button type="button" id="pv-btn-primary" style="border:none; border-radius:8px; padding:8px 18px; font-size:14px; cursor:pointer;">ปุ่มหลัก</button>
              <button type="button" id="pv-btn-primary-hover" style="border:none; border-radius:8px; padding:8px 18px; font-size:14px; cursor:pointer;">hover</button>
              <button type="button" id="pv-btn-secondary" style="border-radius:8px; padding:8px 18px; font-size:14px; cursor:pointer;">ปุ่มรอง</button>
              <button type="button" id="pv-btn-secondary-hover" style="border-radius:8px; padding:8px 18px; font-size:14px; cursor:pointer;">hover</button>
            </div>
          </div>
          <div class="te-pv-card" id="pv-dropdown-card">
            <p style="margin:0 0 8px; font-size:12px; color:var(--text-muted);">เมนู Dropdown (เมนูย่อย)</p>
            <div id="pv-dropdown" style="display:inline-block; min-width:200px; padding:10px 6px 6px; border-radius:8px;">
              <div id="pv-dd-1" style="padding:8px 10px; border-radius:6px; font-size:13px;">รายการเมนูที่ 1</div>
              <div id="pv-dd-2" style="padding:8px 10px; border-radius:6px; font-size:13px;">รายการเมนูที่ 2 (ตอน hover)</div>
            </div>
          </div>
          <div class="te-pv-card" id="pv-sidebar-card">
            <p style="margin:0 0 8px; font-size:12px; color:var(--text-muted);">แถบเมนูแอดมิน (sidebar) — กว้าง 240px เท่าของจริง</p>
            <div id="pv-sidebar-shell" style="width:240px; max-width:100%;">
              <div id="pv-sb-group-label" style="font-size:13px; font-weight:700; text-transform:uppercase; letter-spacing:0.04em; margin:0 0 8px 10px; padding-bottom:6px;">ตั้งค่าเว็บ</div>
              <div id="pv-sb-normal" style="display:flex; align-items:center; justify-content:space-between; gap:8px; padding:8px 10px; border-radius:8px; margin-bottom:4px; font-size:14px;">
                <span>ตั้งค่าเว็บ</span>
              </div>
              <div id="pv-sb-normal2" style="display:flex; align-items:center; justify-content:space-between; gap:8px; padding:8px 10px; border-radius:8px; margin-bottom:4px; font-size:14px;">
                <span>จัดการเมนู</span>
                <span id="pv-sb-badge1" style="font-size:12px; border-radius:999px; padding:1px 8px;">8</span>
              </div>
              <div id="pv-sb-hover" style="display:flex; align-items:center; justify-content:space-between; gap:8px; padding:8px 10px; border-radius:8px; margin-bottom:4px; font-size:14px;">
                <span>จัดการหมวดหมู่ (hover)</span>
                <span id="pv-sb-badge2" style="font-size:12px; border-radius:999px; padding:1px 8px;">6</span>
              </div>
              <div id="pv-sb-active" style="display:flex; align-items:center; justify-content:space-between; gap:8px; padding:8px 10px; border-radius:8px; font-size:14px; font-weight:500;">
                <span>จัดการ Sidebar</span>
                <span id="pv-sb-badge3" style="font-size:12px; border-radius:999px; padding:1px 8px;">6</span>
              </div>
            </div>
          </div>
          <div class="te-pv-card" id="pv-form-card">
            <label id="pv-form-label" style="display:block; font-size:13px; margin-bottom:6px;">ช่องกรอกข้อความ</label>
            <input type="text" id="pv-input" value="ตัวอย่างข้อความ" readonly style="width:100%; box-sizing:border-box; padding:8px 10px; border-radius:8px; font-size:13px; margin-bottom:10px;">
            <select id="pv-select" style="width:100%; box-sizing:border-box; padding:8px 10px; border-radius:8px; font-size:13px;">
              <option>ตัวเลือกที่ 1</option>
              <option>ตัวเลือกที่ 2</option>
            </select>
          </div>
        </div>
      </div>
    </div>
  </div>

<script>
(function () {
  var KEYS = <?= json_encode(CUSTOM_THEME_KEYS) ?>;
  var current = <?= json_encode($current) ?>;
  var previewTheme = 'light';

  function hslToHex(h, s, l) {
    s /= 100; l /= 100;
    var k = function (n) { return (n + h / 30) % 12; };
    var a = s * Math.min(l, 1 - l);
    var f = function (n) { return l - a * Math.max(-1, Math.min(k(n) - 3, Math.min(9 - k(n), 1))); };
    var rgb = [f(0), f(8), f(4)].map(function (x) { return Math.round(x * 255); });
    return '#' + rgb.map(function (v) { return v.toString(16).padStart(2, '0'); }).join('');
  }
  function hexToHsl(hex) {
    var r = parseInt(hex.substr(1, 2), 16) / 255, g = parseInt(hex.substr(3, 2), 16) / 255, b = parseInt(hex.substr(5, 2), 16) / 255;
    var mx = Math.max(r, g, b), mn = Math.min(r, g, b), l = (mx + mn) / 2, h, s;
    if (mx === mn) { h = s = 0; } else {
      var d = mx - mn;
      s = l > 0.5 ? d / (2 - mx - mn) : d / (mx + mn);
      if (mx === r) h = (g - b) / d + (g < b ? 6 : 0);
      else if (mx === g) h = (b - r) / d + 2;
      else h = (r - g) / d + 4;
      h *= 60;
    }
    return [h, s * 100, l * 100];
  }
  function shiftL(hex, dl) {
    var hsl = hexToHsl(hex);
    return hslToHex(hsl[0], hsl[1], Math.min(97, Math.max(3, hsl[2] + dl)));
  }
  function contrastPick(hex) {
    var r = parseInt(hex.substr(1, 2), 16) / 255, g = parseInt(hex.substr(3, 2), 16) / 255, b = parseInt(hex.substr(5, 2), 16) / 255;
    var lin = function (v) { return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4); };
    var lum = 0.2126 * lin(r) + 0.7152 * lin(g) + 0.0722 * lin(b);
    return lum > 0.4 ? '#141413' : '#ffffff';
  }

  // Dark-mode background tone presets for #s-dark-tone. "black" keeps the
  // original behavior exactly (hue inherited from the light tone, kept very
  // desaturated so it reads as near-black) — the rest override the hue
  // outright and raise saturation on the background tokens only
  // (canvas/card/secondary/hairline); text tokens (ink/body/muted) stay
  // barely tinted like "black" does, since lightness (not hue/saturation)
  // is what carries their contrast against the dark background.
  // Hue picks: 230 blue, 150 green, 355 red (wine, not alert-red — pure 0
  // reads as an error state elsewhere in the UI), 275 purple (violet, not
  // magenta — stays clearly distinct from the red preset).
  var DARK_TONE_PRESETS = {
    black: { h: null, s: { canvas: 8, card: 6, secondary: 6, hairline: 6, ink: 10, body: 6, muted: 5 } },
    blue: { h: 230, s: { canvas: 32, card: 24, secondary: 22, hairline: 26, ink: 10, body: 8, muted: 6 } },
    green: { h: 150, s: { canvas: 28, card: 20, secondary: 18, hairline: 22, ink: 8, body: 6, muted: 5 } },
    red: { h: 355, s: { canvas: 30, card: 22, secondary: 20, hairline: 24, ink: 10, body: 8, muted: 6 } },
    purple: { h: 275, s: { canvas: 30, card: 22, secondary: 20, hairline: 24, ink: 10, body: 8, muted: 6 } },
  };

  // Simple-mode derivation: 3 seed inputs (+ dark-tone style) -> full
  // 11-key light+dark set. Heuristic, not exact science — Advanced mode
  // exists precisely so a result that looks slightly off can still be
  // hand-tuned per key.
  function deriveFromSimple(primary, toneHsl, link, darkStyle) {
    var h = toneHsl[0], s = toneHsl[1], l = toneHsl[2];
    var light = {
      canvas: hslToHex(h, s, l),
      card: hslToHex(h, s * 0.6, Math.min(l + 6, 98)),
      secondary: hslToHex(h, s * 0.75, Math.max(l - 2, 0)),
      hairline: hslToHex(h, s, Math.max(l - 4, 0)),
      ink: hslToHex(h, 15, 8),
      body: hslToHex(h, 12, 24),
      muted: hslToHex(h, 10, 43),
      primary: primary,
      'on-primary': contrastPick(primary),
      'brand-name': shiftL(primary, -18),
      link: link,
    };
    var preset = DARK_TONE_PRESETS[darkStyle] || DARK_TONE_PRESETS.black;
    var dh = preset.h === null ? h : preset.h;
    var ds = preset.s;
    var dark = {
      canvas: hslToHex(dh, ds.canvas, 8),
      card: hslToHex(dh, ds.card, 15),
      secondary: hslToHex(dh, ds.secondary, 12),
      hairline: hslToHex(dh, ds.hairline, 20),
      ink: hslToHex(dh, ds.ink, 97),
      body: hslToHex(dh, ds.body, 82),
      muted: hslToHex(dh, ds.muted, 65),
      primary: shiftL(primary, 12),
      link: shiftL(link, 22),
    };
    dark['on-primary'] = contrastPick(dark.primary);
    dark['brand-name'] = shiftL(dark.primary, -8);
    return { light: light, dark: dark };
  }

  function applyToPreview(colors) {
    var mode = colors[previewTheme];
    var root = document.getElementById('te-preview');
    root.style.background = mode.canvas;
    document.getElementById('pv-card').style.background = mode.card;
    document.getElementById('pv-card').style.border = '1px solid ' + mode.hairline;
    document.getElementById('pv-ink').style.color = mode.ink;
    document.getElementById('pv-body').style.color = mode.body;
    document.getElementById('pv-muted').style.color = mode.muted;
    document.getElementById('pv-link').style.color = mode.link;
    document.getElementById('pv-brand').style.color = mode['brand-name'];
    document.getElementById('pv-navbar').style.background = mode.primary;
    ['pv-nav-1', 'pv-nav-2', 'pv-nav-3'].forEach(function (id) {
      document.getElementById(id).style.color = mode['on-primary'];
    });
    document.getElementById('pv-btn-primary').style.background = mode.primary;
    document.getElementById('pv-btn-primary').style.color = mode['on-primary'];
    document.getElementById('pv-btn-secondary').style.background = mode.secondary;
    document.getElementById('pv-btn-secondary').style.color = mode.ink;
    document.getElementById('pv-btn-secondary').style.border = '1px solid ' + mode.hairline;
    // Hover pair — .btn:hover uses filter: brightness(0.8) (no separate
    // color token), .btn-secondary:hover swaps to --btn-secondary-bg-hover
    // (--color-card). Mirrors assets/components.css exactly.
    document.getElementById('pv-btn-primary-hover').style.background = mode.primary;
    document.getElementById('pv-btn-primary-hover').style.color = mode['on-primary'];
    document.getElementById('pv-btn-primary-hover').style.filter = 'brightness(0.8)';
    document.getElementById('pv-btn-secondary-hover').style.background = mode.card;
    document.getElementById('pv-btn-secondary-hover').style.color = mode.ink;
    document.getElementById('pv-btn-secondary-hover').style.border = '1px solid ' + mode.hairline;
    document.getElementById('pv-form-card').style.background = mode.card;
    document.getElementById('pv-form-card').style.border = '1px solid ' + mode.hairline;
    document.getElementById('pv-form-label').style.color = mode.body;
    ['pv-input', 'pv-select'].forEach(function (id) {
      var el = document.getElementById(id);
      el.style.background = mode.card;
      el.style.border = '1px solid ' + mode.hairline;
      el.style.color = mode.ink;
    });

    // Dropdown submenu — hover uses --card-border (--color-hairline), not
    // --btn-secondary-bg-hover (--color-card), because that would equal the
    // panel's own background and be invisible (see assets/layout.css).
    document.getElementById('pv-dropdown').style.background = mode.card;
    document.getElementById('pv-dropdown').style.border = '1px solid ' + mode.hairline;
    document.getElementById('pv-dropdown-card').style.background = 'transparent';
    document.getElementById('pv-dropdown-card').style.border = 'none';
    document.getElementById('pv-dropdown-card').style.padding = '0';
    document.getElementById('pv-dd-1').style.color = mode.ink;
    document.getElementById('pv-dd-2').style.color = mode.ink;
    document.getElementById('pv-dd-2').style.background = mode.hairline;

    // Admin sidebar — mirrors .admin-sidebar-group-label, .admin-sidebar-
    // link, :hover, -active and .admin-sidebar-badge exactly (see
    // assets/layout.css). 240px width matches .admin-sidebar-shell for
    // real.
    document.getElementById('pv-sidebar-card').style.background = mode.canvas;
    document.getElementById('pv-sidebar-card').style.border = 'none';
    document.getElementById('pv-sidebar-card').style.padding = '0';
    document.getElementById('pv-sb-group-label').style.color = mode.ink;
    document.getElementById('pv-sb-group-label').style.borderBottom = '1px solid ' + mode.hairline;
    ['pv-sb-normal', 'pv-sb-normal2', 'pv-sb-hover'].forEach(function (id) {
      document.getElementById(id).style.color = mode.body;
    });
    document.getElementById('pv-sb-hover').style.background = mode.card;
    document.getElementById('pv-sb-active').style.background = mode.primary;
    document.getElementById('pv-sb-active').style.color = mode['on-primary'];
    ['pv-sb-badge1', 'pv-sb-badge2'].forEach(function (id) {
      var b = document.getElementById(id);
      b.style.background = mode.secondary;
      b.style.color = mode.muted;
    });
    document.getElementById('pv-sb-badge3').style.background = 'rgba(255,255,255,0.25)';
    document.getElementById('pv-sb-badge3').style.color = mode['on-primary'];
  }

  // Every color field is a swatch (source of truth) + a hex text input that
  // mirrors it — typing a valid #rrggbb updates the swatch (and fires
  // onChange) immediately; an incomplete/invalid string is left alone until
  // it resolves to something valid, and snaps back to match the swatch on
  // blur so the field never shows a stale/bogus value.
  function wirePair(colorEl, textEl, onChange) {
    colorEl.addEventListener('input', function () {
      textEl.value = colorEl.value;
      onChange();
    });
    textEl.addEventListener('input', function () {
      var v = textEl.value.trim();
      if (/^#[0-9a-fA-F]{6}$/.test(v)) {
        colorEl.value = v.toLowerCase();
        onChange();
      }
    });
    textEl.addEventListener('blur', function () {
      textEl.value = colorEl.value;
    });
  }

  function advColorInput(mode, key) {
    return document.querySelector('input[type=color][data-adv-mode=' + mode + '][data-adv-key=' + key + ']');
  }
  function advTextInput(mode, key) {
    return document.querySelector('input[type=text][data-adv-mode=' + mode + '][data-adv-key=' + key + ']');
  }

  function readAdvanced() {
    var colors = { light: {}, dark: {} };
    KEYS.forEach(function (key) {
      colors.light[key] = advColorInput('light', key).value;
      colors.dark[key] = advColorInput('dark', key).value;
    });
    return colors;
  }

  function writeAdvanced(colors) {
    KEYS.forEach(function (key) {
      advColorInput('light', key).value = colors.light[key];
      advTextInput('light', key).value = colors.light[key];
      advColorInput('dark', key).value = colors.dark[key];
      advTextInput('dark', key).value = colors.dark[key];
    });
  }

  function activePanel() {
    return document.querySelector('.te-tab.active').dataset.tab;
  }

  function recompute() {
    var colors;
    if (activePanel() === 'simple') {
      var toneHsl = document.getElementById('s-tone').value.split(',').map(Number);
      var darkStyle = document.getElementById('s-dark-tone').value;
      colors = deriveFromSimple(document.getElementById('s-primary').value, toneHsl, document.getElementById('s-link').value, darkStyle);
      writeAdvanced(colors);
    } else {
      colors = readAdvanced();
    }
    current = colors;
    document.getElementById('te-colors-json').value = JSON.stringify(colors);
    applyToPreview(colors);
  }

  document.querySelectorAll('.te-tab').forEach(function (tab) {
    tab.addEventListener('click', function () {
      document.querySelectorAll('.te-tab').forEach(function (t) { t.classList.remove('active'); });
      document.querySelectorAll('.te-panel').forEach(function (p) { p.classList.remove('active'); });
      tab.classList.add('active');
      document.querySelector('[data-panel="' + tab.dataset.tab + '"]').classList.add('active');
      recompute();
    });
  });

  document.getElementById('te-preview-theme-toggle').addEventListener('click', function () {
    previewTheme = previewTheme === 'light' ? 'dark' : 'light';
    this.textContent = previewTheme === 'light' ? 'สลับดูธีมมืด' : 'สลับดูธีมสว่าง';
    applyToPreview(current);
  });

  wirePair(document.getElementById('s-primary'), document.getElementById('s-primary-hex'), recompute);
  wirePair(document.getElementById('s-link'), document.getElementById('s-link-hex'), recompute);
  document.getElementById('s-tone').addEventListener('input', recompute);
  document.getElementById('s-dark-tone').addEventListener('input', recompute);

  KEYS.forEach(function (key) {
    wirePair(advColorInput('light', key), advTextInput('light', key), recompute);
    wirePair(advColorInput('dark', key), advTextInput('dark', key), recompute);
  });

  document.getElementById('te-export').addEventListener('click', function () {
    var blob = new Blob([JSON.stringify(current, null, 2)], { type: 'application/json' });
    var url = URL.createObjectURL(blob);
    var a = document.createElement('a');
    a.href = url;
    a.download = 'mblog-theme-' + new Date().toISOString().slice(0, 10) + '.json';
    a.click();
    URL.revokeObjectURL(url);
  });

  document.getElementById('te-import-btn').addEventListener('click', function () {
    document.getElementById('te-import-file').click();
  });
  document.getElementById('te-import-file').addEventListener('change', function (e) {
    var file = e.target.files[0];
    if (!file) return;
    var reader = new FileReader();
    reader.onload = function () {
      try {
        var parsed = JSON.parse(reader.result);
        var clean = { light: {}, dark: {} };
        KEYS.forEach(function (key) {
          var lv = parsed.light && parsed.light[key];
          var dv = parsed.dark && parsed.dark[key];
          clean.light[key] = (typeof lv === 'string' && /^#[0-9a-fA-F]{6}$/.test(lv)) ? lv : current.light[key];
          clean.dark[key] = (typeof dv === 'string' && /^#[0-9a-fA-F]{6}$/.test(dv)) ? dv : current.dark[key];
        });
        writeAdvanced(clean);
        document.querySelector('.te-tab[data-tab="advanced"]').click();
      } catch (err) {
        alert('ไฟล์นี้อ่านไม่ได้ ตรวจสอบว่าเป็นไฟล์ JSON ที่ export จากหน้านี้');
      }
    };
    reader.readAsText(file);
    e.target.value = '';
  });

  document.getElementById('te-form').addEventListener('submit', function () {
    document.getElementById('te-colors-json').value = JSON.stringify(activePanel() === 'simple' ? current : readAdvanced());
  });

  // Show the exact current values (server default or previously-saved
  // custom set) as-is on first load — don't run them through the simple-
  // mode derivation formula yet, since that only approximates the real
  // canvas/card/etc. Recompute only kicks in once the admin actually
  // touches a simple-mode input or an advanced field.
  document.getElementById('te-colors-json').value = JSON.stringify(current);
  applyToPreview(current);

  // Pre-select "โทนพื้นหลัง" to whichever preset's computed canvas hex
  // matches the current saved canvas, instead of always defaulting to the
  // first option ("ครีมอุ่น") regardless of what's actually active — same
  // "reflect saved state" reasoning as the block above. Leaves the browser
  // default (first option) untouched if no preset matches (e.g. a canvas
  // color hand-edited in โหมดขั้นสูง).
  var toneSelect = document.getElementById('s-tone');
  var currentCanvas = (current.light.canvas || '').toLowerCase();
  Array.from(toneSelect.options).forEach(function (opt) {
    var hsl = opt.value.split(',').map(Number);
    if (hslToHex(hsl[0], hsl[1], hsl[2]).toLowerCase() === currentCanvas) {
      toneSelect.value = opt.value;
    }
  });

  // Same reasoning, for #s-dark-tone — "black" reuses whichever light hue
  // toneSelect just matched above (falls back to the first option's hue if
  // the light tone itself didn't match a preset, which is still a
  // reasonable guess since "black" only reads as different at hues far
  // enough apart to matter).
  var darkToneSelect = document.getElementById('s-dark-tone');
  var currentDarkCanvas = (current.dark.canvas || '').toLowerCase();
  var blackHue = toneSelect.value.split(',').map(Number)[0];
  Object.keys(DARK_TONE_PRESETS).forEach(function (styleKey) {
    var preset = DARK_TONE_PRESETS[styleKey];
    var dh = preset.h === null ? blackHue : preset.h;
    if (hslToHex(dh, preset.s.canvas, 8).toLowerCase() === currentDarkCanvas) {
      darkToneSelect.value = styleKey;
    }
  });
})();
</script>
<?php render_sidebar($layout); render_footer(); ?>
