<?php
// Data-access layer for articles — the only place that knows articles currently
// live in articles/*.json. index.php, article.php, editor.php call these functions
// instead of reading files directly, so switching to MySQL later only means
// changing the inside of these functions, not the pages that use them.

require_once __DIR__ . '/../config.php';

// Scheme + host + path to the project root, with no trailing slash — used to
// build absolute URLs for canonical/OG tags and the sitemap. Works regardless
// of which subfolder the project is deployed under.
function siteBaseUrl(): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');

    return $scheme . '://' . $host . $dir;
}

function getArticlesDir(): string
{
    return ARTICLES_DIR;
}

// Articles saved before the draft/published field existed have no 'status' —
// treat those as already-published so existing content doesn't disappear.
function articleStatus(array $article): string
{
    return $article['status'] ?? 'published';
}

function getCategories(): array
{
    return require __DIR__ . '/../config/categories.php';
}

// Articles saved before the category field existed have none — fall back to
// the first configured category so old content still has something to show.
function articleCategory(array $article): string
{
    $categories = getCategories();
    return $article['category'] ?? $categories[0];
}

// Uses the author-written excerpt if there is one, otherwise auto-generates
// one from the content (stripped of HTML, trimmed to ~160 chars at a word
// boundary) — used for <meta name="description"> and OG/Twitter descriptions.
function articleExcerpt(array $article): string
{
    $stored = trim($article['excerpt'] ?? '');
    if ($stored !== '') {
        return $stored;
    }

    $text = trim(preg_replace('/\s+/u', ' ', strip_tags($article['content'] ?? '')));
    if ($text === '' || mb_strlen($text) <= 160) {
        return $text;
    }

    $truncated = mb_substr($text, 0, 160);
    $lastSpace = mb_strrpos($truncated, ' ');
    if ($lastSpace !== false) {
        $truncated = mb_substr($truncated, 0, $lastSpace);
    }

    return $truncated . '…';
}

// Uses the manually-picked featured image if the author set one, otherwise
// falls back to the first image found in the content. Returns a path relative
// to the site root (e.g. "uploads/xxx.png"), or null if there's no image at all.
function articleFeaturedImage(array $article): ?string
{
    if (!empty($article['featured_image'])) {
        return $article['featured_image'];
    }

    if (preg_match('/<img[^>]+src="([^"]+)"/i', $article['content'] ?? '', $m)) {
        return $m[1];
    }

    return null;
}

function readArticleFile(string $slug): ?array
{
    if (!preg_match('/^[a-z0-9\-]+$/', $slug)) {
        return null;
    }

    $file = getArticlesDir() . $slug . '.json';
    if (!is_file($file)) {
        return null;
    }

    $data = json_decode(file_get_contents($file), true);
    return $data ?: null;
}

function getAllArticles(): array
{
    $files = glob(getArticlesDir() . '*.json');

    $articles = [];
    foreach ($files as $file) {
        $data = json_decode(file_get_contents($file), true);
        if ($data) {
            $articles[] = $data;
        }
    }
    usort($articles, fn($a, $b) => strcmp($b['updated_at'], $a['updated_at']));

    return $articles;
}

// Public article list — published only.
function getArticles(): array
{
    return array_values(array_filter(
        getAllArticles(),
        fn($a) => articleStatus($a) === 'published'
    ));
}

// Draft list — for the "ร่าง" screen so drafts stay findable now that they're
// hidden from the public list. No ownership check yet (no login system exists
// yet — see PLANNING.md), so this is visible to anyone for now.
function getDraftArticles(): array
{
    return array_values(array_filter(
        getAllArticles(),
        fn($a) => articleStatus($a) === 'draft'
    ));
}

// Public single-article lookup — published only (a draft's URL is not viewable
// directly either, not just hidden from the list).
function getArticle(string $slug): ?array
{
    $article = readArticleFile($slug);
    if (!$article || articleStatus($article) !== 'published') {
        return null;
    }

    return $article;
}

// Editor lookup — any status, so an author can reopen a draft to keep working on it.
function getArticleForEdit(string $slug): ?array
{
    return readArticleFile($slug);
}
