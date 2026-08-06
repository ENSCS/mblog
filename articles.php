<?php
require __DIR__ . '/includes/articles.php';
require __DIR__ . '/includes/stats.php';

recordPageview('articles_list');

$perPage = max(1, (int) siteSetting('articles_per_page', 10));
$page = max(1, (int) ($_GET['page'] ?? 1));

$result = getArticleList(['type' => 'post', 'status' => 'published'], $page, $perPage, true);
$articles = $result['items'];
$totalPages = max(1, (int) ceil($result['total'] / $perPage));

$pageTitle = 'บทความทั้งหมด';
$topbarActions = '<a href="editor.php">+ เขียนบทความใหม่</a>';
$showSidebar = true;
$layout = render_header(compact('pageTitle', 'topbarActions', 'showSidebar'));
?>
  <?php
  $emptyMessage = 'ยังไม่มีบทความ — <a href="editor.php">เริ่มเขียนบทความแรก</a>';
  $showCategoryBadge = true;
  $pageUrl = fn(int $p) => 'articles.php?page=' . $p;
  include __DIR__ . '/partials/article-list.php';
  ?>
<?php render_sidebar($layout); render_footer(); ?>
