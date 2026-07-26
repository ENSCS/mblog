<?php
// Direct push endpoint for the local summarizer script (see PLANNING.md
// section 7) — the other entry point (import-markdown.php) is a manual web
// upload for occasional/ad-hoc use, this one is for the daily automated
// pipeline. Both call the exact same importMarkdownArticle() so a
// conversion tweak lands on whichever entry point sent the file.
header('Content-Type: application/json; charset=utf-8');
require __DIR__ . '/../includes/markdown-import.php';

$expectedToken = siteSetting('markdown_import_token', '');
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

// Two ways to send the file, so the local script can use whichever is
// simpler for it: a real multipart file upload (field name "file"), or a
// raw request body with the filename in a header.
if (!empty($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
    $filename = $_FILES['file']['name'];
    $raw = file_get_contents($_FILES['file']['tmp_name']);
} else {
    $filename = $_SERVER['HTTP_X_FILENAME'] ?? '';
    $raw = file_get_contents('php://input');
}

if ($filename === '' || strtolower(pathinfo($filename, PATHINFO_EXTENSION)) !== 'md') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'ต้องระบุชื่อไฟล์ .md (multipart field "file" หรือ header X-Filename)']);
    exit;
}

if (trim($raw) === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'ไม่มีเนื้อหาไฟล์']);
    exit;
}

$result = importMarkdownArticle($raw, $filename);

http_response_code($result['success'] || $result['skipped'] ? 200 : 422);
echo json_encode($result, JSON_UNESCAPED_UNICODE);
