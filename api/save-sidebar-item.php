<?php
header('Content-Type: application/json; charset=utf-8');
require __DIR__ . '/../includes/sidebar.php';
require __DIR__ . '/../includes/uploads.php';

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

$title = isset($data['title']) ? trim($data['title']) : '';
$content = isset($data['content']) ? $data['content'] : '';
$linkUrl = isset($data['link_url']) ? trim($data['link_url']) : '';
$isActive = !empty($data['is_active']) ? 1 : 0;

// Quill never sends a true empty string — an untouched editor still submits
// "<p><br></p>" — so store NULL instead of that markup whenever there's no
// real text AND no image/video in it, otherwise partials/footer.php's
// !empty($item['content']) check would still render an empty content box
// under the image. Checked for "<img"/"<iframe" before stripping tags so a
// photo (pasted directly rather than via the separate "รูป" field) or a
// YouTube embed (see embedYoutubeLinks() in assets/editor.js) isn't mistaken
// for empty content — strip_tags() removes both of those tags entirely,
// leaving no text behind.
if (trim(strip_tags($content)) === '' && !str_contains($content, '<img') && !str_contains($content, '<iframe')) {
    $content = null;
}

// Same validation as api/save.php's featured_image — see sanitizeFeaturedImagePath().
$image = sanitizeFeaturedImagePath((string) ($data['image'] ?? ''));

if ($title === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'title is required']);
    exit;
}

$id = isset($data['id']) && $data['id'] !== '' ? (int) $data['id'] : null;
$existing = $id !== null ? getSidebarItemById($id) : null;

if ($existing) {
    updateSidebarItem($existing['id'], $title, $content, $image, $linkUrl, $isActive);
    $itemId = (int) $existing['id'];
} else {
    $itemId = createSidebarItem($title, $content, $image, $linkUrl, $isActive, nextSidebarSortOrder());
}

// Same featured-image-only cleanup as api/save.php — an image removed from
// inside the rich-text body isn't touched here.
if ($existing && !empty($existing['image']) && $existing['image'] !== $image) {
    deleteUploadIfUnused($existing['image']);
}

echo json_encode(['success' => true, 'id' => $itemId]);
