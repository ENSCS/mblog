<?php
// Data-access layer for the site menu — the only place that knows menu items
// live in mblog_menu_items. partials/header.php calls getMenuItems() instead
// of reading the table directly, so switching storage again later only means
// changing the inside of this function, not the pages that use it.

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/db.php';

// Returns a tree: each item has 'label', 'href', 'children' (itself an array
// of the same shape) — built from the flat table via parent_id self-
// reference. header.php currently only renders the top level (no dropdown
// UI yet), but the data layer already returns the full tree so that UI work
// later won't need to touch this function again.
function getMenuItems(): array
{
    $rows = db()->query(
        'SELECT id, parent_id, label, href FROM mblog_menu_items ORDER BY parent_id, sort_order'
    )->fetchAll();

    $byParent = [];
    foreach ($rows as $row) {
        $byParent[$row['parent_id'] ?? 0][] = $row;
    }

    $build = function ($parentId) use (&$build, $byParent) {
        $items = [];
        foreach ($byParent[$parentId] ?? [] as $row) {
            $items[] = [
                'label' => $row['label'],
                'href' => $row['href'],
                'children' => $build($row['id']),
            ];
        }

        return $items;
    };

    return $build(0);
}

// Flat rows (with raw ids) for the admin screen — unlike getMenuItems() this
// isn't a tree, since the admin form needs actual ids to edit/delete/reparent
// individual rows, not just labels to render.
function getAllMenuItems(): array
{
    return db()->query(
        'SELECT id, parent_id, label, href, sort_order FROM mblog_menu_items ORDER BY parent_id, sort_order'
    )->fetchAll();
}

// Top-level items only — populates the "parent menu" dropdown in the admin
// form. Only 2 levels are supported (matches what partials/header.php
// actually renders), so a child item is never offered as a parent.
function getTopLevelMenuItems(): array
{
    return db()->query(
        'SELECT id, label FROM mblog_menu_items WHERE parent_id IS NULL ORDER BY sort_order'
    )->fetchAll();
}

function getMenuItemById(int $id): ?array
{
    $stmt = db()->prepare('SELECT id, parent_id, label, href, sort_order FROM mblog_menu_items WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();

    return $row ?: null;
}

// Direct child count — used by the admin screen to warn before deleting a
// parent item, since mblog_menu_items cascades the delete to its children.
function countMenuItemChildren(int $id): int
{
    $stmt = db()->prepare('SELECT COUNT(*) FROM mblog_menu_items WHERE parent_id = ?');
    $stmt->execute([$id]);

    return (int) $stmt->fetchColumn();
}

// Auto-append-to-end default when the admin leaves "sort order" blank —
// scoped by parent so a new top-level item and a new submenu item both land
// after their own siblings, not after every row in the whole table.
function nextMenuSortOrder(?int $parentId): int
{
    $stmt = db()->prepare('SELECT MAX(sort_order) FROM mblog_menu_items WHERE parent_id <=> ?');
    $stmt->execute([$parentId]);
    $max = $stmt->fetchColumn();

    return $max !== null ? ((int) $max + 1) : 1;
}

function createMenuItem(?int $parentId, string $label, string $href, int $sortOrder): void
{
    $stmt = db()->prepare(
        'INSERT INTO mblog_menu_items (parent_id, label, href, sort_order) VALUES (?, ?, ?, ?)'
    );
    $stmt->execute([$parentId, $label, $href, $sortOrder]);
}

function updateMenuItem(int $id, ?int $parentId, string $label, string $href, int $sortOrder): void
{
    $stmt = db()->prepare(
        'UPDATE mblog_menu_items SET parent_id = ?, label = ?, href = ?, sort_order = ? WHERE id = ?'
    );
    $stmt->execute([$parentId, $label, $href, $sortOrder, $id]);
}

function deleteMenuItem(int $id): void
{
    $stmt = db()->prepare('DELETE FROM mblog_menu_items WHERE id = ?');
    $stmt->execute([$id]);
}
