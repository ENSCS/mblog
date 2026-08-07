<?php
header('Content-Type: application/json; charset=utf-8');
require __DIR__ . '/../config.php';
require __DIR__ . '/../includes/uploads.php';
requireApiCapability('edit_articles');
verifyCsrf($_POST['csrf_token'] ?? null, true);
ensureUploadsHtaccess();

$uploadsDir = UPLOADS_DIR;
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

// WP-style: keep the original filename (sanitized) instead of a random one —
// readable image URLs help SEO. Collisions are handled by appending -1, -2,
// ... the same way WP's wp_unique_filename() does.
function sanitizeUploadFilename(string $original): string
{
    $name = trim(pathinfo($original, PATHINFO_FILENAME));
    $name = preg_replace('/\s+/u', '-', $name);
    // Keep letters, digits, combining marks (Thai vowels/tone marks are marks,
    // not letters, in Unicode — dropping \p{M} would break Thai words),
    // hyphen, underscore — replace everything else.
    $name = preg_replace('/[^\p{L}\p{N}\p{M}_-]+/u', '-', $name);
    $name = preg_replace('/-+/', '-', $name);
    $name = trim($name, '-_');

    // Cap by BYTE length, not character count — filesystems limit filenames
    // to ~255 bytes, and scripts like Thai/Chinese/Japanese/Arabic/Devanagari
    // use 2-4 bytes per character, so a 100-character cap can silently
    // produce a 300+ byte filename that fails to save on Linux (ext4/XFS).
    // Leaves room for the "-N" collision suffix + extension.
    while (strlen($name) > 150) {
        $name = mb_substr($name, 0, mb_strlen($name) - 1);
    }

    return $name !== '' ? $name : 'image';
}

function uniqueUploadFilename(string $dir, string $baseName, string $ext): string
{
    $filename = $baseName . '.' . $ext;
    $i = 1;
    while (file_exists($dir . $filename)) {
        $filename = $baseName . '-' . $i . '.' . $ext;
        $i++;
    }

    return $filename;
}

// Split into uploads/YYYY/MM/ so the folder doesn't fill up with thousands of
// flat files over time (path is stored as-is in mblog_images, so this is
// purely a filesystem-health concern, not tied to any article relationship).
$subPath = date('Y') . '/' . date('m') . '/';
$targetDir = $uploadsDir . $subPath;

if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
    http_response_code(500);
    echo json_encode(['error' => 'could not create upload folder']);
    exit;
}

$baseName = sanitizeUploadFilename($file['name']);
$filename = uniqueUploadFilename($targetDir, $baseName, $ext);

if (!move_uploaded_file($file['tmp_name'], $targetDir . $filename)) {
    http_response_code(500);
    echo json_encode(['error' => 'could not save file']);
    exit;
}

echo json_encode(['url' => 'uploads/' . $subPath . $filename]);
