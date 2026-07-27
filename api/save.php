<?php
header('Content-Type: application/json; charset=utf-8');
require __DIR__ . '/../includes/articles.php';
require __DIR__ . '/../includes/uploads.php';

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

$title = isset($data['title']) ? trim($data['title']) : '';
$content = isset($data['content']) ? $data['content'] : '';
$requestedSlug = isset($data['slug']) ? trim($data['slug']) : '';
$status = (isset($data['status']) && $data['status'] === 'published') ? 'published' : 'draft';
$type = (isset($data['type']) && $data['type'] === 'page') ? 'page' : 'post';
$excerpt = isset($data['excerpt']) ? trim($data['excerpt']) : '';

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

// published_at is set once, the first time an article goes live, and kept
// after that — switching back to draft and re-publishing doesn't reset it.
$publishedAt = $existing['published_at'] ?? null;
if ($status === 'published' && $publishedAt === null) {
    $publishedAt = $now;
} elseif ($publishedAt !== null) {
    $publishedAt = date('Y-m-d H:i:s', strtotime($publishedAt));
}

if ($existing) {
    $stmt = db()->prepare(
        'UPDATE mblog_articles
         SET slug = ?, title = ?, content = ?, excerpt = ?, category_id = ?, featured_image = ?,
             show_sidebar = ?, status = ?, type = ?, updated_at = ?, published_at = ?
         WHERE id = ?'
    );
    $stmt->execute([$slug, $title, $content, $excerpt, $categoryId, $featuredImage, $showSidebar, $status, $type, $now, $publishedAt, $existing['id']]);
    $articleId = (int) $existing['id'];

    if ($existing['slug'] !== $slug) {
        recordSlugRedirect($existing['slug'], $articleId);
    }
} else {
    $stmt = db()->prepare(
        'INSERT INTO mblog_articles
            (slug, title, content, excerpt, category_id, featured_image, show_sidebar, status, type, created_at, updated_at, published_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([$slug, $title, $content, $excerpt, $categoryId, $featuredImage, $showSidebar, $status, $type, $now, $now, $publishedAt]);
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
