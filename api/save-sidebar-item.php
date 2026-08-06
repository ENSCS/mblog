<?php
header('Content-Type: application/json; charset=utf-8');
require __DIR__ . '/../includes/sidebar.php';
require __DIR__ . '/../includes/uploads.php';
requireApiCapability('manage_sidebar');

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
verifyCsrf($data['csrf_token'] ?? null, true);

$title = isset($data['title']) ? trim($data['title']) : '';
$type = in_array($data['type'] ?? '', ['article', 'iframe'], true) ? $data['type'] : 'article';
$content = isset($data['content']) ? $data['content'] : '';
$linkUrl = isset($data['link_url']) ? trim($data['link_url']) : '';
$isActive = !empty($data['is_active']) ? 1 : 0;

// iframe_src is rendered straight into an <iframe src="..."> for every site
// visitor — unlike link_url (just a clicked-through <a href>, no validation
// anywhere else in this codebase either) this is the actual risk point, so
// it gets validated here specifically rather than trusted like the rest of
// this form. Invalid/empty just becomes null (renderer skips output) rather
// than failing the whole save.
$iframeSrc = trim((string) ($data['iframe_src'] ?? ''));
if ($iframeSrc !== '' && filter_var($iframeSrc, FILTER_VALIDATE_URL) && in_array(parse_url($iframeSrc, PHP_URL_SCHEME), ['http', 'https'], true)) {
    // keep as-is
} else {
    $iframeSrc = null;
}
$iframeHeight = isset($data['iframe_height']) && (int) $data['iframe_height'] > 0
    ? max(50, min(2000, (int) $data['iframe_height']))
    : 300;

// Per-type fields don't carry stale data for the type the item isn't using
// (e.g. switching an item from iframe back to article shouldn't leave an
// orphaned iframe_src sitting in the row).
if ($type === 'iframe') {
    $content = null;
    $linkUrl = '';
} else {
    $iframeSrc = null;
    $iframeHeight = null;
}

// Quill never sends a true empty string — an untouched editor still submits
// "<p><br></p>" — so store NULL instead of that markup whenever there's no
// real text AND no image/video in it, otherwise partials/footer.php's
// !empty($item['content']) check would still render an empty content box
// under the image. Checked for "<img"/"<iframe" before stripping tags so a
// photo (pasted directly rather than via the separate "รูป" field) or a
// YouTube embed (see embedYoutubeLinks() in assets/editor.js) isn't mistaken
// for empty content — strip_tags() removes both of those tags entirely,
// leaving no text behind. Skipped entirely for iframe-type items, whose
// $content is already forced to null above.
if ($content !== null && trim(strip_tags($content)) === '' && !str_contains($content, '<img') && !str_contains($content, '<iframe')) {
    $content = null;
}

// Same validation as api/save.php's featured_image — see sanitizeFeaturedImagePath().
$image = $type === 'iframe' ? '' : sanitizeFeaturedImagePath((string) ($data['image'] ?? ''));

if ($title === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'title is required']);
    exit;
}

$id = isset($data['id']) && $data['id'] !== '' ? (int) $data['id'] : null;
$existing = $id !== null ? getSidebarItemById($id) : null;

if ($existing) {
    updateSidebarItem($existing['id'], $title, $type, $content, $image, $linkUrl, $iframeSrc, $iframeHeight, $isActive);
    $itemId = (int) $existing['id'];
} else {
    $itemId = createSidebarItem($title, $type, $content, $image, $linkUrl, $iframeSrc, $iframeHeight, $isActive, nextSidebarSortOrder());
}

// Same featured-image-only cleanup as api/save.php — an image removed from
// inside the rich-text body isn't touched here.
if ($existing && !empty($existing['image']) && $existing['image'] !== $image) {
    deleteUploadIfUnused($existing['image']);
}

echo json_encode(['success' => true, 'id' => $itemId]);
