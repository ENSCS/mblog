<?php
require __DIR__ . '/includes/articles.php';

$slug = $_GET['slug'] ?? '';
$article = getArticle($slug);
if (!$article) {
    renderErrorPage(404, 'ไม่พบบทความนี้');
}

$canonicalUrl = siteBaseUrl() . '/article.php?slug=' . urlencode($slug);
$description = articleExcerpt($article);
$featuredImage = articleFeaturedImage($article);
$imageUrl = $featuredImage ? siteBaseUrl() . '/' . ltrim($featuredImage, '/') : null;
// Only the manually-picked one is shown as a banner — the auto-detected
// fallback is already the first image inside the content, so showing it
// again here would just duplicate it.
$manualFeaturedImage = $article['featured_image'] ?? '';

$pageTitle = htmlspecialchars($article['title']) . ' — mBlog';
$extraHead = '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.snow.css">' . "\n"
    . '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/github.min.css">'
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
include __DIR__ . '/partials/header.php';
?>
    <div class="card">
      <?php if ($manualFeaturedImage): ?>
        <img class="featured-image-banner" src="<?= htmlspecialchars($manualFeaturedImage) ?>" alt="">
      <?php endif; ?>
      <h1 class="article-title"><?= htmlspecialchars($article['title']) ?></h1>
      <div class="meta" style="margin-bottom:20px;">
        <span class="category-tag"><?= htmlspecialchars(articleCategory($article)) ?></span>
        อัปเดตล่าสุด: <?= htmlspecialchars($article['updated_at']) ?>
      </div>
      <div class="article-content ql-editor" style="padding:0;"><?= $article['content'] ?></div>
    </div>
<?php include __DIR__ . '/partials/footer.php'; ?>
