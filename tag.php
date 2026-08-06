<?php
require __DIR__ . '/includes/articles.php';
require __DIR__ . '/includes/stats.php';

$slug = $_GET['slug'] ?? '';
$tag = getTagBySlug($slug);
if (!$tag) {
    renderErrorPage(404, 'ไม่พบแท็กนี้');
}

recordPageview('tag');

$perPage = max(1, (int) siteSetting('articles_per_page', 10));
$page = max(1, (int) ($_GET['page'] ?? 1));

$result = getArticleList(['type' => 'post', 'status' => 'published', 'tag_slug' => $slug], $page, $perPage);
$articles = $result['items'];
$totalPages = max(1, (int) ceil($result['total'] / $perPage));

$pageTitle = 'แท็ก: ' . htmlspecialchars($tag['name']);
$showSidebar = true;
$layout = render_header(compact('pageTitle', 'showSidebar'));
?>
  <h1 class="article-title">#<?= htmlspecialchars($tag['name']) ?></h1>
  <?php
  $emptyMessage = 'ยังไม่มีบทความในแท็กนี้';
  $showCategoryBadge = true;
  $pageUrl = fn(int $p) => 'tag.php?slug=' . urlencode($slug) . '&page=' . $p;
  include __DIR__ . '/partials/article-list.php';
  ?>
<?php render_sidebar($layout); render_footer(); ?>
