<?php
header('Content-Type: application/json; charset=utf-8');
require __DIR__ . '/../includes/sidebar.php';

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

$title = isset($data['title']) ? trim($data['title']) : '';
$content = isset($data['content']) ? $data['content'] : '';
$linkUrl = isset($data['link_url']) ? trim($data['link_url']) : '';
$isActive = !empty($data['is_active']) ? 1 : 0;

// Quill never sends a true empty string — an untouched editor still submits
// "<p><br></p>" — so store NULL instead of that markup whenever there's no
// real text AND no image in it, otherwise partials/footer.php's
// !empty($item['content']) check would still render an empty content box
// under the image. Checked for "<img" before stripping tags so a photo
// pasted directly into the editor (rather than the separate "รูป" field)
// isn't mistaken for empty content.
if (trim(strip_tags($content)) === '' && !str_contains($content, '<img')) {
    $content = null;
}

// Same validation as api/save.php's featured_image — only accept a path that
// looks like one of our own uploads, never trust an arbitrary URL.
$image = isset($data['image']) ? trim($data['image']) : '';
if ($image !== '' && (
    str_contains($image, '..')
    || !preg_match('#^uploads/[\p{L}\p{N}\p{M}_./-]+\.(jpg|jpeg|png|gif|webp)$#iu', $image)
)) {
    $image = '';
}

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

echo json_encode(['success' => true, 'id' => $itemId]);
