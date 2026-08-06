<?php
require __DIR__ . '/includes/markdown-import.php';
require __DIR__ . '/includes/admin-nav.php';
requireCapability('edit_articles');

$results = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_FILES['md_files'])) {
    verifyCsrf();
    $names = $_FILES['md_files']['name'];
    $tmpPaths = $_FILES['md_files']['tmp_name'];
    $errors = $_FILES['md_files']['error'];

    foreach ($names as $i => $filename) {
        if ($errors[$i] !== UPLOAD_ERR_OK) {
            $results[] = ['success' => false, 'skipped' => false, 'filename' => $filename, 'error' => 'อัปโหลดไฟล์ไม่สำเร็จ'];
            continue;
        }
        if (strtolower(pathinfo($filename, PATHINFO_EXTENSION)) !== 'md') {
            $results[] = ['success' => false, 'skipped' => false, 'filename' => $filename, 'error' => 'ไม่ใช่ไฟล์ .md'];
            continue;
        }

        $raw = file_get_contents($tmpPaths[$i]);
        $results[] = importMarkdownArticle($raw, $filename);
    }
}

$pageTitle = 'นำเข้าบทความจาก Markdown';
$showAdminSidebar = true;
$layout = render_header(compact('pageTitle', 'showAdminSidebar'));
?>
  <h1 class="article-title">นำเข้าบทความจาก Markdown</h1>
  <div class="card">
    <p style="color:var(--text-muted); margin-top:0;">
      เลือกไฟล์ .md ได้หลายไฟล์พร้อมกัน — แต่ละไฟล์เผยแพร่เป็นบทความทันที ในหมวดหมู่ "<?= htmlspecialchars(MARKDOWN_IMPORT_CATEGORY_NAME) ?>"
      พร้อมแท็ก "<?= htmlspecialchars(MARKDOWN_IMPORT_AI_TAG_NAME) ?>" และแท็กชื่อสำนัก (ถ้าชื่อไฟล์ตรงรูปแบบ วันที่_ชื่อสำนัก_หัวข้อ)
      ไฟล์ที่เคยนำเข้าไปแล้วจะถูกข้าม ไม่สร้างซ้ำ
    </p>
    <form method="post" enctype="multipart/form-data">
      <?= csrfField() ?>
      <div class="field">
        <input type="file" name="md_files[]" accept=".md" multiple required>
      </div>
      <button type="submit" class="btn">นำเข้า</button>
    </form>

    <?php if ($results): ?>
      <div class="table-scroll" style="margin-top:20px;">
        <table class="admin-table">
          <thead>
            <tr>
              <th>ไฟล์</th>
              <th>ผลลัพธ์</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($results as $r): ?>
              <tr>
                <td><?= htmlspecialchars($r['filename']) ?></td>
                <td>
                  <?php if ($r['success']): ?>
                    <span class="status-badge status-published">สำเร็จ</span>
                    — <a href="editor.php?slug=<?= urlencode($r['slug']) ?>"><?= htmlspecialchars($r['title']) ?></a>
                  <?php elseif ($r['skipped']): ?>
                    <span class="status-badge status-draft">ข้าม (ซ้ำ)</span>
                  <?php else: ?>
                    <span class="status-badge status-draft" style="background:var(--error-bg); color:var(--error-text);">ล้มเหลว</span>
                    — <?= htmlspecialchars($r['error']) ?>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
<?php render_sidebar($layout); render_footer(); ?>
