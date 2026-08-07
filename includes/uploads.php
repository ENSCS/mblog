<?php
// Shared by api/save.php and api/save-sidebar-item.php — both let an editor
// remove/replace an uploaded image, and both need to know whether the old
// path is safe to physically delete before doing it. Both callers must run
// this AFTER their own UPDATE/INSERT has already landed, so the check below
// sees final state — including the case where the same image is still
// embedded inline in that very same article/item's body, which must count as
// "in use" too.
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/settings.php';

// Self-provisions uploads/.htaccess denying script execution — same reasoning
// as ensureBackupDir()'s .htaccess (includes/backup.php): uploads/ is
// git-ignored (see .gitignore) so nothing ships one on deploy. Every upload
// entry point (api/upload.php, staff/user avatar upload, site logo/favicon)
// calls this. Pure defense-in-depth — every current upload path already
// whitelists image extensions, but this stops a future weaker check (or a
// misconfigured host serving .php as CGI from anywhere) from turning an
// upload into script execution.
function ensureUploadsHtaccess(): void
{
    $htaccessPath = UPLOADS_DIR . '.htaccess';
    if (!file_exists($htaccessPath)) {
        file_put_contents($htaccessPath, "<FilesMatch \"\\.(php\\d*|phtml|phar)$\">\n    <IfModule mod_authz_core.c>\n        Require all denied\n    </IfModule>\n    <IfModule !mod_authz_core.c>\n        Deny from all\n    </IfModule>\n</FilesMatch>\n");
    }
}

// True if $path (an "uploads/..." relative path) is still referenced by any
// article's featured_image/content, any sidebar item's image/content, the
// site logo/favicon in mblog_settings, a staff avatar, or a user avatar —
// articles, sidebar items, site branding, and both avatar tables all share
// the same uploads/ pool, so all five need checking regardless of which
// caller is doing the deleting.
function uploadPathInUse(string $path): bool
{
    $stmt = db()->prepare('SELECT COUNT(*) FROM mblog_articles WHERE deleted_at IS NULL AND (featured_image = ? OR content LIKE ?)');
    $stmt->execute([$path, '%' . $path . '%']);
    if ((int) $stmt->fetchColumn() > 0) {
        return true;
    }

    $stmt = db()->prepare('SELECT COUNT(*) FROM mblog_sidebar_items WHERE image = ? OR content LIKE ?');
    $stmt->execute([$path, '%' . $path . '%']);
    if ((int) $stmt->fetchColumn() > 0) {
        return true;
    }

    $stmt = db()->prepare('SELECT COUNT(*) FROM mblog_staff WHERE avatar_path = ?');
    $stmt->execute([$path]);
    if ((int) $stmt->fetchColumn() > 0) {
        return true;
    }

    $stmt = db()->prepare('SELECT COUNT(*) FROM mblog_users WHERE avatar_path = ?');
    $stmt->execute([$path]);
    if ((int) $stmt->fetchColumn() > 0) {
        return true;
    }

    return $path === siteSetting('site_logo', '') || $path === siteSetting('site_favicon', '');
}

// No validation beyond trim() — same trust level as $content (api/save.php
// stores that raw too) and articleFeaturedImage()'s own YouTube-thumbnail
// fallback (includes/articles.php), which already builds and uses an
// external https://img.youtube.com/... URL with no check at all. This value
// is always htmlspecialchars()'d wherever it's printed, and the one place
// that touches the filesystem with it (deleteUploadIfUnused() below)
// independently re-validates before ever calling unlink() — so there's
// nothing left for a check here to protect that isn't already covered.
function sanitizeFeaturedImagePath(string $raw): string
{
    return trim($raw);
}

// Physically deletes an uploads/ file, but only once uploadPathInUse() above
// confirms nothing else still points at it — same caution as
// syncArticleImages()'s "an orphaned row is cheap to fix, a deleted file used
// elsewhere isn't", except here the check has actually been done, so it's
// safe to touch the filesystem. Silently no-ops on anything that doesn't
// look like one of our own uploads (matches the validation already done in
// api/save.php / api/save-sidebar-item.php before $path is trusted at all).
function deleteUploadIfUnused(string $path): void
{
    if ($path === '' || strpos($path, '..') !== false || !preg_match('#^uploads/[\p{L}\p{N}\p{M}_./-]+\.(jpg|jpeg|png|gif|webp)$#iu', $path)) {
        return;
    }
    if (uploadPathInUse($path)) {
        return;
    }
    $filePath = UPLOADS_DIR . substr($path, strlen('uploads/'));
    // Belt-and-suspenders on top of the ".." rejection above: canonicalize
    // and confirm the resolved path still lands inside UPLOADS_DIR before
    // ever calling unlink() — the character class above allows "/" (for
    // uploads/YYYY/MM/ subfolders), so path-traversal segments alone were
    // the only thing standing between this and deleting arbitrary files.
    $realFile = realpath($filePath);
    $realUploadsDir = realpath(UPLOADS_DIR);
    if ($realFile === false || $realUploadsDir === false || strpos($realFile, $realUploadsDir . DIRECTORY_SEPARATOR) !== 0) {
        return;
    }
    @unlink($realFile);
}
