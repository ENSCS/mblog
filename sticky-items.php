<?php
require __DIR__ . '/includes/articles.php';
require __DIR__ . '/includes/admin-nav.php';

$saved = isset($_GET['saved']);
$search = trim($_GET['search'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = (int) ($_POST['id'] ?? 0);

    if ($action === 'pin' && $id > 0) {
        pinArticle($id);
        // Carries the search term back so pinning a result doesn't lose
        // the admin's place — they're likely about to pin a second/third
        // match from the same search.
        header('Location: sticky-items.php?search=' . urlencode($_POST['search'] ?? '') . '&saved=1');
        exit;
    }

    if ($action === 'unpin' && $id > 0) {
        unpinArticle($id);
        header('Location: sticky-items.php?saved=1');
        exit;
    }

    if ($action === 'reorder') {
        // This form only ever renders one row per currently-pinned item (see
        // $pinnedItems below) — never a filtered subset — so treating "not
        // in this submission" as "no longer pinned" is safe here, unlike a
        // search-filtered list would be.
        $pairs = [];
        foreach ($_POST['sort_order'] ?? [] as $rowId => $order) {
            $pairs[] = ['id' => (int) $rowId, 'order' => ($order !== '' && is_numeric($order)) ? (int) $order : PHP_INT_MAX];
        }
        usort($pairs, fn($a, $b) => $a['order'] <=> $b['order']);
        setStickyArticles(array_column($pairs, 'id'));
        header('Location: sticky-items.php?saved=1');
        exit;
    }
}

$stickyIds = getStickyArticleIds();
// Preserves sticky order (getArticleById() one at a time, not a single IN()
// query) — this list is exactly as long as the pinned set, which is small
// by nature (see setStickyArticles()'s own doc comment), so N queries here
// costs nothing and keeps the ordering trivially correct.
$pinnedItems = array_values(array_filter(array_map('getArticleById', $stickyIds)));

// Only queried when the admin actually searches — never loads the full
// article/page catalog at once, which is what made the previous version of
// this page (one giant table of everything) slow to scroll once the site
// has more than a couple dozen posts.
$searchResults = $search !== '' ? getArticleList(['status' => 'published', 'search' => $search], 1, 20)['items'] : [];

$pageTitle = 'จัดการปักหมุด';
$topbarActions = adminTopbarActions();
$showAdminSidebar = true;
$layout = render_header(compact('pageTitle', 'topbarActions', 'showAdminSidebar'));
?>
  <h1 class="article-title">จัดการปักหมุด</h1>

  <?php if ($saved): ?><div class="settings-notice settings-notice-success">บันทึกแล้ว</div><?php endif; ?>

  <div class="card">
    <h2 style="margin-top:0;">ค้นหาเพื่อปักหมุดเพิ่ม</h2>
    <form method="get" class="filter-bar">
      <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="ค้นหาชื่อบทความ/หน้า...">
      <button type="submit" class="btn btn-secondary">ค้นหา</button>
    </form>

    <?php if ($search !== ''): ?>
      <?php if (empty($searchResults)): ?>
        <div class="empty-state" style="margin-top:12px;">ไม่พบบทความ/หน้าที่ตรงกับ "<?= htmlspecialchars($search) ?>"</div>
      <?php else: ?>
        <div class="table-scroll" style="margin-top:12px;">
          <table class="admin-table sticky-search-table">
            <thead><tr><th>ชื่อ</th><th>ประเภท</th><th></th></tr></thead>
            <tbody>
              <?php foreach ($searchResults as $item): ?>
                <?php $itemId = (int) $item['id']; $isPinned = in_array($itemId, $stickyIds, true); ?>
                <tr>
                  <td><?= htmlspecialchars($item['title']) ?></td>
                  <td><span class="category-tag"><?= $item['type'] === 'page' ? 'หน้า' : 'บทความ' ?></span></td>
                  <td class="row-actions">
                    <?php if ($isPinned): ?>
                      ปักหมุดแล้ว
                    <?php else: ?>
                      <form method="post" style="display:inline">
                        <input type="hidden" name="action" value="pin">
                        <input type="hidden" name="id" value="<?= $itemId ?>">
                        <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
                        <button type="submit" class="link-plain">ปักหมุด</button>
                      </form>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    <?php endif; ?>
  </div>

  <div class="card">
    <h2 style="margin-top:0;">รายการที่ปักหมุดอยู่</h2>
    <?php if (empty($pinnedItems)): ?>
      <div class="empty-state">ยังไม่มีบทความ/หน้าที่ปักหมุด — ค้นหาด้านบนแล้วกด "ปักหมุด"</div>
    <?php else: ?>
      <form method="post" id="reorder-form"><input type="hidden" name="action" value="reorder"></form>
      <div class="table-scroll">
        <table class="admin-table sticky-pinned-table">
          <thead><tr><th>ชื่อ</th><th>ประเภท</th><th>ลำดับ</th><th></th></tr></thead>
          <tbody>
            <?php foreach ($pinnedItems as $item): ?>
              <?php $itemId = (int) $item['id']; $order = array_search($itemId, $stickyIds, true) + 1; ?>
              <tr>
                <td><?= htmlspecialchars($item['title']) ?></td>
                <td><span class="category-tag"><?= $item['type'] === 'page' ? 'หน้า' : 'บทความ' ?></span></td>
                <td><input type="number" name="sort_order[<?= $itemId ?>]" value="<?= $order ?>" form="reorder-form" style="max-width:80px;"></td>
                <td class="row-actions">
                  <form method="post" style="display:inline">
                    <input type="hidden" name="action" value="unpin">
                    <input type="hidden" name="id" value="<?= $itemId ?>">
                    <button type="submit" class="link-danger">ถอดหมุด</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <button type="submit" form="reorder-form" class="btn" style="margin-top:12px;">บันทึกลำดับ</button>
    <?php endif; ?>
  </div>
<?php render_sidebar($layout); render_footer(); ?>
