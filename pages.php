<?php
require __DIR__ . '/includes/articles.php';

$perPage = max(1, (int) siteSetting('articles_per_page', 10));
$page = max(1, (int) ($_GET['page'] ?? 1));

$result = getArticleList(['type' => 'page', 'status' => 'published'], $page, $perPage);
$articles = $result['items'];
$totalPages = max(1, (int) ceil($result['total'] / $perPage));

$pageTitle = 'หน้าทั้งหมด';
$topbarActions = '<a href="editor.php">+ เขียนบทความใหม่</a>';
$showSidebar = true;
include __DIR__ . '/partials/header.php';
?>
  <h1 class="article-title">หน้าทั้งหมด</h1>
  <?php
  $emptyMessage = 'ยังไม่มีหน้า';
  $showCategoryBadge = false;
  $pageUrl = fn(int $p) => 'pages.php?page=' . $p;
  include __DIR__ . '/partials/article-list.php';
  ?>
<?php include __DIR__ . '/partials/footer.php'; ?>
