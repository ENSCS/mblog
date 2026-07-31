<?php
// Shared backup logic — reused by scripts/backup.php (CLI/cron) and
// backup.php (admin "Backup ตอนนี้" button + list/download). Same pattern as
// includes/feed.php / includes/markdown-import.php: one logic file, multiple
// front doors, so the two never drift apart.
//
// Restore is deliberately manual, not scripted: unzip a backup, import
// db-dump.sql (`mysql dbname < db-dump.sql`, or via phpMyAdmin), then copy
// the zip's uploads/ back into place.

require_once __DIR__ . '/db.php';

// mblog_pageview_log grows fast and is cheap to lose (just resets the
// counters) — no retention/rollup policy decided for it yet (see
// PLANNING.md), so it's excluded to keep backups from ballooning forever.
const BACKUP_EXCLUDED_TABLES = ['mblog_pageview_log'];

// backups/ is fully git-ignored (unlike uploads/, it has no .gitkeep — see
// .gitignore), so a deploy can't ship a committed .htaccess for it. Writing
// the deny-all here, the moment the directory is first needed, is the only
// way the protection reliably exists regardless of how/where this app is
// deployed. Both directives included: `Require all denied` for Apache 2.4+,
// `Deny from all` for 2.2 (harmless if the other module isn't loaded).
function ensureBackupDir(): string
{
    if (!is_dir(BACKUP_DIR)) {
        // 0777, not 0755: this directory gets written to by both the CLI
        // (runs as the shell user) and the admin web page (runs as the web
        // server's own user) — same cross-user-ownership situation uploads/
        // already had to solve, matching its permissions here.
        mkdir(BACKUP_DIR, 0777, true);
    }

    $htaccessPath = BACKUP_DIR . '.htaccess';
    if (!file_exists($htaccessPath)) {
        file_put_contents($htaccessPath, "<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n    Deny from all\n</IfModule>\n");
    }

    return BACKUP_DIR;
}

// Live table list (not a hardcoded array) so new tables from future
// migrations are picked up automatically with no code change here.
function getBackupTables(): array
{
    $tables = db()->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);

    return array_values(array_diff($tables, BACKUP_EXCLUDED_TABLES));
}

// FOREIGN_KEY_CHECKS=0 around the whole dump so per-table order never
// matters on restore, regardless of FK relationships between mblog_* tables.
function buildDatabaseDumpSql(): string
{
    $pdo = db();
    $sql = "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n";

    foreach (getBackupTables() as $table) {
        $createRow = $pdo->query('SHOW CREATE TABLE `' . $table . '`')->fetch();
        $createSql = $createRow['Create Table'];

        $sql .= "DROP TABLE IF EXISTS `{$table}`;\n{$createSql};\n\n";

        $rows = $pdo->query('SELECT * FROM `' . $table . '`')->fetchAll();
        if (!$rows) {
            continue;
        }

        $columns = array_keys($rows[0]);
        $columnList = '`' . implode('`, `', $columns) . '`';

        $valueGroups = [];
        foreach ($rows as $row) {
            $values = array_map(
                fn ($value) => $value === null ? 'NULL' : $pdo->quote((string) $value),
                $row
            );
            $valueGroups[] = '(' . implode(', ', $values) . ')';
        }

        $sql .= "INSERT INTO `{$table}` ({$columnList}) VALUES\n" . implode(",\n", $valueGroups) . ";\n\n";
    }

    $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";

    return $sql;
}

// Zips db-dump.sql (built fresh, never touches disk on its own) together
// with uploads/ — same recursive-iterator approach the old backup.php used
// for its directory copy, just scoped to uploads/ now that articles/ no
// longer exists (content lives in MySQL, covered by the SQL dump instead).
function createBackupArchive(): array
{
    ensureBackupDir();

    $projectRoot = dirname(__DIR__);
    $filename = 'backup-' . date('Ymd-His') . '.zip';
    $zipPath = BACKUP_DIR . $filename;

    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException("Could not create zip file: $zipPath");
    }

    $zip->addFromString('db-dump.sql', buildDatabaseDumpSql());

    $uploadsDir = $projectRoot . '/uploads';
    if (is_dir($uploadsDir)) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($uploadsDir, FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            $localPath = 'uploads/' . substr($file->getPathname(), strlen($uploadsDir) + 1);
            $zip->addFile($file->getPathname(), $localPath);
        }
    }

    $zip->close();

    return [
        'path' => $zipPath,
        'filename' => $filename,
        'size' => filesize($zipPath),
    ];
}

function pruneOldBackups(int $retentionDays = 14): int
{
    $cutoff = time() - ($retentionDays * 86400);
    $deleted = 0;

    foreach (glob(BACKUP_DIR . 'backup-*.zip') as $file) {
        if (filemtime($file) < $cutoff) {
            unlink($file);
            $deleted++;
        }
    }

    return $deleted;
}

// For backup.php's admin list — newest first, so the button's result is
// always the top row.
function listBackupFiles(): array
{
    $files = glob(BACKUP_DIR . 'backup-*.zip') ?: [];

    $items = array_map(fn ($file) => [
        'filename' => basename($file),
        'size' => filesize($file),
        'mtime' => filemtime($file),
        'created_at' => date('Y-m-d H:i:s', filemtime($file)), // for relativeTimeTag()
    ], $files);

    usort($items, fn ($a, $b) => $b['mtime'] <=> $a['mtime']);

    return $items;
}

function formatBackupSize(int $bytes): string
{
    if ($bytes >= 1024 * 1024) {
        return round($bytes / (1024 * 1024), 1) . ' MB';
    }

    return round($bytes / 1024, 1) . ' KB';
}

// Only filenames matching this exact pattern are ever read from disk — no
// user-controlled path fragment reaches the filesystem, so there's no path
// traversal surface here. Shared by streamBackupDownload() and
// deleteBackupFile() so both validate the same way.
function resolveBackupPath(string $filename): ?string
{
    if (!preg_match('/^backup-\d{8}-\d{6}\.zip$/', $filename)) {
        return null;
    }

    $path = realpath(BACKUP_DIR . $filename);
    if ($path === false || dirname($path) !== realpath(BACKUP_DIR)) {
        return null;
    }

    return $path;
}

function streamBackupDownload(string $filename): void
{
    $path = resolveBackupPath($filename);
    if ($path === null) {
        http_response_code(404);
        exit('Backup not found');
    }

    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($path));
    readfile($path);
    exit;
}

function deleteBackupFile(string $filename): bool
{
    $path = resolveBackupPath($filename);

    return $path !== null && unlink($path);
}
