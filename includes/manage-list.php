<?php
require_once __DIR__ . '/articles.php';
require_once __DIR__ . '/admin-nav.php';

// Shared admin-list engine behind manage-articles.php and manage-pages.php —
// one query/action/render pipeline instead of two near-identical admin
// screens, so a future tweak (a new bulk action, a new filter) lands on both
// at once. Mirrors the existing "one template, several thin callers"
// pattern already used by partials/article-list.php for the public side.
//
// $type: 'post' or 'page' — the mblog_articles.type discriminator.
// $config:
//   scriptName          (required) this page's own filename, e.g. 'manage-pages.php'
//   pageTitle           (required) heading + <title>, e.g. 'จัดการหน้า'
//   emptyMessage        (required) shown when the filtered list has no rows
//   showTaxonomyFilters  show the category/tag filter dropdowns + table columns
//                        (default true — posts only; pages don't use categories)
function renderManageListPage(string $type, array $config): void
{
    $scriptName = $config['scriptName'];
    $showTaxonomyFilters = $config['showTaxonomyFilters'] ?? true;
    $perPage = 20;

    // Filters come from the querystring (GET) so the view is bookmarkable/
    // shareable, same as WP's post list — only the action forms below (bulk
    // action, single-row trash/restore/permanent-delete) are POST.
    $requestedStatus = $_GET['status'] ?? 'all';
    $status = in_array($requestedStatus, ['all', 'published', 'draft', 'trash'], true) ? $requestedStatus : 'all';
    $search = trim($_GET['search'] ?? '');
    $categoryId = $showTaxonomyFilters ? ($_GET['category_id'] ?? '') : '';
    $tagSlug = $showTaxonomyFilters ? trim($_GET['tag_slug'] ?? '') : '';
    $dateFrom = $_GET['date_from'] ?? '';
    $dateTo = $_GET['date_to'] ?? '';
    $page = max(1, (int) ($_GET['page'] ?? 1));

    // Every action form posts back to this same filtered/paginated URL, so
    // after processing it lands the admin exactly where they were.
    $currentQuery = $_SERVER['QUERY_STRING'] ?? '';
    $actionTarget = $scriptName . ($currentQuery !== '' ? '?' . $currentQuery : '');

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';
        $ids = array_map('intval', $_POST['ids'] ?? (isset($_POST['id']) ? [$_POST['id']] : []));

        if ($action === 'bulk') {
            $action = $_POST['bulk_action'] ?? '';
        }

        switch ($action) {
            case 'trash':
                bulkTrashArticles($ids);
                break;
            case 'restore':
                bulkRestoreArticles($ids);
                break;
            case 'permanently_delete':
                bulkPermanentlyDeleteArticles($ids);
                break;
            case 'publish':
                bulkUpdateArticleStatus($ids, 'published');
                break;
            case 'draft':
                bulkUpdateArticleStatus($ids, 'draft');
                break;
        }

        header('Location: ' . $actionTarget . ($currentQuery !== '' ? '&' : '?') . 'done=1');
        exit;
    }

    $filters = [
        'type' => $type,
        'status' => $status,
        'search' => $search,
        'category_id' => $categoryId,
        'tag_slug' => $tagSlug,
        'date_from' => $dateFrom,
        'date_to' => $dateTo,
    ];
    $result = getArticlesForAdmin($filters, $page, $perPage);
    $articles = $result['items'];
    $total = $result['total'];
    $totalPages = max(1, (int) ceil($total / $perPage));

    $statusCounts = getArticleStatusCounts($type);
    $categories = $showTaxonomyFilters ? getAllCategories() : [];
    $tags = $showTaxonomyFilters ? getAllTags() : [];

    // Every tab/pagination link needs the OTHER filters preserved — build from
    // current GET params and just override the one key that link changes.
    $buildUrl = function (array $overrides) use ($scriptName) {
        $params = array_merge($_GET, $overrides);
        foreach ($params as $key => $value) {
            if ($value === '' || $value === null) {
                unset($params[$key]);
            }
        }
        return $scriptName . (count($params) ? '?' . http_build_query($params) : '');
    };

    $statusTabs = [
        'all' => 'ทั้งหมด',
        'published' => 'เผยแพร่แล้ว',
        'draft' => 'ร่าง',
        'trash' => 'ถังขยะ',
    ];

    $pageTitle = $config['pageTitle'] . ' — ' . siteSetting('site_name');
    $topbarActions = adminTopbarActions(['<a href="editor.php">+ เขียนบทความใหม่</a>']);
    $showAdminSidebar = true;

    ob_start();
    ?>
