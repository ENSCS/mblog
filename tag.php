<?php
require __DIR__ . '/includes/articles.php';

$slug = $_GET['slug'] ?? '';
$tag = getTagBySlug($slug);
if (!$tag) {
    renderErrorPage(404, 'ไม่พบแท็กนี้');
}

$perPage = max(1, (int) siteSetting('articles_per_page', 10));
$page = max(1, (int) ($_GET['page'] ?? 1));

$result = getArticleList(['type' => 'post', 'status' => 'published', 'tag_slug' => $slug], $page, $perPage);
$articles = $result['items'];
$totalPages = max(1, (int) ceil($result['total'] / $perPage));

$pageTitle = 'แท็ก: ' . htmlspecialchars($tag['name']) . ' — ' . siteSetting('site_name');
$topbarActions = '<a href="editor.php">+ เขียนบทความใหม่</a>';
$showSidebar = true;
include __DIR__ . '/partials/header.php';
?>
  <h1 class="article-title">#<?= htmlspecialchars($tag['name']) ?></h1>
  <?php
  $emptyMessage = 'ยังไม่มีบทความในแท็กนี้';
  $showCategoryBadge = true;
  $pageUrl = fn(int $p) => 'tag.php?slug=' . urlencode($slug) . '&page=' . $p;
  include __DIR__ . '/partials/article-list.php';
  ?>
<?php include __DIR__ . '/partials/footer.php'; ?>
