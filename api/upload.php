<?php
header('Content-Type: application/json; charset=utf-8');

$uploadsDir = __DIR__ . '/../uploads/';
$allowedExt = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['error' => 'no file uploaded']);
    exit;
}

$file = $_FILES['image'];

// Validate it's really an image (not just a renamed extension).
$imageInfo = @getimagesize($file['tmp_name']);
if ($imageInfo === false) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid image file']);
    exit;
}

$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if (!in_array($ext, $allowedExt, true)) {
    http_response_code(400);
    echo json_encode(['error' => 'unsupported file type']);
    exit;
}

if ($file['size'] > 8 * 1024 * 1024) {
    http_response_code(400);
    echo json_encode(['error' => 'file too large (max 8MB)']);
    exit;
}

$filename = date('Ymd-His') . '-' . substr(bin2hex(random_bytes(4)), 0, 8) . '.' . $ext;

if (!move_uploaded_file($file['tmp_name'], $uploadsDir . $filename)) {
    http_response_code(500);
    echo json_encode(['error' => 'could not save file']);
    exit;
}

echo json_encode(['url' => 'uploads/' . $filename]);
