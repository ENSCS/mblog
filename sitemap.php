<?php
require __DIR__ . '/includes/articles.php';

header('Content-Type: application/xml; charset=utf-8');

$articles = getArticles();
$pages = getPages();
$tags = getPublicTags();
$base = siteBaseUrl();

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
  <?php // index.php just 302-redirects here for now (see index.php) — list
        // the real destination directly instead of a URL that only redirects. ?>
  <url>
    <loc><?= htmlspecialchars($base . '/articles.php') ?></loc>
  </url>
<?php foreach ($articles as $a): ?>
  <url>
    <loc><?= htmlspecialchars($base . '/article.php?slug=' . urlencode($a['slug'])) ?></loc>
    <lastmod><?= htmlspecialchars(substr($a['updated_at'], 0, 10)) ?></lastmod>
  </url>
<?php endforeach; ?>
<?php foreach ($pages as $p): ?>
  <url>
    <loc><?= htmlspecialchars($base . '/page.php?slug=' . urlencode($p['slug'])) ?></loc>
    <lastmod><?= htmlspecialchars(substr($p['updated_at'], 0, 10)) ?></lastmod>
  </url>
<?php endforeach; ?>
<?php foreach ($tags as $t): ?>
  <url>
    <loc><?= htmlspecialchars($base . '/tag.php?slug=' . urlencode($t['slug'])) ?></loc>
  </url>
<?php endforeach; ?>
</urlset>
