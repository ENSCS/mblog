<?php
header('Content-Type: application/json; charset=utf-8');
require __DIR__ . '/../includes/articles.php';
require __DIR__ . '/../includes/uploads.php';
requireApiCapability('edit_articles');

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
verifyCsrf($data['csrf_token'] ?? null, true);

$title = isset($data['title']) ? trim($data['title']) : '';
$content = isset($data['content']) ? $data['content'] : '';
$requestedSlug = isset($data['slug']) ? trim($data['slug']) : '';
$status = in_array($data['status'] ?? '', ['published', 'scheduled'], true) ? $data['status'] : 'draft';
$type = (isset($data['type']) && $data['type'] === 'page') ? 'page' : 'post';
// '' stored as-is (not NULL) — articleSeoTitle()/articleSeoDescription() in
// includes/articles.php already treat '' the same as NULL (fall back to the
// real title / auto-generated description), so there's no behavior
// difference, and it keeps this INSERT/UPDATE simpler (no NULL-vs-''
// branching for 3 fields).
$seoTitle = isset($data['seo_title']) ? trim($data['seo_title']) : '';
$seoDescription = isset($data['seo_description']) ? trim($data['seo_description']) : '';
$seoNoindex = !empty($data['seo_noindex']) ? 1 : 0;

// '' (ตามค่าเว็บ) -> NULL, '1'/'0' -> บังคับเปิด/ปิด ไม่สน sidebar_enabled ของเว็บ —
// ดู show_sidebar ใน database/article_sidebar_toggle.sql สำหรับความหมายเต็ม
$showSidebarRaw = isset($data['show_sidebar']) ? (string) $data['show_sidebar'] : '';
$showSidebar = $showSidebarRaw === '1' ? 1 : ($showSidebarRaw === '0' ? 0 : null);

// See sanitizeFeaturedImagePath() — accepts one of our own uploads or a
// plain external http(s) URL, '' otherwise.
$featuredImage = sanitizeFeaturedImagePath((string) ($data['featured_image'] ?? ''));

// Pages don't have a category — it's a blog-content concept, doesn't apply
// to a standalone page like "About"/"Privacy Policy". For posts, category is
// optional — an empty (or unrecognized, e.g. a since-deleted category) value
// means "no category" rather than silently falling back to the first one.
if ($type === 'page') {
    $categoryId = null;
    $tagNames = [];
} else {
    $categories = getCategories();
    $category = isset($data['category']) ? trim($data['category']) : '';
    $categoryId = in_array($category, $categories, true) ? categoryIdByName($category) : null;
    $tagNames = isset($data['tags']) && is_array($data['tags']) ? $data['tags'] : [];
}

if ($title === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'title is required']);
    exit;
}

// Look up "which article is this" by id — not by slug, since the slug is now
// user-editable and can no longer double as a stable identifier.
$id = isset($data['id']) && $data['id'] !== '' ? (int) $data['id'] : null;
$existing = $id !== null ? getArticleById($id) : null;

// 'author' role can only ever touch their own articles — enforced here, not
// just as a UX nicety in editor.php's page load, since a POST straight to
// this endpoint would otherwise bypass that check entirely. admin/editor
// (edit_others_articles) are exempt; a brand-new article (no $existing yet)
// has no owner to conflict with.
if ($existing && (int) ($existing['author_id'] ?? 0) !== (int) (currentStaff()['id'] ?? 0) && !staffCan('edit_others_articles')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'ไม่มีสิทธิ์แก้ไขบทความนี้']);
    exit;
}

// Slug the author wants to save. Empty means: for a new article, suggest one
// from the title; for an existing one, leave the current slug untouched
// (an empty field is treated as "no change", not "regenerate").
if ($requestedSlug !== '') {
    $baseSlug = sanitizeSlug($requestedSlug);
} elseif ($existing) {
    $baseSlug = $existing['slug'];
} else {
    $baseSlug = sanitizeSlug($title);
}

if ($baseSlug === '') {
    $baseSlug = date('Ymd-His') . '-' . substr(bin2hex(random_bytes(3)), 0, 6);
}

