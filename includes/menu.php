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
