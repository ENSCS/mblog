<?php
require __DIR__ . '/includes/articles.php';
require __DIR__ . '/includes/stats.php';

$slug = $_GET['slug'] ?? '';
$page = getPage($slug);
if (!$page) {
    $redirectSlug = findRedirectSlug($slug, 'page');
    if ($redirectSlug) {
        header('Location: page.php?slug=' . urlencode($redirectSlug), true, 301);
        exit;
    }
    renderErrorPage(404, 'ไม่พบหน้านี้');
}

recordPageview('page');

$canonicalUrl = siteBaseUrl() . '/page.php?slug=' . urlencode($slug);
$description = articleExcerpt($page);
$imageUrl = articleFeaturedImageUrl($page);
$manualFeaturedImage = $page['featured_image'] ?? '';

$pageTitle = htmlspecialchars($page['title']) . ' — ' . siteSetting('site_name');
// Pages aren't blog posts — og:type "website" (not "article") and no JSON-LD
// Article schema, unlike article.php, since that structured data doesn't fit
// a static page like "About"/"Contact".
$extraHead = '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/github.min.css">' . "\n"
    . '<link rel="stylesheet" href="assets/article.css?v=' . @filemtime(__DIR__ . '/assets/article.css') . '">'
    . "\n" . '<meta name="description" content="' . htmlspecialchars($description) . '">'
    . "\n" . '<link rel="canonical" href="' . htmlspecialchars($canonicalUrl) . '">'
    . "\n" . '<meta property="og:type" content="website">'
    . "\n" . '<meta property="og:title" content="' . htmlspecialchars($page['title']) . '">'
    . "\n" . '<meta property="og:description" content="' . htmlspecialchars($description) . '">'
    . "\n" . '<meta property="og:url" content="' . htmlspecialchars($canonicalUrl) . '">'
    . "\n" . '<meta name="twitter:title" content="' . htmlspecialchars($page['title']) . '">'
    . "\n" . '<meta name="twitter:description" content="' . htmlspecialchars($description) . '">';

if ($imageUrl) {
    $extraHead .= "\n" . '<meta property="og:image" content="' . htmlspecialchars($imageUrl) . '">'
        . "\n" . '<meta name="twitter:card" content="summary_large_image">'
        . "\n" . '<meta name="twitter:image" content="' . htmlspecialchars($imageUrl) . '">';
} else {
    $extraHead .= "\n" . '<meta name="twitter:card" content="summary">';
}

$topbarActions = '<a href="editor.php?slug=' . urlencode($slug) . '">แก้ไข</a><a href="editor.php">+ เขียนบทความใหม่</a>';
$footerScripts = '<script src="assets/copy-button.js"></script>';
// Already resolved (site setting vs. this page's own override) — don't let
// header.php re-check sidebar_enabled on top of that, see
// articleShowsSidebar()/database/article_sidebar_toggle.sql.
$showSidebar = articleShowsSidebar($page);
$sidebarSiteGate = false;
include __DIR__ . '/partials/header.php';
?>
    <div class="card">
      <?php if ($manualFeaturedImage): ?>
        <img class="featured-image-banner" src="<?= htmlspecialchars($manualFeaturedImage) ?>" alt="">
      <?php endif; ?>
      <h1 class="article-title"><?= htmlspecialchars($page['title']) ?></h1>
      <div class="article-content rich-content ql-editor"><?= $page['content'] ?></div>
    </div>
<?php include __DIR__ . '/partials/footer.php'; ?>