$slug = uniqueSlug($baseSlug, $existing['id'] ?? null);

$now = date('Y-m-d H:i:s');
$existingPublishedAt = $existing['published_at'] ?? null;

if ($status === 'scheduled') {
    // The admin-picked date/time from editor.php's "ตั้งเวลาเผยแพร่" field —
    // falls back to now on a missing/unparseable value rather than leaving
    // published_at NULL, since publicVisibilitySql() requires a real
    // published_at to ever show this article once its status does allow it.
    $scheduledAtRaw = isset($data['scheduled_at']) ? trim($data['scheduled_at']) : '';
    $scheduledTs = $scheduledAtRaw !== '' ? strtotime($scheduledAtRaw) : false;
    $publishedAt = $scheduledTs !== false ? date('Y-m-d H:i:s', $scheduledTs) : $now;
} elseif ($status === 'published') {
    // "now" both the first time an article goes live AND when explicitly
    // publishing over a still-pending schedule (clicking "เผยแพร่" means
    // "make this live immediately", overriding whatever future date was set
    // before) — only a genuine republish of something already live in the
    // past keeps its original published_at, so toggling draft<->published
    // afterward doesn't keep resetting it.
    $publishedAt = ($existingPublishedAt === null || strtotime($existingPublishedAt) > strtotime($now))
        ? $now
        : date('Y-m-d H:i:s', strtotime($existingPublishedAt));
} else {
    $publishedAt = $existingPublishedAt !== null ? date('Y-m-d H:i:s', strtotime($existingPublishedAt)) : null;
}

// '' -> NULL (no expiration, the default/original behavior for every
// article saved before this column existed).
$expiresAtRaw = isset($data['expires_at']) ? trim($data['expires_at']) : '';
$expiresAtTs = $expiresAtRaw !== '' ? strtotime($expiresAtRaw) : false;
$expiresAt = $expiresAtTs !== false ? date('Y-m-d H:i:s', $expiresAtTs) : null;

if ($existing) {
    $stmt = db()->prepare(
        'UPDATE mblog_articles
         SET slug = ?, title = ?, content = ?, category_id = ?, featured_image = ?,
             show_sidebar = ?, status = ?, type = ?, updated_at = ?, published_at = ?, expires_at = ?,
             seo_title = ?, seo_description = ?, seo_noindex = ?
         WHERE id = ?'
    );
    $stmt->execute([$slug, $title, $content, $categoryId, $featuredImage, $showSidebar, $status, $type, $now, $publishedAt, $expiresAt, $seoTitle, $seoDescription, $seoNoindex, $existing['id']]);
    $articleId = (int) $existing['id'];

    if ($existing['slug'] !== $slug) {
        recordSlugRedirect($existing['slug'], $articleId);
    }
} else {
    $stmt = db()->prepare(
        'INSERT INTO mblog_articles
            (slug, title, content, category_id, featured_image, show_sidebar, status, type, author_id, created_at, updated_at, published_at, expires_at,
             seo_title, seo_description, seo_noindex)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([$slug, $title, $content, $categoryId, $featuredImage, $showSidebar, $status, $type, currentStaff()['id'], $now, $now, $publishedAt, $expiresAt, $seoTitle, $seoDescription, $seoNoindex]);
    $articleId = (int) db()->lastInsertId();
}

syncArticleImages($articleId, $content, $featuredImage);
syncArticleTags($articleId, $tagNames);

// Only the featured_image slot is cleaned up automatically here — an image
// removed from inside the rich-text body is left for mblog_images/
// syncArticleImages() bookkeeping only, same "never touch the filesystem
// from a regex guess" caution as before, just not extended to this new path.
if ($existing && !empty($existing['featured_image']) && $existing['featured_image'] !== $featuredImage) {
    deleteUploadIfUnused($existing['featured_image']);
}

echo json_encode(['success' => true, 'id' => $articleId, 'slug' => $slug, 'status' => $status, 'type' => $type]);
