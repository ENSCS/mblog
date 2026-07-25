<?php
require __DIR__ . '/includes/articles.php';

$perPage = max(1, (int) siteSetting('articles_per_page', 10));
$page = max(1, (int) ($_GET['page'] ?? 1));

$result = getArticleList(['type' => 'post', 'status' => 'published'], $page, $perPage);
$articles = $result['items'];
$totalPages = max(1, (int) ceil($result['total'] / $perPage));

$pageTitle = 'บทความทั้งหมด — ' . siteSetting('site_name');
$topbarActions = '<a href="editor.php">+ เขียนบทความใหม่</a>';
include __DIR__ . '/partials/header.php';
?>
  <h1 class="article-title">บทความทั้งหมด</h1>
  <?php
  $emptyMessage = 'ยังไม่มีบทความ — <a href="editor.php">เริ่มเขียนบทความแรก</a>';
  $showCategoryBadge = true;
  $pageUrl = fn(int $p) => 'articles.php?page=' . $p;
  include __DIR__ . '/partials/article-list.php';
  ?>
<?php include __DIR__ . '/partials/footer.php'; ?>
