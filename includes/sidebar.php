<?php
// Data-access layer for sidebar items — the only place that knows they live
// in mblog_sidebar_items. Same shape as includes/menu.php.

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/db.php';

// iframe_src is either an absolute http(s) URL (external site) or a path
// relative to this site's root (e.g. "feed-embed.php") — the latter is what
// keeps internal embeds working after a host/folder/domain change, because
// the host is filled in per request instead of being frozen into the DB row.
// Returns null for anything that is neither (blank, javascript:, //evil.com,
// ../ traversal, ...) — also the single validation point for save-sidebar-item.php.
function normalizeIframeSrc(string $src): ?string
{
    $src = trim($src);
    if ($src === '') {
        return null;
    }
    if (filter_var($src, FILTER_VALIDATE_URL) && in_array(parse_url($src, PHP_URL_SCHEME), ['http', 'https'], true)) {
        return $src;
    }
    $path = ltrim($src, '/');
    if ($src[0] === '/' && ($src[1] ?? '') === '/') {
        return null; // protocol-relative //host/... would point off-site
    }
    if (preg_match('#^[A-Za-z0-9_\-./?=&%]+$#', $path) && !str_contains($path, '..') && !str_contains($path, '//')) {
        return $path;
    }
    return null;
}

// Turns a stored iframe_src into the URL the browser should load.
function resolveIframeSrc(string $src): string
{
    $normalized = normalizeIframeSrc($src);
    if ($normalized === null || preg_match('#^https?://#i', $normalized)) {
        return $normalized ?? '';
    }
    require_once __DIR__ . '/articles.php'; // siteBaseUrl()
    return siteBaseUrl() . '/' . $normalized;
}

// Only the ones toggled "on", in display order — what partials/footer.php
// actually renders. Separate from getAllSidebarItems() below the same way
// getPublicTags() is separate from getAllTags(): the admin screen needs to
// see everything, the public-facing render only needs what's live.
function getActiveSidebarItems(): array
{
    return db()->query(
        'SELECT id, title, type, content, image, link_url, iframe_src, iframe_height FROM mblog_sidebar_items WHERE is_active = 1 ORDER BY sort_order'
    )->fetchAll();
}

// Every item regardless of active state — for sidebar-items.php.
function getAllSidebarItems(): array
{
    return db()->query('SELECT * FROM mblog_sidebar_items ORDER BY sort_order')->fetchAll();
}

function getSidebarItemById(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM mblog_sidebar_items WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();

    return $row ?: null;
}

// Auto-append-to-end default when the admin leaves "sort order" blank on a
// new item — same pattern as nextMenuSortOrder()/nextCategorySortOrder().
function nextSidebarSortOrder(): int
{
    $max = db()->query('SELECT MAX(sort_order) FROM mblog_sidebar_items')->fetchColumn();

    return $max !== null ? ((int) $max + 1) : 1;
}

function createSidebarItem(string $title, string $type, ?string $content, string $image, string $linkUrl, ?string $iframeSrc, ?int $iframeHeight, int $isActive, int $sortOrder): int
{
    $now = date('Y-m-d H:i:s');
    $stmt = db()->prepare(
        'INSERT INTO mblog_sidebar_items (title, type, content, image, link_url, iframe_src, iframe_height, is_active, sort_order, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([$title, $type, $content, $image, $linkUrl, $iframeSrc, $iframeHeight, $isActive, $sortOrder, $now, $now]);

    return (int) db()->lastInsertId();
}

// Full content edit, from sidebar-item-editor.php — deliberately doesn't
// touch sort_order, so re-saving an item's text/image never silently
// reshuffles its position (that's sidebar-items.php's reorder form's job,
// see updateSidebarItemOrder() below).
function updateSidebarItem(int $id, string $title, string $type, ?string $content, string $image, string $linkUrl, ?string $iframeSrc, ?int $iframeHeight, int $isActive): void
{
    $stmt = db()->prepare(
        'UPDATE mblog_sidebar_items SET title = ?, type = ?, content = ?, image = ?, link_url = ?, iframe_src = ?, iframe_height = ?, is_active = ?, updated_at = ? WHERE id = ?'
    );
    $stmt->execute([$title, $type, $content, $image, $linkUrl, $iframeSrc, $iframeHeight, $isActive, date('Y-m-d H:i:s'), $id]);
}

// Position/visibility only, from sidebar-items.php's bulk reorder form — the
// counterpart to updateSidebarItem() above, deliberately leaves title/
// content/image/link_url untouched.
function updateSidebarItemOrder(int $id, int $sortOrder, int $isActive): void
{
    $stmt = db()->prepare('UPDATE mblog_sidebar_items SET sort_order = ?, is_active = ? WHERE id = ?');
    $stmt->execute([$sortOrder, $isActive, $id]);
}

function deleteSidebarItem(int $id): void
{
    $stmt = db()->prepare('DELETE FROM mblog_sidebar_items WHERE id = ?');
    $stmt->execute([$id]);
}
