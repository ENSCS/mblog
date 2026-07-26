<?php
require __DIR__ . '/includes/articles.php';

$slug = $_GET['slug'] ?? '';
$category = getCategoryBySlug($slug);
if (!$category) {
    renderErrorPage(404, 'ไม่พบหมวดหมู่นี้');
}

$perPage = max(1, (int) siteSetting('articles_per_page', 10));
$page = max(1, (int) ($_GET['page'] ?? 1));

$result = getArticleList(['type' => 'post', 'status' => 'published', 'category_slug' => $slug], $page, $perPage);
$articles = $result['items'];
$totalPages = max(1, (int) ceil($result['total'] / $perPage));

$pageTitle = 'หมวด: ' . htmlspecialchars($category['name']) . ' — ' . siteSetting('site_name');
$topbarActions = '<a href="editor.php">+ เขียนบทความใหม่</a>';
$showSidebar = true;
include __DIR__ . '/partials/header.php';
?>
  <h1 class="article-title"><?= htmlspecialchars($category['name']) ?></h1>
  <?php
  $emptyMessage = 'ยังไม่มีบทความในหมวดนี้';
  $showCategoryBadge = true;
  $pageUrl = fn(int $p) => 'category.php?slug=' . urlencode($slug) . '&page=' . $p;
  include __DIR__ . '/partials/article-list.php';
  ?>
<?php include __DIR__ . '/partials/footer.php'; ?>
