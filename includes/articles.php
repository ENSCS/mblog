<?php
// Data-access layer for articles — the only place that knows articles live in
// MySQL (mblog_articles + mblog_categories). index.php, article.php,
// editor.php, drafts.php, sitemap.php call these functions instead of
// querying directly, so switching storage again later only means changing
// the inside of these functions, not the pages that use them.

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/db.php';

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

function articleStatus(array $article): string
{
    return $article['status'] ?? 'published';
}

function getCategories(): array
{
    $rows = db()->query('SELECT name FROM mblog_categories ORDER BY sort_order')->fetchAll();

    return array_column($rows, 'name');
}

// Looks up a category's id by its display name — lets api/save.php keep
// working with plain category names (same as the editor's <select>) while the
// DB stores the normalized category_id foreign key.
function categoryIdByName(string $name): ?int
{
    $stmt = db()->prepare('SELECT id FROM mblog_categories WHERE name = ? LIMIT 1');
    $stmt->execute([$name]);
    $id = $stmt->fetchColumn();

    return $id !== false ? (int) $id : null;
}

// Articles with no category (legacy, or their category was deleted) — fall
// back to the first configured category so they still show something.
function articleCategory(array $article): string
{
    $categories = getCategories();

    return $article['category'] ?? $categories[0];
}

// Slug for linking the category badge to category.php?slug=... — null (no
// link, badge shows as plain text) when the article has no real category,
// since there's no slug to fall back to for the "first category" case above.
function articleCategorySlug(array $article): ?string
{
    return $article['category_slug'] ?? null;
}

// Color token for the category badge (see .category-tag-* in
// assets/components.css) — falls back to "gray" (the original badge look)
// when the article has no real category, same spirit as articleCategory().
function articleCategoryColor(array $article): string
{
    return $article['category_color'] ?? 'gray';
}

// Looks up a category by its URL slug — used by category.php to resolve
// "?slug=investing" into a display name and to validate it exists (404 if not).
function getCategoryBySlug(string $slug): ?array
{
    $stmt = db()->prepare('SELECT id, slug, name FROM mblog_categories WHERE slug = ? LIMIT 1');
    $stmt->execute([$slug]);
    $row = $stmt->fetch();

    return $row ?: null;
}

