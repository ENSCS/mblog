<?php
require __DIR__ . '/includes/articles.php';
require __DIR__ . '/includes/stats.php';

$slug = $_GET['slug'] ?? '';
$page = getPage($slug);
if (!$page) {
    // Same private-article fallback as article.php — see getPrivateArticle().
    $privatePage = getPrivateArticle($slug, 'page');
    if ($privatePage && currentStaff() !== null) {
        $page = $privatePage;
    }
}
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
$seoTitle = articleSeoTitle($page);
$seoDescription = articleSeoDescription($page);
$imageUrl = articleFeaturedImageUrl($page);
$manualFeaturedImage = $page['featured_image'] ?? '';

$pageTitle = htmlspecialchars($seoTitle);
// Pages aren't blog posts — og:type "website" (not "article") and no JSON-LD
// Article schema, unlike article.php, since that structured data doesn't fit
// a static page like "About"/"Contact".
$extraHead = '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/github.min.css">' . "\n"
    . '<link rel="stylesheet" href="assets/article.css?v=' . @filemtime(__DIR__ . '/assets/article.css') . '">'
    . "\n" . '<meta name="description" content="' . htmlspecialchars($seoDescription) . '">'
    . "\n" . '<link rel="canonical" href="' . htmlspecialchars($canonicalUrl) . '">'
    . "\n" . '<meta property="og:type" content="website">'
    . "\n" . '<meta property="og:title" content="' . htmlspecialchars($seoTitle) . '">'
    . "\n" . '<meta property="og:description" content="' . htmlspecialchars($seoDescription) . '">'
    . "\n" . '<meta property="og:url" content="' . htmlspecialchars($canonicalUrl) . '">'
    . "\n" . '<meta name="twitter:title" content="' . htmlspecialchars($seoTitle) . '">'
    . "\n" . '<meta name="twitter:description" content="' . htmlspecialchars($seoDescription) . '">';

if (!empty($page['seo_noindex'])) {
    $extraHead .= "\n" . '<meta name="robots" content="noindex,follow">';
}

if ($imageUrl) {
    $extraHead .= "\n" . '<meta property="og:image" content="' . htmlspecialchars($imageUrl) . '">'
        . "\n" . '<meta name="twitter:card" content="summary_large_image">'
        . "\n" . '<meta name="twitter:image" content="' . htmlspecialchars($imageUrl) . '">';
} else {
    $extraHead .= "\n" . '<meta name="twitter:card" content="summary">';
}

$footerScripts = '<script src="assets/copy-button.js"></script>';
// Already resolved (site setting vs. this page's own override) — don't let
// header.php re-check sidebar_enabled on top of that, see
// articleShowsSidebar()/database/article_sidebar_toggle.sql.
$showSidebar = articleShowsSidebar($page);
$sidebarSiteGate = false;
$layout = render_header(compact('pageTitle', 'extraHead', 'showSidebar', 'sidebarSiteGate'));
?>
    <div class="card">
      <?php if ($manualFeaturedImage): ?>
        <img class="featured-image-banner" src="<?= htmlspecialchars($manualFeaturedImage) ?>" alt="">
      <?php endif; ?>
      <h1 class="article-title"><?= htmlspecialchars($page['title']) ?></h1>
      <div class="article-content rich-content ql-editor"><?= $page['content'] ?></div>
    </div>
<?php render_sidebar($layout); render_footer(compact('footerScripts')); ?>
