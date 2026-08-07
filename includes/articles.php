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

// Single definition of "visible to the public" — a scheduled article/page
// becomes visible once its time arrives without anything ever flipping its
// status column back to 'published' (no cron; see database/
// article_visibility_and_seo.sql), this comparison at query time is the
// entire mechanism. Expired content stops being visible the same way,
// regardless of status. Every public-facing query (getArticleList()'s
// 'published' filter, searchArticles(), getArticle(), getPage()) goes
// through this one place — admin-facing queries (getArticlesForAdmin(),
// getArticleStatusCounts()) deliberately do NOT, since the admin needs to
// see and manage scheduled/expired items, not have them hidden.
function publicVisibilitySql(string $alias = 'a'): string
{
    return "$alias.status IN ('published', 'scheduled') AND $alias.published_at <= NOW() AND ($alias.expires_at IS NULL OR $alias.expires_at > NOW())";
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

// Null when the article has no category — either it was never given one, or
// its category was deleted (category_id gets set to NULL, not reassigned to
// another category — see the FK in database/phase1_core.sql). Callers skip
// the badge entirely in this case rather than showing a fake fallback category.
function articleCategory(array $article): ?string
{
    return $article['category'] ?? null;
}

// Slug for linking the category badge to category.php?slug=... — null (no
// badge at all) when the article has no category, same as articleCategory().
function articleCategorySlug(array $article): ?string
{
    return $article['category_slug'] ?? null;
}

// Color token for the category badge (see .category-tag-* in
// assets/components.css) — only meaningful when articleCategory() is non-null;
// callers that skip the badge for a null category never call this either.
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

// Full rows (id, slug, name, color, sort_order) for the admin screen —
// unlike getCategories() this isn't just names, since the admin form needs
// actual ids/colors/order to edit individual rows.
function getAllCategories(): array
{
    return db()->query(
        'SELECT id, slug, name, color, sort_order FROM mblog_categories ORDER BY sort_order'
    )->fetchAll();
}

function getCategoryById(int $id): ?array
{
    $stmt = db()->prepare('SELECT id, slug, name, color, sort_order FROM mblog_categories WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();

    return $row ?: null;
}

// Used by the admin form to warn before deleting a category that's still in
// use — deleting is non-destructive either way (category_id has
// ON DELETE SET NULL, articles just end up with no category — see
// articleCategory()), but an author should know that's about to happen.
function countArticlesInCategory(int $id): int
{
    $stmt = db()->prepare('SELECT COUNT(*) FROM mblog_articles WHERE category_id = ?');
    $stmt->execute([$id]);

    return (int) $stmt->fetchColumn();
}

// Auto-append-to-end default when the admin leaves "sort order" blank.
function nextCategorySortOrder(): int
{
    $max = db()->query('SELECT MAX(sort_order) FROM mblog_categories')->fetchColumn();

    return $max !== null ? ((int) $max + 1) : 1;
}

// True if another category already uses this slug — $excludeId lets a
// category keep its own current slug when re-saved unchanged, same pattern
// as slugExists() for articles.
function categorySlugExists(string $slug, ?int $excludeId): bool
{
    if ($excludeId !== null) {
        $stmt = db()->prepare('SELECT 1 FROM mblog_categories WHERE slug = ? AND id != ? LIMIT 1');
        $stmt->execute([$slug, $excludeId]);
    } else {
        $stmt = db()->prepare('SELECT 1 FROM mblog_categories WHERE slug = ? LIMIT 1');
        $stmt->execute([$slug]);
    }

    return (bool) $stmt->fetchColumn();
}

function createCategory(string $slug, string $name, string $color, int $sortOrder): void
{
    $stmt = db()->prepare(
        'INSERT INTO mblog_categories (slug, name, color, sort_order, created_at) VALUES (?, ?, ?, ?, ?)'
    );
    $stmt->execute([$slug, $name, $color, $sortOrder, date('Y-m-d H:i:s')]);
}

function updateCategory(int $id, string $slug, string $name, string $color, int $sortOrder): void
{
    $stmt = db()->prepare(
        'UPDATE mblog_categories SET slug = ?, name = ?, color = ?, sort_order = ? WHERE id = ?'
    );
    $stmt->execute([$slug, $name, $color, $sortOrder, $id]);
}

function deleteCategory(int $id): void
{
    $stmt = db()->prepare('DELETE FROM mblog_categories WHERE id = ?');
    $stmt->execute([$id]);
}

// Auto-generates a description from the content (stripped of HTML, trimmed
// to ~160 chars at a word boundary) — the fallback articleSeoDescription()
// uses when seo_description is empty. There used to be an author-written
// excerpt field checked first here; it was dropped (2026-07-30) once
// seo_description took over that exact job, so this is content-only now.
function autoArticleDescription(array $article): string
{
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

// seo_title/seo_description (see database/article_visibility_and_seo.sql) are
// an editor override for <title>/meta description — NULL/'' means "keep
// using the real title / auto-generated description", so an article that
// never sets them behaves exactly as it did before this column existed.
function articleSeoTitle(array $article): string
{
    $override = trim($article['seo_title'] ?? '');

    return $override !== '' ? $override : $article['title'];
}

function articleSeoDescription(array $article): string
{
    $override = trim($article['seo_description'] ?? '');

    return $override !== '' ? $override : autoArticleDescription($article);
}

// Thai has no spaces between words, so cutting at "the last space before
// $maxLength" (which works for English) can badly misfire — e.g. "TNP
// ประกอบธุรกิจ..." has its only early space right after "TNP", so that
// approach chopped the whole preview down to just "TNP". A plain
// character-count cut instead can land mid-syllable in a way that reads as
// broken — Thai vowel signs ั/็ (mai han akat/mai taikhu) always need a
// following final consonant and can never end a syllable on their own, and
// เ/แ/โ/ใ/ไ are written *before* the consonant they belong to — so "ตัด" (cut)
// truncated to 2 characters becomes the meaningless "ตั" instead of "ต".
// This isn't full Thai word segmentation (that needs a dictionary — ICU's
// IntlBreakIterator can technically do it, but its Thai dictionary data
// isn't reliably present, and produced garbage boundaries when tried here)
// — it only backs off far enough to avoid landing on one of those, which
// covers the common case without a dependency. Vowel signs that CAN validly
// end a word on their own (ี/ู/่ etc. — ดู, ไม่, สี, ...) are deliberately
// left alone.
function trimTrailingThaiIncomplete(string $text): string
{
    while ($text !== '' && preg_match('/[\x{0E31}\x{0E40}-\x{0E44}\x{0E47}]$/u', $text)) {
        $text = mb_substr($text, 0, -1);
    }

    return $text;
}

// First paragraph of the actual content (not the manually-written excerpt —
// that's for meta description/OG tags, a different job) trimmed to
// $maxLength characters (see trimTrailingThaiIncomplete() above for why this
// is a plain character cut, not a word-boundary one), so a card on
// articles.php/pages.php/category.php/tag.php/drafts.php gets a taste of the
// article without overflowing the card no matter how long the first
// paragraph runs. $maxLength defaults to the site's preview_max_length
// setting (settings.php) — null (not a literal 500) since a function call
// isn't a legal PHP default value; pass an explicit int to override it for
// one call.
function articleContentPreview(array $article, ?int $maxLength = null): string
{
    $maxLength = $maxLength ?? (int) siteSetting('preview_max_length', 500);
    $content = $article['content'] ?? '';
    $firstParagraph = preg_match('/<p[^>]*>(.*?)<\/p>/is', $content, $m) ? $m[1] : $content;

    $text = trim(preg_replace('/\s+/u', ' ', strip_tags($firstParagraph)));
    if ($text === '' || mb_strlen($text) <= $maxLength) {
        return $text;
    }

    $truncated = trimTrailingThaiIncomplete(mb_substr($text, 0, $maxLength));

    return $truncated . '…';
}

// Uses the manually-picked featured image if the author set one, otherwise
// falls back to the first image found in the content, then to the cover of
// the first embedded YouTube video (see embedYoutubeLinks() in
// assets/editor.js for how a <iframe class="ql-video"> ends up in content).
// Returns a path relative to the site root, or a full https:// URL for the
// YouTube case, or null if there's nothing to show at all.
function articleFeaturedImage(array $article): ?string
{
    if (!empty($article['featured_image'])) {
        return $article['featured_image'];
    }

    $content = $article['content'] ?? '';

    if (preg_match('/<img[^>]+src="([^"]+)"/i', $content, $m)) {
        return $m[1];
    }

    // hqdefault.jpg (480x360) is generated by YouTube for every video, unlike
    // maxresdefault.jpg which 404s for plenty of them — no HTTP check needed.
    if (preg_match('#<iframe[^>]+class="ql-video"[^>]+src="https://www\.youtube\.com/embed/([\w-]{11})#i', $content, $m)) {
        return 'https://img.youtube.com/vi/' . $m[1] . '/hqdefault.jpg';
    }

    return null;
}

// article.php/page.php need a fully-qualified URL for og:image/twitter:image
// (a bare relative "uploads/xxx.png" won't do there) — this adds siteBaseUrl()
// for a local upload/content image, but passes an already-absolute URL (the
// YouTube-cover fallback above) through untouched instead of mangling it into
// "https://oursite.com/https://img.youtube.com/...".
function articleFeaturedImageUrl(array $article): ?string
{
    $image = articleFeaturedImage($article);
    if (!$image) {
        return null;
    }

    return preg_match('#^https?://#i', $image) ? $image : siteBaseUrl() . '/' . ltrim($image, '/');
}

// show_sidebar: NULL means "follow the site-wide sidebar_enabled setting,
// live" (not a snapshot — matches every article's behavior before this
// column existed), 1/0 force it on/off for this one article/page regardless
// of the site setting. Used by article.php/page.php only — list pages
// (articles.php, category.php, ...) have no single item to hang this off of,
// so they keep consulting sidebar_enabled directly via partials/header.php.
function articleShowsSidebar(array $article): bool
{
    if (!isset($article['show_sidebar']) || $article['show_sidebar'] === null) {
        return siteSetting('sidebar_enabled', '1') === '1';
    }

    return (bool) $article['show_sidebar'];
}

// MySQL DATETIME ("Y-m-d H:i:s") -> ISO 8601 ("c") so display text, OG tags
// and JSON-LD look exactly like they did back when date('c') was used to
// write the JSON files.
function normalizeArticleRow(array $row): array
{
    foreach (['created_at', 'updated_at', 'published_at', 'expires_at'] as $field) {
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
// "title" for the full date/time shown on hover (rendered via the
// [data-tooltip] CSS component in assets/components.css, not a native <span
// title="">, since native tooltips have a browser-fixed ~1s hover delay we
// can't shorten). Both read off PHP's current timezone, which config.php
// already pins to siteSetting('timezone') (Asia/Bangkok) — so this needs no
// timezone conversion of its own.
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

    return '<span data-tooltip="' . htmlspecialchars($rt['title']) . '">' . htmlspecialchars($rt['text']) . '</span>';
}

// $orderBy defaults to updated_at (edit-recency) since that's the only sort
// that makes sense for getDraftArticles() — a draft may have no published_at
// at all yet. Published-only lists pass 'a.published_at DESC' explicitly, to
// match the published_at now shown next to them (see relativeTimeTag()).
//
// "a.deleted_at IS NULL" is baked in here (not left to each caller) so a
// trashed article can never leak back out through any of the functions below
// — getArticlesForAdmin() is the one deliberate exception, and it bypasses
// this helper entirely with its own query for exactly that reason.
function fetchArticles(string $whereSql, array $params, string $orderBy = 'a.updated_at DESC'): array
{
    $stmt = db()->prepare(
        'SELECT a.*, c.name AS category, c.slug AS category_slug, c.color AS category_color
         FROM mblog_articles a
         LEFT JOIN mblog_categories c ON a.category_id = c.id
         WHERE a.deleted_at IS NULL AND (' . $whereSql . ')
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
         WHERE a.deleted_at IS NULL AND (' . $whereSql . ')
         LIMIT 1'
    );
    $stmt->execute($params);
    $row = $stmt->fetch();

    return $row ? normalizeArticleRow($row) : null;
}

// Unified query behind the five "browse" screens (articles.php, pages.php,
// category.php, tag.php, drafts.php) — one query builder instead of five
// near-identical ones, so a new filter or pagination lands everywhere at
// once instead of needing to be copied five times. $filters: type
// ('post'/'page', omit for both — used by drafts.php which mixes them),
// status ('published'/'draft', omit for either), category_slug, tag_slug.
// $perPage = 0 means "no pagination, return every match in one page".
// Returns ['items' => [...], 'total' => N] so the caller can render
// "หน้า X จาก Y" without a second query.
function getArticleList(array $filters, int $page = 1, int $perPage = 0, bool $stickyFirst = false): array
{
    $joinSql = '';
    $joinParams = [];
    if (!empty($filters['tag_slug'])) {
        $joinSql = 'JOIN mblog_article_tag lat ON lat.article_id = a.id
                    JOIN mblog_tags lt ON lt.id = lat.tag_id AND lt.slug = ?';
        $joinParams[] = $filters['tag_slug'];
    }

    $where = ['a.deleted_at IS NULL'];
    $params = [];
    if (!empty($filters['type'])) {
        $where[] = 'a.type = ?';
        $params[] = $filters['type'];
    }
    if (!empty($filters['status'])) {
        if ($filters['status'] === 'published') {
            $where[] = publicVisibilitySql();
        } else {
            $where[] = 'a.status = ?';
            $params[] = $filters['status'];
        }
    }
    if (!empty($filters['category_slug'])) {
        $where[] = 'c.slug = ?';
        $params[] = $filters['category_slug'];
    }
    if (($filters['search'] ?? '') !== '') {
        $where[] = 'a.title LIKE ?';
        $params[] = '%' . $filters['search'] . '%';
    }
    // 'author' role — same articleOwnerFilter() filter getArticlesForAdmin()
    // uses, opt-in per caller (drafts.php passes it, others don't).
    if (!empty($filters['author_id'])) {
        $where[] = 'a.author_id = ?';
        $params[] = (int) $filters['author_id'];
    }

    $whereSql = implode(' AND ', $where);
    $allParams = array_merge($joinParams, $params);
    // A draft may have no published_at yet, so drafts sort by edit-recency;
    // everything else sorts by publish date, matching what's shown next to
    // each item (see relativeTimeTag() calls in partials/article-list.php).
    $orderBy = ($filters['status'] ?? '') === 'draft' ? 'a.updated_at DESC' : 'a.published_at DESC';

    // Sticky ids come from getStickyArticleIds(), already int-cast — inlined
    // into ORDER BY the same way $perPage/$offset are inlined below (no
    // injection risk), since there's no ? placeholder for "sort these rows
    // first". Opt-in per call ($stickyFirst) rather than always-on: only the
    // main public lists (articles.php/pages.php) want pinned items to float
    // up — category.php/tag.php/drafts.php etc. all share this same
    // function but query a narrower slice where "pinned to the top" doesn't
    // apply. The WHERE clause's own type/status filters already mean a
    // pinned post's id simply never matches while querying pages (and vice
    // versa), so passing $stickyFirst=true from both callers is safe — each
    // only ever promotes ids that belong in its own result set. FIELD()
    // sorts sticky rows by their position in the array (the admin-picked
    // order from sticky-items.php), not just "sticky vs not".
    if ($stickyFirst) {
        $stickyIds = getStickyArticleIds();
        if ($stickyIds) {
            $idList = implode(',', $stickyIds);
            $orderBy = "CASE WHEN a.id IN ($idList) THEN 0 ELSE 1 END, FIELD(a.id, $idList), " . $orderBy;
        }
    }

    $countStmt = db()->prepare(
        "SELECT COUNT(*) FROM mblog_articles a
         LEFT JOIN mblog_categories c ON a.category_id = c.id
         $joinSql
         WHERE $whereSql"
    );
    $countStmt->execute($allParams);
    $total = (int) $countStmt->fetchColumn();

    // $perPage/$offset are inlined (not bound as ? params) — same reasoning
    // as getArticlesForAdmin(): PDO's LIMIT/OFFSET binding is unreliable
    // across drivers, and both are already int-cast so there's no injection risk.
    $limitSql = '';
    if ($perPage > 0) {
        $perPage = max(1, $perPage);
        $offset = max(0, ($page - 1) * $perPage);
        $limitSql = "LIMIT $perPage OFFSET $offset";
    }

    $stmt = db()->prepare(
        "SELECT a.*, c.name AS category, c.slug AS category_slug, c.color AS category_color
         FROM mblog_articles a
         LEFT JOIN mblog_categories c ON a.category_id = c.id
         $joinSql
         WHERE $whereSql
         ORDER BY $orderBy
         $limitSql"
    );
    $stmt->execute($allParams);
    $items = array_map('normalizeArticleRow', $stmt->fetchAll());

    return ['items' => $items, 'total' => $total];
}

// Public search (search.php) — published posts only, never pages/drafts/
// trash. Plain LIKE, not a FULLTEXT index: MySQL/MariaDB's default FULLTEXT
// parser splits words on whitespace, and Thai has none between words, so it
// would barely match anything without the extra ngram parser config — LIKE's
// substring match works regardless of that, and needs no schema change.
// Title/tag matches rank above content-only matches; published_at breaks ties.
// Tags deliberately included, category deliberately not: a tag is a precise
// signal an author chose on purpose, while a category is a broad bucket that
// would pull in a lot of loosely-related noise for a common query word.
// Matched via EXISTS rather than a JOIN to mblog_tags — an article can have
// several matching tags, and a plain JOIN would return one row per match,
// throwing off both COUNT(*) and pagination.
// Returns ['items' => [...], 'total' => N] so the caller can paginate.
function searchArticles(string $query, int $page, int $perPage): array
{
    $like = '%' . $query . '%';
    $tagMatchSql = "EXISTS (
        SELECT 1 FROM mblog_article_tag mat
        JOIN mblog_tags t ON t.id = mat.tag_id
        WHERE mat.article_id = a.id AND t.name LIKE ?
    )";
    $visibilitySql = publicVisibilitySql();

    $countStmt = db()->prepare(
        "SELECT COUNT(*) FROM mblog_articles a
         WHERE a.type = 'post' AND $visibilitySql AND a.deleted_at IS NULL
           AND (a.title LIKE ? OR a.content LIKE ? OR $tagMatchSql)"
    );
    $countStmt->execute([$like, $like, $like]);
    $total = (int) $countStmt->fetchColumn();

    // $perPage/$offset inlined, not bound — same reasoning as
    // getArticlesForAdmin(): both are already int-cast, no injection risk.
    $perPage = max(1, $perPage);
    $offset = max(0, ($page - 1) * $perPage);
    $stmt = db()->prepare(
        "SELECT a.*, c.name AS category, c.slug AS category_slug, c.color AS category_color,
                (CASE WHEN a.title LIKE ? THEN 2 ELSE 0 END
                 + CASE WHEN a.content LIKE ? THEN 1 ELSE 0 END
                 + CASE WHEN $tagMatchSql THEN 2 ELSE 0 END) AS relevance
         FROM mblog_articles a
         LEFT JOIN mblog_categories c ON a.category_id = c.id
         WHERE a.type = 'post' AND $visibilitySql AND a.deleted_at IS NULL
           AND (a.title LIKE ? OR a.content LIKE ? OR $tagMatchSql)
         ORDER BY relevance DESC, a.published_at DESC
         LIMIT $perPage OFFSET $offset"
    );
    $stmt->execute([$like, $like, $like, $like, $like, $like]);
    $items = array_map('normalizeArticleRow', $stmt->fetchAll());

    return ['items' => $items, 'total' => $total];
}

// article.php for a post, page.php for a page — the "read this" link shown
// by partials/article-list.php whenever an item is published.
function articleViewUrl(array $article): string
{
    $script = ($article['type'] ?? 'post') === 'page' ? 'page.php' : 'article.php';

    return $script . '?slug=' . urlencode($article['slug']);
}

// Public article list — published posts only (not pages — see getPages()).
// Thin wrapper kept for sitemap.php/admin.php, which want every match in one
// go rather than one page at a time.
function getArticles(): array
{
    return getArticleList(['type' => 'post', 'status' => 'published'])['items'];
}

// Which page numbers to show in a WP-style pagination bar (1, current-delta
// .. current+delta, total — with '...' filling any gap) instead of every
// single page number once there are many. Returns a flat list of ints and
// '...' strings for the template to loop over; page-1 and last-page links
// are handled separately by the template since they're always shown, not
// windowed.
function paginationWindow(int $current, int $total, int $delta = 2): array
{
    $range = [];
    for ($i = max(1, $current - $delta); $i <= min($total, $current + $delta); $i++) {
        $range[] = $i;
    }

    if ($range[0] > 1) {
        if ($range[0] > 2) {
            array_unshift($range, '...');
        }
        array_unshift($range, 1);
    }

    $last = end($range);
    if ($last < $total) {
        if ($last < $total - 1) {
            $range[] = '...';
        }
        $range[] = $total;
    }

    return $range;
}

// Public page list — published pages only (About/Contact/Privacy Policy/...).
// Pages aren't browsed as a feed like posts; mainly used by sitemap.php.
function getPages(): array
{
    return getArticleList(['type' => 'page', 'status' => 'published'])['items'];
}

// Draft list — for the "ร่าง" screen so drafts stay findable now that they're
// hidden from the public list, and for admin.php's count. No ownership check
// yet (no login system exists yet — see PLANNING.md), so this is visible to
// anyone for now.
function getDraftArticles(): array
{
    return getArticleList(['status' => 'draft'])['items'];
}

// Public single-article lookup — published posts only (a draft's URL is not
// viewable directly either, not just hidden from the list).
function getArticle(string $slug): ?array
{
    return fetchOneArticle('a.slug = ? AND a.type = ? AND ' . publicVisibilitySql(), [$slug, 'post']);
}

// Direct-link lookup for a 'private' article/page — never listed publicly
// (excluded by publicVisibilitySql() everywhere else), openable only by
// slug + staff login. article.php/page.php call this as a fallback when
// getArticle()/getPage() finds nothing, then gate rendering on
// currentStaff() themselves (see article.php).
function getPrivateArticle(string $slug, string $type): ?array
{
    return fetchOneArticle(
        "a.slug = ? AND a.type = ? AND a.status = 'private' AND (a.expires_at IS NULL OR a.expires_at > NOW())",
        [$slug, $type]
    );
}

// Public single-page lookup — same idea as getArticle() but for pages
// (About/Contact/Privacy Policy/...), used by page.php.
function getPage(string $slug): ?array
{
    return fetchOneArticle('a.slug = ? AND a.type = ? AND ' . publicVisibilitySql(), [$slug, 'page']);
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

// Counts for the status tabs on manage-list.php ("ทั้งหมด (N)" etc.) — one
// query instead of 4, since the admin screen needs all of them at once on
// every load regardless of which tab is active. $type: 'post' or 'page',
// same discriminator as everywhere else — shared by manage-articles.php and
// manage-pages.php (see includes/manage-list.php).
function getArticleStatusCounts(string $type = 'post'): array
{
    $stmt = db()->prepare(
        "SELECT
            SUM(CASE WHEN deleted_at IS NULL THEN 1 ELSE 0 END) AS all_count,
            SUM(CASE WHEN deleted_at IS NULL AND status = 'published' THEN 1 ELSE 0 END) AS published_count,
            SUM(CASE WHEN deleted_at IS NULL AND status = 'draft' THEN 1 ELSE 0 END) AS draft_count,
            SUM(CASE WHEN deleted_at IS NOT NULL THEN 1 ELSE 0 END) AS trash_count
         FROM mblog_articles WHERE type = ?"
    );
    $stmt->execute([$type]);
    $row = $stmt->fetch();

    return [
        'all' => (int) $row['all_count'],
        'published' => (int) $row['published_count'],
        'draft' => (int) $row['draft_count'],
        'trash' => (int) $row['trash_count'],
    ];
}

// Backs manage-articles.php/manage-pages.php (via includes/manage-list.php)
// — deliberately bypasses fetchArticles() (which always excludes trashed
// rows) since this is the one screen that needs to see into the trash.
// $filters: type ('post'/'page', defaults to 'post'), status
// ('all'/'published'/'draft'/'trash'), search (title LIKE), category_id,
// tag_slug, date_from/date_to (against created_at, not published_at — a
// draft may never have a published_at).
// Returns ['items' => [...], 'total' => N] so the caller can paginate.
function getArticlesForAdmin(array $filters, int $page, int $perPage): array
{
    $joinSql = '';
    $joinParams = [];
    if (!empty($filters['tag_slug'])) {
        $joinSql = 'JOIN mblog_article_tag fat ON fat.article_id = a.id
                    JOIN mblog_tags ft ON ft.id = fat.tag_id AND ft.slug = ?';
        $joinParams[] = $filters['tag_slug'];
    }

    $where = ['a.type = ?'];
    $params = [$filters['type'] ?? 'post'];

    $status = $filters['status'] ?? 'all';
    if ($status === 'trash') {
        $where[] = 'a.deleted_at IS NOT NULL';
    } else {
        $where[] = 'a.deleted_at IS NULL';
        if (in_array($status, ['published', 'draft'], true)) {
            $where[] = 'a.status = ?';
            $params[] = $status;
        }
    }

    if (($filters['search'] ?? '') !== '') {
        $where[] = 'a.title LIKE ?';
        $params[] = '%' . $filters['search'] . '%';
    }
    if (($filters['category_id'] ?? '') === 'none') {
        // 'none' is a UI-level sentinel (see manage-articles.php's filter
        // dropdown), not a real category — category_id itself is never the
        // string 'none' in the DB, uncategorized articles just have NULL.
        $where[] = 'a.category_id IS NULL';
    } elseif (!empty($filters['category_id'])) {
        $where[] = 'a.category_id = ?';
        $params[] = (int) $filters['category_id'];
    }
    if (($filters['date_from'] ?? '') !== '') {
        $where[] = 'a.created_at >= ?';
        $params[] = $filters['date_from'] . ' 00:00:00';
    }
    if (($filters['date_to'] ?? '') !== '') {
        $where[] = 'a.created_at <= ?';
        $params[] = $filters['date_to'] . ' 23:59:59';
    }
    // 'author' role — manage-articles.php/manage-pages.php pass
    // articleOwnerFilter() here (includes/auth.php), which is null for
    // admin/editor (no restriction) and the current user's id for author
    // (sees only their own articles).
    if (!empty($filters['author_id'])) {
        $where[] = 'a.author_id = ?';
        $params[] = (int) $filters['author_id'];
    }

    $whereSql = implode(' AND ', $where);
    $allParams = array_merge($joinParams, $params);

    $countStmt = db()->prepare("SELECT COUNT(*) FROM mblog_articles a $joinSql WHERE $whereSql");
    $countStmt->execute($allParams);
    $total = (int) $countStmt->fetchColumn();

    // $perPage/$offset are inlined (not bound as ? params) — PDO's LIMIT/OFFSET
    // binding is unreliable across drivers, and both are already int-cast
    // above/below so there's no injection risk.
    $perPage = max(1, $perPage);
    $offset = max(0, ($page - 1) * $perPage);
    $stmt = db()->prepare(
        "SELECT a.*, c.name AS category, c.slug AS category_slug, c.color AS category_color
         FROM mblog_articles a
         LEFT JOIN mblog_categories c ON a.category_id = c.id
         $joinSql
         WHERE $whereSql
         ORDER BY a.created_at DESC
         LIMIT $perPage OFFSET $offset"
    );
    $stmt->execute($allParams);
    $items = array_map('normalizeArticleRow', $stmt->fetchAll());

    return ['items' => $items, 'total' => $total];
}

// Sticky posts/pages — pinned to the top of articles.php/pages.php in a
// custom admin-picked order, regardless of publish date. Stored as a JSON
// array of article ids in mblog_settings (same pattern as WordPress's
// wp_options 'sticky_posts') instead of a column on mblog_articles, so
// pinning/unpinning never needs an ALTER TABLE — array order IS display
// order (first id shows first among stickies), managed entirely from
// sticky-items.php's own picker+reorder form (see setStickyArticles()) —
// manage-articles.php/manage-pages.php have no pin control of their own.
function getStickyArticleIds(): array
{
    $decoded = json_decode(siteSetting('sticky_article_ids', '[]'), true);

    return is_array($decoded) ? array_values(array_unique(array_map('intval', $decoded))) : [];
}

function isStickyArticle(int $id): bool
{
    return in_array($id, getStickyArticleIds(), true);
}

// Replaces the whole pinned set in one go, in the given order. Safe to call
// with "the complete current pinned set, reordered" (sticky-items.php's own
// reorder form always submits every pinned row, never a filtered subset) —
// NOT safe to call with an arbitrary partial list, which would silently
// unpin anything left out. pinArticle()/unpinArticle() below are what
// single search-result rows actually call, precisely to avoid that trap.
function setStickyArticles(array $orderedIds): void
{
    $ids = array_values(array_unique(array_map('intval', $orderedIds)));
    updateSiteSettings(['sticky_article_ids' => json_encode($ids)]);
}

// Appends to the end (new pins show after existing ones until manually
// reordered) — the single-row "ปักหมุด" button in sticky-items.php's search
// results calls this instead of setStickyArticles(), so pinning one more
// item never depends on which other rows happened to be on screen at the
// time (a search filter, in particular, only ever shows a subset).
function pinArticle(int $id): void
{
    $ids = getStickyArticleIds();
    if (!in_array($id, $ids, true)) {
        setStickyArticles(array_merge($ids, [$id]));
    }
}

function unpinArticle(int $id): void
{
    setStickyArticles(array_diff(getStickyArticleIds(), [$id]));
}

// Called from bulkPermanentlyDeleteArticles() — a stale id left in this
// array wouldn't point at a different article later (MySQL never reuses an
// id), so it's not unsafe, just untidy, so clear it out at the one point an
// id is actually gone for good rather than leaving it to accumulate forever.
function unstickArticles(array $ids): void
{
    $remaining = array_values(array_diff(getStickyArticleIds(), array_map('intval', $ids)));
    updateSiteSettings(['sticky_article_ids' => json_encode($remaining)]);
}

// Moves articles to the trash — sets deleted_at instead of touching status,
// so restoreArticles() knows whether to bring each one back as a draft or
// as published. $ids empty is a no-op (not an error) so callers don't need
// to special-case "nothing selected".
function bulkTrashArticles(array $ids): void
{
    if (!$ids) {
        return;
    }
    $stmt = db()->prepare('UPDATE mblog_articles SET deleted_at = ? WHERE id = ?');
    $now = date('Y-m-d H:i:s');
    foreach ($ids as $id) {
        $stmt->execute([$now, (int) $id]);
    }
}

function bulkRestoreArticles(array $ids): void
{
    if (!$ids) {
        return;
    }
    $stmt = db()->prepare('UPDATE mblog_articles SET deleted_at = NULL WHERE id = ?');
    foreach ($ids as $id) {
        $stmt->execute([(int) $id]);
    }
}

// Only reachable from the trash tab — permanent, no recovery. DB cascades
// the tag/image/redirect rows via FK, same as everywhere else in this app;
// uploaded files on disk are left untouched (same reasoning as
// syncArticleImages() — never auto-delete a file that might still be used).
function bulkPermanentlyDeleteArticles(array $ids): void
{
    if (!$ids) {
        return;
    }
    $stmt = db()->prepare('DELETE FROM mblog_articles WHERE id = ?');
    foreach ($ids as $id) {
        $stmt->execute([(int) $id]);
    }
    unstickArticles($ids);
}

// Bulk publish/unpublish from manage-articles.php — same published_at-set-
// once rule as api/save.php (switching back to draft and republishing later
// doesn't reset it).
function bulkUpdateArticleStatus(array $ids, string $status): void
{
    if (!$ids || !in_array($status, ['draft', 'published'], true)) {
        return;
    }
    $now = date('Y-m-d H:i:s');
    $stmt = db()->prepare(
        "UPDATE mblog_articles
         SET status = ?, updated_at = ?,
             published_at = CASE WHEN ? = 'published' AND published_at IS NULL THEN ? ELSE published_at END
         WHERE id = ?"
    );
    foreach ($ids as $id) {
        $stmt->execute([$status, $now, $status, $now, (int) $id]);
    }
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