// Published posts within one category — powers category.php, which stays in
// sync automatically as articles are added to/removed from the category
// (no menu item to hand-maintain per article).
function getArticlesByCategorySlug(string $slug): array
{
    return fetchArticles('c.slug = ? AND a.status = ? AND a.type = ?', [$slug, 'published', 'post'], 'a.published_at DESC');
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

// MySQL DATETIME ("Y-m-d H:i:s") -> ISO 8601 ("c") so display text, OG tags
// and JSON-LD look exactly like they did back when date('c') was used to
// write the JSON files.
function normalizeArticleRow(array $row): array
{
    foreach (['created_at', 'updated_at', 'published_at'] as $field) {
        if (!empty($row[$field])) {
            $row[$field] = date('c', strtotime($row[$field]));
        }
    }

    return $row;
}

function thaiMonthName(int $month): string
{
    static $months = ['', 'มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน',
        'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'];

    return $months[$month];
}

function thaiDayName(int $weekday): string
{
    static $days = ['อาทิตย์', 'จันทร์', 'อังคาร', 'พุธ', 'พฤหัสบดี', 'ศุกร์', 'เสาร์'];

    return $days[$weekday];
}

// "16 กรกฎาคม เวลา 17:01 น." — year appended only when it isn't the current
// year (same convention Facebook uses), since spelling it out on every older
// timestamp within the same year is just noise.
function thaiShortDateTime(int $timestamp): string
{
    $text = date('j', $timestamp) . ' ' . thaiMonthName((int) date('n', $timestamp));
    if ((int) date('Y', $timestamp) !== (int) date('Y')) {
        $text .= ' ' . date('Y', $timestamp);
    }

    return $text . ' เวลา ' . date('H:i', $timestamp) . ' น.';
}

// "วันอาทิตย์ที่ 26 กรกฎาคม 2026 เวลา 00:48 น." — full timestamp for the
// hover tooltip, always spells out the year since there's no "is it this
// year" ambiguity to save characters on here.
function thaiFullDateTime(int $timestamp): string
{
    return 'วัน' . thaiDayName((int) date('w', $timestamp)) . 'ที่ ' . date('j', $timestamp) . ' '
        . thaiMonthName((int) date('n', $timestamp)) . ' ' . date('Y', $timestamp)
        . ' เวลา ' . date('H:i', $timestamp) . ' น.';
}

// Facebook-style relative time — "text" for the visible label ("12 นาที"),
// "title" for the full date/time shown on hover (native <span title="">
// tooltip, no JS needed). Both read off PHP's current timezone, which
// config.php already pins to siteSetting('timezone') (Asia/Bangkok) — so this
// needs no timezone conversion of its own.
function formatRelativeTime(string $dateString): array
{
    $timestamp = strtotime($dateString);
    $diff = max(0, time() - $timestamp);

    if ($diff < 60) {
        $text = 'เมื่อสักครู่';
    } elseif ($diff < 3600) {
        $text = floor($diff / 60) . ' นาที';
    } elseif ($diff < 86400) {
        $text = floor($diff / 3600) . ' ชั่วโมง';
    } elseif ($diff < 604800) {
        $text = floor($diff / 86400) . ' วัน';
    } else {
        $text = thaiShortDateTime($timestamp);
    }

    return ['text' => $text, 'title' => thaiFullDateTime($timestamp)];
}

// Ready-to-echo <span> for formatRelativeTime() — every "อัปเดตล่าสุด"/"เผยแพร่เมื่อ"
// spot on the site wants the exact same markup, so this keeps the
// htmlspecialchars() escaping in one place instead of repeated at each call site.
function relativeTimeTag(string $dateString): string
{
    $rt = formatRelativeTime($dateString);

    return '<span title="' . htmlspecialchars($rt['title']) . '">' . htmlspecialchars($rt['text']) . '</span>';
}

// $orderBy defaults to updated_at (edit-recency) since that's the only sort
// that makes sense for getDraftArticles() — a draft may have no published_at
// at all yet. Published-only lists pass 'a.published_at DESC' explicitly, to
// match the published_at now shown next to them (see relativeTimeTag()).
function fetchArticles(string $whereSql, array $params, string $orderBy = 'a.updated_at DESC'): array
{
    $stmt = db()->prepare(
        'SELECT a.*, c.name AS category, c.slug AS category_slug, c.color AS category_color
         FROM mblog_articles a
         LEFT JOIN mblog_categories c ON a.category_id = c.id
         WHERE ' . $whereSql . '
         ORDER BY ' . $orderBy
    );
    $stmt->execute($params);

    return array_map('normalizeArticleRow', $stmt->fetchAll());
}

function fetchOneArticle(string $whereSql, array $params): ?array
{
    $stmt = db()->prepare(
        'SELECT a.*, c.name AS category, c.slug AS category_slug, c.color AS category_color
         FROM mblog_articles a
         LEFT JOIN mblog_categories c ON a.category_id = c.id
         WHERE ' . $whereSql . '
         LIMIT 1'
    );
    $stmt->execute($params);
    $row = $stmt->fetch();

    return $row ? normalizeArticleRow($row) : null;
}

// Public article list — published posts only (not pages — see getPages()).
function getArticles(): array
{
    return fetchArticles('a.status = ? AND a.type = ?', ['published', 'post'], 'a.published_at DESC');
}

// Public page list — published pages only (About/Contact/Privacy Policy/...).
// Pages aren't browsed as a feed like posts; mainly used by sitemap.php.
function getPages(): array
{
    return fetchArticles('a.status = ? AND a.type = ?', ['published', 'page'], 'a.published_at DESC');
}

// Draft list — for the "ร่าง" screen so drafts stay findable now that they're
// hidden from the public list. No ownership check yet (no login system exists
// yet — see PLANNING.md), so this is visible to anyone for now.
function getDraftArticles(): array
{
    return fetchArticles('a.status = ?', ['draft']);
}

// Public single-article lookup — published posts only (a draft's URL is not
// viewable directly either, not just hidden from the list).
function getArticle(string $slug): ?array
{
    return fetchOneArticle('a.slug = ? AND a.status = ? AND a.type = ?', [$slug, 'published', 'post']);
}

// Public single-page lookup — same idea as getArticle() but for pages
// (About/Contact/Privacy Policy/...), used by page.php.
function getPage(string $slug): ?array
{
    return fetchOneArticle('a.slug = ? AND a.status = ? AND a.type = ?', [$slug, 'published', 'page']);
}

// Editor lookup — any status, so an author can reopen a draft to keep working on it.
function getArticleForEdit(string $slug): ?array
{
    return fetchOneArticle('a.slug = ?', [$slug]);
}

// Looked up by id — used by api/save.php, which must identify "which article
// is this a save for" by a value that never changes, now that the slug
// itself is user-editable (see sanitizeSlug()/uniqueSlug() below).
function getArticleById(int $id): ?array
{
    return fetchOneArticle('a.id = ?', [$id]);
}

// Same idea as the upload filename sanitizer in api/upload.php, but lower-
// cased (WP-style slugs are always lowercase) — keeps letters in any script
// (Thai/Chinese/...), digits, marks (Thai vowels/tone marks), hyphen,
// underscore; everything else (spaces, punctuation) becomes a hyphen.
function sanitizeSlug(string $input): string
{
    $slug = mb_strtolower(trim($input), 'UTF-8');
    $slug = preg_replace('/\s+/u', '-', $slug);
    $slug = preg_replace('/[^\p{L}\p{N}\p{M}_-]+/u', '-', $slug);
    $slug = preg_replace('/-+/', '-', $slug);
    $slug = trim($slug, '-_');

    return mb_substr($slug, 0, 100);
}

function slugExists(string $slug, ?int $excludeId): bool
{
    if ($excludeId !== null) {
        $stmt = db()->prepare('SELECT 1 FROM mblog_articles WHERE slug = ? AND id != ? LIMIT 1');
        $stmt->execute([$slug, $excludeId]);
    } else {
        $stmt = db()->prepare('SELECT 1 FROM mblog_articles WHERE slug = ? LIMIT 1');
        $stmt->execute([$slug]);
    }

    return (bool) $stmt->fetchColumn();
}

// Appends -2, -3, ... on collision — same pattern as uniqueUploadFilename()
// in api/upload.php. $excludeId lets an article keep its own current slug
// (not treated as a collision against itself) when re-saved unchanged.
function uniqueSlug(string $baseSlug, ?int $excludeId): string
{
    $slug = $baseSlug;
    $i = 2;
    while (slugExists($slug, $excludeId)) {
        $slug = $baseSlug . '-' . $i;
        $i++;
    }

    return $slug;
}

// Records that $oldSlug used to belong to this article — called from
// api/save.php whenever a save changes an article's slug. Stores the
// article id, not the new slug directly, so a chain of renames (A -> B -> C)
// doesn't need to be followed hop by hop: every old slug resolves straight
// to whatever the current slug is at redirect time (see findRedirectSlug()).
function recordSlugRedirect(string $oldSlug, int $articleId): void
{
    $stmt = db()->prepare(
        'INSERT INTO mblog_slug_redirects (old_slug, article_id, created_at)
         VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE article_id = VALUES(article_id), created_at = VALUES(created_at)'
    );
    $stmt->execute([$oldSlug, $articleId, date('Y-m-d H:i:s')]);
}

// Looks up the current slug for an old one — used by article.php/page.php on
// a 404 to redirect visitors following a stale/shared link. Only resolves to
// published articles of the same type ($type keeps a post redirect from ever
// pointing article.php at what is now a page, or vice versa): if the article
// was unpublished after being renamed, this must still 404, not leak the new
// URL of a now-hidden draft.
function findRedirectSlug(string $oldSlug, string $type): ?string
{
    $stmt = db()->prepare(
        'SELECT a.slug
         FROM mblog_slug_redirects r
         JOIN mblog_articles a ON a.id = r.article_id
         WHERE r.old_slug = ? AND a.status = ? AND a.type = ?
         LIMIT 1'
    );
    $stmt->execute([$oldSlug, 'published', $type]);
    $slug = $stmt->fetchColumn();

    return $slug !== false ? $slug : null;
}

// Keeps mblog_images in sync with the images actually referenced by an
// article (inline <img> tags in its content, plus its featured image) — the
// table exists so backup/migrate tooling can find exactly which uploaded
// files belong to which article without parsing HTML itself (PLANNING.md
// section 9). Called from api/save.php after every save.
//
// Only adds/removes DB rows — never touches the actual files in uploads/, in
// case the regex below misses an edge case and wrongly thinks an image is
// unused; an orphaned row is cheap to fix, a deleted file used elsewhere isn't.
function syncArticleImages(int $articleId, string $content, ?string $featuredImage): void
{
    $paths = [];
    if (preg_match_all('/<img[^>]+src="([^"]+)"/i', $content, $matches)) {
        $paths = $matches[1];
    }
    if (!empty($featuredImage)) {
        $paths[] = $featuredImage;
    }
    $paths = array_values(array_unique($paths));

    $stmt = db()->prepare('SELECT id, path FROM mblog_images WHERE article_id = ?');
    $stmt->execute([$articleId]);
    $existingRows = $stmt->fetchAll();

    $existingPaths = array_column($existingRows, 'path');
    $toInsert = array_diff($paths, $existingPaths);
    $toDelete = array_filter($existingRows, fn($row) => !in_array($row['path'], $paths, true));

    if ($toInsert) {
        $insert = db()->prepare('INSERT INTO mblog_images (article_id, path, created_at) VALUES (?, ?, ?)');
        $now = date('Y-m-d H:i:s');
        foreach ($toInsert as $path) {
            $insert->execute([$articleId, $path, $now]);
        }
    }

    if ($toDelete) {
        $delete = db()->prepare('DELETE FROM mblog_images WHERE id = ?');
        foreach ($toDelete as $row) {
            $delete->execute([$row['id']]);
        }
    }
}

// Every tag that exists, regardless of whether any article currently uses it
// — used for the editor's autocomplete list, so an author can reuse a tag
// that's momentarily unattached (e.g. removed from its last article).
function getAllTags(): array
{
    return db()->query('SELECT slug, name FROM mblog_tags ORDER BY name')->fetchAll();
}

// Only tags attached to at least one published post — used by sitemap.php,
// since an unused tag has no real archive page worth listing.
function getPublicTags(): array
{
    return db()->query(
        'SELECT DISTINCT t.slug, t.name
         FROM mblog_tags t
         JOIN mblog_article_tag at ON at.tag_id = t.id
         JOIN mblog_articles a ON a.id = at.article_id
         WHERE a.status = "published" AND a.type = "post"
         ORDER BY t.name'
    )->fetchAll();
}

// Tags on one article, for display (article.php) and for pre-filling the
// chip input when reopening an article in the editor.
function getArticleTags(int $articleId): array
{
    $stmt = db()->prepare(
        'SELECT t.slug, t.name
         FROM mblog_tags t
         JOIN mblog_article_tag at ON at.tag_id = t.id
         WHERE at.article_id = ?
         ORDER BY t.name'
    );
    $stmt->execute([$articleId]);

    return $stmt->fetchAll();
}

// Looks up a tag by its URL slug — used by tag.php to resolve "?slug=..."
// into a display name and to validate it exists (404 if not), same idea as
// getCategoryBySlug().
function getTagBySlug(string $slug): ?array
{
    $stmt = db()->prepare('SELECT id, slug, name FROM mblog_tags WHERE slug = ? LIMIT 1');
    $stmt->execute([$slug]);
    $row = $stmt->fetch();

    return $row ?: null;
}

// Published posts carrying one tag — powers tag.php, same shape as
// getArticlesByCategorySlug() but joined through the many-to-many tables.
function getArticlesByTagSlug(string $slug): array
{
    $stmt = db()->prepare(
        'SELECT a.*, c.name AS category, c.slug AS category_slug, c.color AS category_color
         FROM mblog_articles a
         LEFT JOIN mblog_categories c ON a.category_id = c.id
         JOIN mblog_article_tag at ON at.article_id = a.id
         JOIN mblog_tags t ON t.id = at.tag_id
         WHERE t.slug = ? AND a.status = ? AND a.type = ?
         ORDER BY a.published_at DESC'
    );
    $stmt->execute([$slug, 'published', 'post']);

    return array_map('normalizeArticleRow', $stmt->fetchAll());
}

// Tags are freeform (unlike category, there's no admin-curated list) — an
// author just types a name in the editor and it's created on the spot if it
// doesn't exist yet. Identity is the slug (same sanitizeSlug() as articles),
// so "React" and "react" collapse into one tag; whichever name was typed
// first is what displays. The INSERT ... ON DUPLICATE KEY trick makes
// find-or-create a single atomic statement instead of a racy SELECT-then-INSERT.
function findOrCreateTagIds(array $names): array
{
    $insert = db()->prepare(
        'INSERT INTO mblog_tags (slug, name) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)'
    );

    $ids = [];
    foreach ($names as $name) {
        $name = trim((string) $name);
        if ($name === '') {
            continue;
        }
        $slug = sanitizeSlug($name);
        if ($slug === '') {
            continue;
        }
        $insert->execute([$slug, mb_substr($name, 0, 100)]);
        $ids[] = (int) db()->lastInsertId();
    }

    return array_values(array_unique($ids));
}

// Keeps mblog_article_tag in sync with the tag names an article was saved
// with — same add/remove-the-difference approach as syncArticleImages().
// Only touches the junction table: a tag that ends up with zero articles is
// left in mblog_tags (still offered by getAllTags() for reuse), not deleted.
function syncArticleTags(int $articleId, array $tagNames): void
{
    $tagIds = findOrCreateTagIds($tagNames);

    $stmt = db()->prepare('SELECT tag_id FROM mblog_article_tag WHERE article_id = ?');
    $stmt->execute([$articleId]);
    $existingIds = array_map('intval', array_column($stmt->fetchAll(), 'tag_id'));

    $toInsert = array_diff($tagIds, $existingIds);
    $toDelete = array_diff($existingIds, $tagIds);

    if ($toInsert) {
        $insert = db()->prepare('INSERT INTO mblog_article_tag (article_id, tag_id) VALUES (?, ?)');
        foreach ($toInsert as $tagId) {
            $insert->execute([$articleId, $tagId]);
        }
    }

    if ($toDelete) {
        $delete = db()->prepare('DELETE FROM mblog_article_tag WHERE article_id = ? AND tag_id = ?');
        foreach ($toDelete as $tagId) {
            $delete->execute([$articleId, $tagId]);
        }
    }
}
