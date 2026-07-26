<?php
require __DIR__ . '/includes/articles.php';

$slug = $_GET['slug'] ?? '';
$article = getArticle($slug);
if (!$article) {
    $redirectSlug = findRedirectSlug($slug, 'post');
    if ($redirectSlug) {
        header('Location: article.php?slug=' . urlencode($redirectSlug), true, 301);
        exit;
    }
    renderErrorPage(404, 'ไม่พบบทความนี้');
}

$canonicalUrl = siteBaseUrl() . '/article.php?slug=' . urlencode($slug);
$description = articleExcerpt($article);
$imageUrl = articleFeaturedImageUrl($article);
// Only the manually-picked one is shown as a banner — the auto-detected
// fallback is already the first image inside the content, so showing it
// again here would just duplicate it.
$manualFeaturedImage = $article['featured_image'] ?? '';
$tags = getArticleTags($article['id']);

$pageTitle = htmlspecialchars($article['title']) . ' — ' . siteSetting('site_name');
$extraHead = '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/github-dark.min.css">' . "\n"
    . '<link rel="stylesheet" href="assets/article.css?v=' . @filemtime(__DIR__ . '/assets/article.css') . '">'
    . "\n" . '<meta name="description" content="' . htmlspecialchars($description) . '">'
    . "\n" . '<link rel="canonical" href="' . htmlspecialchars($canonicalUrl) . '">'
    . "\n" . '<meta property="og:type" content="article">'
    . "\n" . '<meta property="og:title" content="' . htmlspecialchars($article['title']) . '">'
    . "\n" . '<meta property="og:description" content="' . htmlspecialchars($description) . '">'
    . "\n" . '<meta property="og:url" content="' . htmlspecialchars($canonicalUrl) . '">'
    . "\n" . '<meta name="twitter:title" content="' . htmlspecialchars($article['title']) . '">'
    . "\n" . '<meta name="twitter:description" content="' . htmlspecialchars($description) . '">';

if ($imageUrl) {
    $extraHead .= "\n" . '<meta property="og:image" content="' . htmlspecialchars($imageUrl) . '">'
        . "\n" . '<meta name="twitter:card" content="summary_large_image">'
        . "\n" . '<meta name="twitter:image" content="' . htmlspecialchars($imageUrl) . '">';
} else {
    $extraHead .= "\n" . '<meta name="twitter:card" content="summary">';
}

$extraHead .= "\n" . '<script type="application/ld+json">' . json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'Article',
    'headline' => $article['title'],
    'datePublished' => $article['published_at'] ?? $article['created_at'],
    'dateModified' => $article['updated_at'],
    'description' => $description,
    'image' => $imageUrl ? [$imageUrl] : [],
    'url' => $canonicalUrl,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>';

$topbarActions = '<a href="editor.php?slug=' . urlencode($slug) . '">แก้ไข</a><a href="editor.php">+ เขียนบทความใหม่</a>';
$footerScripts = '<script src="assets/copy-button.js"></script>';
// Already resolved (site setting vs. this article's own override) — don't
// let header.php re-check sidebar_enabled on top of that, see
// articleShowsSidebar()/database/article_sidebar_toggle.sql.
$showSidebar = articleShowsSidebar($article);
$sidebarSiteGate = false;
include __DIR__ . '/partials/header.php';
?>
    <div class="card">
      <?php if ($manualFeaturedImage): ?>
        <img class="featured-image-banner" src="<?= htmlspecialchars($manualFeaturedImage) ?>" alt="">
      <?php endif; ?>
      <h1 class="article-title"><?= htmlspecialchars($article['title']) ?></h1>
      <div class="meta" style="margin-bottom:20px;">
        <?php if ($categoryName = articleCategory($article)): ?>
          <span class="category-tag category-tag-<?= htmlspecialchars(articleCategoryColor($article)) ?>"><?= htmlspecialchars($categoryName) ?></span>
        <?php endif; ?>
        <?= relativeTimeTag($article['published_at']) ?>
      </div>
      <div class="article-content rich-content ql-editor"><?= $article['content'] ?></div>
      <?php if (!empty($tags)): ?>
        <div class="tag-list">
          <?php foreach ($tags as $tag): ?>
            <a class="tag-badge" href="tag.php?slug=<?= urlencode($tag['slug']) ?>">#<?= htmlspecialchars($tag['name']) ?></a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
<?php include __DIR__ . '/partials/footer.php'; ?>
