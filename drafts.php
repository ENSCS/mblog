<?php
require __DIR__ . '/includes/articles.php';

$perPage = max(1, (int) siteSetting('articles_per_page', 10));
$page = max(1, (int) ($_GET['page'] ?? 1));

$result = getArticleList(['status' => 'draft'], $page, $perPage);
$articles = $result['items'];
$totalPages = max(1, (int) ceil($result['total'] / $perPage));

$pageTitle = 'ร่างบทความ — ' . siteSetting('site_name');
$topbarActions = '<a href="editor.php">+ เขียนบทความใหม่</a>';
include __DIR__ . '/partials/header.php';
?>
  <?php
  $emptyMessage = 'ไม่มีร่างบทความ';
  $showCategoryBadge = true;
  $showStatusBadge = true;
  $linkToView = false;
  $pageUrl = fn(int $p) => 'drafts.php?page=' . $p;
  include __DIR__ . '/partials/article-list.php';
  ?>
<?php include __DIR__ . '/partials/footer.php'; ?>
