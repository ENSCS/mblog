<?php
// Direct push endpoint for an external pipeline to post short feed items
// (e.g. stock price/earnings announcements) — same token-header pattern as
// api/import-markdown.php, but a separate token setting (feed_import_token)
// on purpose: this is a different external source than the Markdown
// pipeline, a leaked token here shouldn't also expose that one.
header('Content-Type: application/json; charset=utf-8');
require __DIR__ . '/../includes/feed.php';

$expectedToken = siteSetting('feed_import_token', '');
$providedToken = $_SERVER['HTTP_X_IMPORT_TOKEN'] ?? '';

// Empty setting means the feature is off entirely, not "any token works" —
// an unset/blank token must never be treated as a match.
if ($expectedToken === '' || !hash_equals($expectedToken, $providedToken)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'invalid or missing X-Import-Token']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'POST required']);
    exit;
}

// Raw body is the message text itself — no multipart/JSON wrapper needed,
// the source just posts plain text (see PLANNING.md, "คล้ายๆ twitter เดิม").
$content = trim(file_get_contents('php://input'));

if ($content === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'ไม่มีเนื้อหาข้อความ']);
    exit;
}

$id = createFeedItem($content);

echo json_encode(['success' => true, 'id' => $id], JSON_UNESCAPED_UNICODE);
