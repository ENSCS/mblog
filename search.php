<?php
require __DIR__ . '/includes/articles.php';
require __DIR__ . '/includes/stats.php';

recordPageview('search');

$perPage = max(1, (int) siteSetting('articles_per_page', 10));
$page = max(1, (int) ($_GET['page'] ?? 1));
$q = trim($_GET['q'] ?? '');
$qSafe = htmlspecialchars($q);

$result = $q !== '' ? searchArticles($q, $page, $perPage) : ['items' => [], 'total' => 0];
$articles = $result['items'];
$totalPages = max(1, (int) ceil($result['total'] / $perPage));

$pageTitle = $q !== '' ? 'ค้นหา: ' . $qSafe : 'ค้นหาบทความ';
$showSidebar = true;
$layout = render_header(compact('pageTitle', 'showSidebar'));
?>
  <h1 class="article-title"><?= $q !== '' ? 'ผลการค้นหา: "' . $qSafe . '"' : 'ค้นหาบทความ' ?></h1>
  <?php if ($q === ''): ?>
    <div class="empty-state">พิมพ์คำค้นหาที่ช่องด้านบนของเว็บ</div>
  <?php else: ?>
    <?php
    $emptyMessage = 'ไม่พบบทความที่ตรงกับ "' . $qSafe . '"';
    $showCategoryBadge = true;
    $pageUrl = fn(int $p) => 'search.php?q=' . urlencode($q) . '&page=' . $p;
    include __DIR__ . '/partials/article-list.php';
    ?>
  <?php endif; ?>
<?php render_sidebar($layout); render_footer(); ?>
