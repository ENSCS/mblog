<?php
require __DIR__ . '/includes/articles.php';

header('Content-Type: application/xml; charset=utf-8');

$articles = getArticles();
$base = siteBaseUrl();

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
  <url>
    <loc><?= htmlspecialchars($base . '/index.php') ?></loc>
  </url>
<?php foreach ($articles as $a): ?>
  <url>
    <loc><?= htmlspecialchars($base . '/article.php?slug=' . urlencode($a['slug'])) ?></loc>
    <lastmod><?= htmlspecialchars(substr($a['updated_at'], 0, 10)) ?></lastmod>
  </url>
<?php endforeach; ?>
</urlset>
