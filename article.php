<?php
require __DIR__ . '/includes/articles.php';
require __DIR__ . '/includes/stats.php';

$slug = $_GET['slug'] ?? '';
$article = getArticle($slug);
if (!$article) {
    // Private articles are invisible to getArticle() by design (see
    // publicVisibilitySql()) — openable only by direct link + staff login,
    // so a logged-out visitor sees the same 404 as a nonexistent slug (the
    // URL never reveals a private article exists there).
    $privateArticle = getPrivateArticle($slug, 'post');
    if ($privateArticle && currentStaff() !== null) {
        $article = $privateArticle;
    }
}
if (!$article) {
    $redirectSlug = findRedirectSlug($slug, 'post');
    if ($redirectSlug) {
        header('Location: article.php?slug=' . urlencode($redirectSlug), true, 301);
        exit;
    }
    renderErrorPage(404, 'ไม่พบบทความนี้');
}

recordPageview('article', $article['id']);

$canonicalUrl = siteBaseUrl() . '/article.php?slug=' . urlencode($slug);
// seo_title/seo_description override the real title/auto-generated
// description for everything search/social-facing below — NULL/'' (the
// vast majority of articles, which never set them) falls straight back to
// the same values this page always used, so this is a no-op for existing
// content.
$seoTitle = articleSeoTitle($article);
$seoDescription = articleSeoDescription($article);
$imageUrl = articleFeaturedImageUrl($article);
// Only the manually-picked one is shown as a banner — the auto-detected
// fallback is already the first image inside the content, so showing it
// again here would just duplicate it.
$manualFeaturedImage = $article['featured_image'] ?? '';
$tags = getArticleTags($article['id']);

$pageTitle = htmlspecialchars($seoTitle);
$extraHead = '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/github-dark.min.css">' . "\n"
    . '<link rel="stylesheet" href="assets/article.css?v=' . @filemtime(__DIR__ . '/assets/article.css') . '">'
    . "\n" . '<meta name="description" content="' . htmlspecialchars($seoDescription) . '">'
    . "\n" . '<link rel="canonical" href="' . htmlspecialchars($canonicalUrl) . '">'
    . "\n" . '<meta property="og:type" content="article">'
    . "\n" . '<meta property="og:title" content="' . htmlspecialchars($seoTitle) . '">'
    . "\n" . '<meta property="og:description" content="' . htmlspecialchars($seoDescription) . '">'
    . "\n" . '<meta property="og:url" content="' . htmlspecialchars($canonicalUrl) . '">'
    . "\n" . '<meta name="twitter:title" content="' . htmlspecialchars($seoTitle) . '">'
    . "\n" . '<meta name="twitter:description" content="' . htmlspecialchars($seoDescription) . '">';

if (!empty($article['seo_noindex'])) {
    $extraHead .= "\n" . '<meta name="robots" content="noindex,follow">';
}

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
    'headline' => $seoTitle,
    'datePublished' => $article['published_at'] ?? $article['created_at'],
    'dateModified' => $article['updated_at'],
    'description' => $seoDescription,
    'image' => $imageUrl ? [$imageUrl] : [],
    'url' => $canonicalUrl,
// JSON_UNESCAPED_SLASHES deliberately dropped here (unlike other json_encode
// calls in this codebase) — $seoTitle/$seoDescription are free-text fields
// authors can set (seo_title/seo_description columns), and an unescaped "/"
// would let a value containing "</script>" close this tag early and inject
// arbitrary markup into every visitor's page. Keeping the default "\/"
// escaping is what stops that regardless of what the text contains.
], JSON_UNESCAPED_UNICODE) . '</script>';

$footerScripts = '<script src="assets/copy-button.js"></script>';
// Already resolved (site setting vs. this article's own override) — don't
// let header.php re-check sidebar_enabled on top of that, see
// articleShowsSidebar()/database/article_sidebar_toggle.sql.
$showSidebar = articleShowsSidebar($article);
$sidebarSiteGate = false;
$layout = render_header(compact('pageTitle', 'extraHead', 'showSidebar', 'sidebarSiteGate'));
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
        <span class="view-count"> การดู <?= number_format(articleViewCount($article['id'])) ?> ครั้ง</span> • 
        <?= relativeTimeTag($article['published_at']) ?>
      </div>
      <div class="article-content rich-content ql-editor"><?= $article['content'] ?></div>
      <?php if (!empty($article['source_url'])): ?>
        <p class="source-credit"><a href="<?= htmlspecialchars($article['source_url']) ?>" target="_blank" rel="noopener noreferrer">📺 ดูคลิปต้นฉบับบน YouTube</a></p>
      <?php endif; ?>
      <?php if (!empty($tags)): ?>
        <div class="tag-list">
          <?php foreach ($tags as $tag): ?>
            <a class="tag-badge" href="tag.php?slug=<?= urlencode($tag['slug']) ?>">#<?= htmlspecialchars($tag['name']) ?></a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
<?php render_sidebar($layout); render_footer(compact('footerScripts')); ?>
