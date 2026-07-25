<?php
header('Content-Type: application/json; charset=utf-8');

$articlesDir = __DIR__ . '/../articles/';

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

$title = isset($data['title']) ? trim($data['title']) : '';
$content = isset($data['content']) ? $data['content'] : '';
$slug = isset($data['slug']) ? trim($data['slug']) : '';

if ($title === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'title is required']);
    exit;
}

// Only accept an existing slug that matches our safe pattern and file;
// otherwise generate a fresh one to avoid path traversal / overwrite risk.
$isValidExistingSlug = $slug !== ''
    && preg_match('/^[a-z0-9\-]+$/', $slug)
    && is_file($articlesDir . $slug . '.json');

if (!$isValidExistingSlug) {
    $slug = date('Ymd-His') . '-' . substr(bin2hex(random_bytes(3)), 0, 6);
}

$now = date('c');
$existing = null;
if ($isValidExistingSlug) {
    $existing = json_decode(file_get_contents($articlesDir . $slug . '.json'), true);
}

$article = [
    'slug' => $slug,
    'title' => $title,
    'content' => $content,
    'created_at' => $existing['created_at'] ?? $now,
    'updated_at' => $now,
];

$ok = file_put_contents(
    $articlesDir . $slug . '.json',
    json_encode($article, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
);

if ($ok === false) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'could not write file']);
    exit;
}

echo json_encode(['success' => true, 'slug' => $slug]);