<script src="assets/manage-list.js<?= '?v=' . @filemtime(__DIR__ . '/../assets/manage-list.js') ?>" defer></script>
    <?php
    $footerScripts = ob_get_clean();

    $layout = render_header(compact('pageTitle', 'topbarActions', 'showAdminSidebar'));
    ?>
  <h1 class="article-title"><?= htmlspecialchars($config['pageTitle']) ?></h1>

  <?php if (isset($_GET['done'])): ?>
    <div class="settings-notice settings-notice-success">ดำเนินการเรียบร้อยแล้ว</div>
  <?php endif; ?>

  <div class="status-tabs">
    <?php foreach ($statusTabs as $key => $label): ?>
      <a href="<?= htmlspecialchars($buildUrl(['status' => $key === 'all' ? '' : $key, 'page' => ''])) ?>"
         class="status-tab <?= $status === $key ? 'status-tab-active' : '' ?>">
        <?= htmlspecialchars($label) ?> (<?= $statusCounts[$key] ?>)
      </a>
    <?php endforeach; ?>
  </div>

  <div class="card">
    <form method="get" class="filter-bar">
      <input type="hidden" name="status" value="<?= htmlspecialchars($status) ?>">
      <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="ค้นหาชื่อ...">
      <?php if ($showTaxonomyFilters): ?>
        <select name="category_id">
          <option value="">ทุกหมวดหมู่</option>
          <option value="none" <?= $categoryId === 'none' ? 'selected' : '' ?>>ไม่มีหมวดหมู่</option>
          <?php foreach ($categories as $cat): ?>
            <option value="<?= $cat['id'] ?>" <?= (string) $categoryId === (string) $cat['id'] ? 'selected' : '' ?>><?= htmlspecialchars($cat['name']) ?></option>
          <?php endforeach; ?>
        </select>
        <select name="tag_slug">
          <option value="">ทุกแท็ก</option>
          <?php foreach ($tags as $tag): ?>
            <option value="<?= htmlspecialchars($tag['slug']) ?>" <?= $tagSlug === $tag['slug'] ? 'selected' : '' ?>><?= htmlspecialchars($tag['name']) ?></option>
          <?php endforeach; ?>
        </select>
      <?php endif; ?>
      <label class="filter-date-label">จาก <input type="date" name="date_from" value="<?= htmlspecialchars($dateFrom) ?>"></label>
      <label class="filter-date-label">ถึง <input type="date" name="date_to" value="<?= htmlspecialchars($dateTo) ?>"></label>
      <button type="submit" class="btn btn-secondary">กรอง</button>
      <?php if ($search || $categoryId || $tagSlug || $dateFrom || $dateTo): ?>
        <a href="<?= htmlspecialchars($buildUrl(['search' => '', 'category_id' => '', 'tag_slug' => '', 'date_from' => '', 'date_to' => '', 'page' => ''])) ?>">ล้างตัวกรอง</a>
      <?php endif; ?>
    </form>

    <?php if (empty($articles)): ?>
      <div class="empty-state"><?= htmlspecialchars($config['emptyMessage']) ?></div>
    <?php else: ?>
      <?php
      // The bulk-action form and the per-row action forms below must NOT
      // nest — a <form> inside a <form> is invalid HTML, and browsers
      // silently merge the inner one into the outer one (submitting a row's
      // "ลบ" button ends up firing the bulk form's submit handler instead).
      // Checkboxes stay physically inside the table but attach to this form
      // via form="bulk-form" instead of the form wrapping the table.
      ?>
      <form method="post" action="<?= htmlspecialchars($actionTarget) ?>" id="bulk-form">
        <input type="hidden" name="action" value="bulk">
        <div class="bulk-bar">
          <select name="bulk_action" id="bulk-action-select">
            <option value="">การดำเนินการเป็นชุด</option>
            <?php if ($status === 'trash'): ?>
              <option value="restore">กู้คืน</option>
              <option value="permanently_delete">ลบถาวร</option>
            <?php else: ?>
              <option value="publish">เผยแพร่</option>
              <option value="draft">ตั้งเป็นร่าง</option>
              <option value="trash">ย้ายไปถังขยะ</option>
            <?php endif; ?>
          </select>
          <button type="submit" class="btn btn-secondary">นำไปใช้</button>
        </div>
      </form>

      <div class="table-scroll">
        <table class="admin-table">
          <thead>
            <tr>
              <th><input type="checkbox" id="select-all"></th>
              <th>หัวข้อ</th>
              <?php if ($showTaxonomyFilters): ?>
                <th>หมวดหมู่</th>
                <th>แท็ก</th>
              <?php endif; ?>
              <th>วันที่สร้าง</th>
              <th>สถานะ</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($articles as $article): ?>
              <tr>
                <td><input type="checkbox" name="ids[]" value="<?= $article['id'] ?>" class="row-select" form="bulk-form"></td>
                <td><a href="editor.php?slug=<?= urlencode($article['slug']) ?>"><?= htmlspecialchars($article['title']) ?></a><?= isStickyArticle((int) $article['id']) ? ' 📌' : '' ?></td>
                <?php if ($showTaxonomyFilters): ?>
                  <?php $articleTags = getArticleTags((int) $article['id']); ?>
                  <td>
                    <?php if ($article['category']): ?>
                      <span class="category-tag category-tag-<?= htmlspecialchars($article['category_color'] ?? 'gray') ?>"><?= htmlspecialchars($article['category']) ?></span>
                    <?php endif; ?>
                  </td>
                  <td><?= htmlspecialchars(implode(', ', array_column($articleTags, 'name'))) ?: '—' ?></td>
                <?php endif; ?>
                <td><?= relativeTimeTag($article['created_at']) ?></td>
                <?php
                $displayStatus = $article['deleted_at'] ? 'trash' : $article['status'];
                $statusBadgeLabels = ['published' => 'เผยแพร่แล้ว', 'scheduled' => 'ตั้งเวลา', 'draft' => 'ร่าง', 'trash' => 'ถังขยะ'];
                $statusBadgeClass = $displayStatus === 'trash' ? 'draft' : $displayStatus;
                ?>
                <td>
                  <span class="status-badge status-<?= htmlspecialchars($statusBadgeClass) ?>"><?= htmlspecialchars($statusBadgeLabels[$displayStatus] ?? $displayStatus) ?></span>
                  <?php if ($displayStatus === 'scheduled' && !empty($article['published_at'])): ?>
                    <div style="font-size:12px; color:var(--text-muted); margin-top:2px;"><?= htmlspecialchars(date('j/n/Y H:i', strtotime($article['published_at']))) ?></div>
                  <?php endif; ?>
                </td>
                <td class="row-actions">
                  <?php if ($status === 'trash'): ?>
                    <form method="post" action="<?= htmlspecialchars($actionTarget) ?>" style="display:inline">
                      <input type="hidden" name="action" value="restore">
                      <input type="hidden" name="id" value="<?= $article['id'] ?>">
                      <button type="submit" class="link-plain">กู้คืน</button>
                    </form>
                    <form method="post" action="<?= htmlspecialchars($actionTarget) ?>" style="display:inline" onsubmit="return confirm('ลบถาวร &quot;<?= htmlspecialchars($article['title'], ENT_QUOTES) ?>&quot; — กู้คืนไม่ได้อีก ยืนยันลบถาวร?');">
                      <input type="hidden" name="action" value="permanently_delete">
                      <input type="hidden" name="id" value="<?= $article['id'] ?>">
                      <button type="submit" class="link-danger">ลบถาวร</button>
                    </form>
                  <?php else: ?>
                    <a href="editor.php?slug=<?= urlencode($article['slug']) ?>">แก้ไข</a>
                    <form method="post" action="<?= htmlspecialchars($actionTarget) ?>" style="display:inline" onsubmit="return confirm('ย้าย &quot;<?= htmlspecialchars($article['title'], ENT_QUOTES) ?>&quot; ไปถังขยะ?');">
                      <input type="hidden" name="action" value="trash">
                      <input type="hidden" name="id" value="<?= $article['id'] ?>">
                      <button type="submit" class="link-danger">ลบ</button>
                    </form>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>

    <?php if ($totalPages > 1): ?>
      <div class="pagination">
        <?php if ($page > 1): ?><a href="<?= htmlspecialchars($buildUrl(['page' => $page - 1])) ?>">&laquo; ก่อนหน้า</a><?php endif; ?>
        <span>หน้า <?= $page ?> จาก <?= $totalPages ?></span>
        <?php if ($page < $totalPages): ?><a href="<?= htmlspecialchars($buildUrl(['page' => $page + 1])) ?>">ถัดไป &raquo;</a><?php endif; ?>
      </div>
    <?php endif; ?>
  </div>
<?php
    render_sidebar($layout);
    render_footer(compact('footerScripts'));
}
