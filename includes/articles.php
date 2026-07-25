<?php
// Data-access layer for articles — the only place that knows articles currently
// live in articles/*.json. index.php, article.php, editor.php call these functions
// instead of reading files directly, so switching to MySQL later only means
// changing the inside of these functions, not the pages that use them.

require_once __DIR__ . '/../config.php';

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
