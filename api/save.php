<?php
header('Content-Type: application/json; charset=utf-8');
require __DIR__ . '/../includes/articles.php';

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

$title = isset($data['title']) ? trim($data['title']) : '';
$content = isset($data['content']) ? $data['content'] : '';
$slug = isset($data['slug']) ? trim($data['slug']) : '';
$status = (isset($data['status']) && $data['status'] === 'published') ? 'published' : 'draft';
$excerpt = isset($data['excerpt']) ? trim($data['excerpt']) : '';

// Only accept a path that looks like one of our own uploads (matches the
// naming api/upload.php produces) — never trust an arbitrary URL into og:image.
$featuredImage = isset($data['featured_image']) ? trim($data['featured_image']) : '';
if ($featuredImage !== '' && !preg_match('#^uploads/[A-Za-z0-9_.-]+\.(jpg|jpeg|png|gif|webp)$#i', $featuredImage)) {
    $featuredImage = '';
}

$categories = getCategories();
$category = isset($data['category']) ? trim($data['category']) : '';
if (!in_array($category, $categories, true)) {
    $category = $categories[0];
}

if ($title === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'title is required']);
    exit;
}

// Only accept an existing slug that matches our safe pattern and file;
// otherwise generate a fresh one to avoid path traversal / overwrite risk.
$existing = ($slug !== '') ? getArticleForEdit($slug) : null;

if (!$existing) {
    $slug = date('Ymd-His') . '-' . substr(bin2hex(random_bytes(3)), 0, 6);
}

$now = date('c');

// published_at is set once, the first time an article goes live, and kept
// after that — switching back to draft and re-publishing doesn't reset it.
$publishedAt = $existing['published_at'] ?? null;
if ($status === 'published' && $publishedAt === null) {
    $publishedAt = $now;
}

$article = [
    'slug' => $slug,
    'title' => $title,
    'content' => $content,
    'status' => $status,
    'category' => $category,
    'excerpt' => $excerpt,
    'featured_image' => $featuredImage,
    'created_at' => $existing['created_at'] ?? $now,
    'published_at' => $publishedAt,
    'updated_at' => $now,
];

$ok = file_put_contents(
    getArticlesDir() . $slug . '.json',
    json_encode($article, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
);

if ($ok === false) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'could not write file']);
    exit;
}

echo json_encode(['success' => true, 'slug' => $slug, 'status' => $status]);
