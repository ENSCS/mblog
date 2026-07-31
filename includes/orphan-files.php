<?php
// Orphan upload cleanup — catches files syncArticleImages()/deleteUploadIfUnused()
// (includes/uploads.php) deliberately don't touch: an image removed from an
// article's *body content* (not featured_image) is left on disk on purpose,
// to avoid false positives from regex-parsing <img> tags out of rich text.
// This sweeps uploads/ for files nothing references anymore and lets an
// admin review + delete them by hand — never automatically.

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/uploads.php';

const ORPHAN_SCAN_IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'ico'];

// Every real file under uploads/, minus ones still referenced anywhere
// uploadPathInUse() checks (articles, sidebar items, site logo/favicon).
// Housekeeping files (.DS_Store, .gitkeep) are skipped explicitly —
// FilesystemIterator::SKIP_DOTS only skips "." and "..", not dotfiles.
function scanOrphanUploads(): array
{
    $uploadsDir = rtrim(UPLOADS_DIR, '/');
    $candidates = [];

    if (is_dir($uploadsDir)) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($uploadsDir, FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            $filename = $file->getFilename();
            if ($filename[0] === '.') {
                continue;
            }

            $relativePath = 'uploads/' . substr($file->getPathname(), strlen($uploadsDir) + 1);
            if (uploadPathInUse($relativePath)) {
                continue;
            }

            $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            $candidates[] = [
                'relative_path' => $relativePath,
                'size' => $file->getSize(),
                'created_at' => date('Y-m-d H:i:s', $file->getMTime()), // for relativeTimeTag()
                'is_image' => in_array($extension, ORPHAN_SCAN_IMAGE_EXTENSIONS, true),
            ];
        }
    }

    usort($candidates, fn ($a, $b) => $b['size'] <=> $a['size']);

    return $candidates;
}

// Re-checks uploadPathInUse() again at delete time (not just scan time) —
// scan and delete are separate requests, so something could have started
// referencing the path in between. Same path-containment defense as
// includes/backup.php's resolveBackupPath(): only ever unlink()s a real file
// that resolves inside UPLOADS_DIR.
function deleteOrphanFiles(array $relativePaths): int
{
    $uploadsRealPath = realpath(UPLOADS_DIR);
    $deleted = 0;

    foreach ($relativePaths as $relativePath) {
        if (strpos($relativePath, 'uploads/') !== 0 || uploadPathInUse($relativePath)) {
            continue;
        }

        $fullPath = realpath(dirname(__DIR__) . '/' . $relativePath);
        if ($fullPath === false || strpos($fullPath, $uploadsRealPath) !== 0) {
            continue;
        }

        if (unlink($fullPath)) {
            $deleted++;
        }
    }

    return $deleted;
}
