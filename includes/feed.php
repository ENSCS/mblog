<?php
// Data-access layer for the short news feed (mblog_feed_items) — see
// database/feed_items.sql for why it's a separate table from articles.
// Same shape as includes/sidebar.php.

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/articles.php'; // relativeTimeTag(), used by renderFeedItemHtml()

// Newest first, for feed.php's initial page load and api/feed-poll.php's
// refresh — both show the same top-50 window, no separate "just the new
// ones" query. Simpler than tracking new/edited separately, and it means an
// edit (or a delete) shows up on the next poll exactly the same way a new
// post does, with no extra code for that case.
function getFeedItems(int $limit = 50): array
{
    $limit = max(1, $limit);
    $stmt = db()->prepare('SELECT id, content, created_at FROM mblog_feed_items ORDER BY id DESC LIMIT ' . $limit);
    $stmt->execute();

    return $stmt->fetchAll();
}

// Single render used by both feed.php's initial page load and
// api/feed-poll.php's refresh, so the two never drift apart (previously
// feed.php rendered server-side while assets/feed.js rebuilt the same
// markup client-side by hand — two copies of the same escaping/formatting
// logic to keep in sync). $lastSeenId marks anything newer for the
// fade-in highlight (assets/feed.js passes the id it already knows about);
// feed.php's own initial render passes PHP_INT_MAX so nothing on first
// load is ever treated as "new".
function renderFeedItemHtml(array $item, int $lastSeenId = 0): string
{
    $isNew = (int) $item['id'] > $lastSeenId;
    $class = 'feed-item' . ($isNew ? ' feed-item-new' : '');

    // ไม่ใช้ nl2br() — .feed-item-content เป็น white-space: pre-line (assets/feed.css) อยู่แล้ว
    // ซึ่ง render \n เป็นบรรทัดใหม่ให้เองจาก text ตรงๆ ใส่ nl2br() ซ้อนจะได้ทั้ง <br> และ \n ที่ยังไม่ถูก
    // strip ออก กลายเป็นเว้นบรรทัดสองเท่าทุกจุดขึ้นบรรทัดใหม่
    return '<div class="' . $class . '" data-id="' . (int) $item['id'] . '">'
        . '<div class="feed-item-content">' . htmlspecialchars($item['content']) . '</div>'
        . '<div class="feed-item-time">' . relativeTimeTag($item['created_at']) . '</div>'
        . '</div>';
}

// True total, not capped by getFeedItems()'s LIMIT — for admin.php's
// dashboard card count.
function countFeedItems(): int
{
    return (int) db()->query('SELECT COUNT(*) FROM mblog_feed_items')->fetchColumn();
}

function createFeedItem(string $content): int
{
    $stmt = db()->prepare('INSERT INTO mblog_feed_items (content, created_at) VALUES (?, ?)');
    $stmt->execute([$content, date('Y-m-d H:i:s')]);

    return (int) db()->lastInsertId();
}

// Used by manage-feed.php's inline edit form (?edit_id=).
function getFeedItemById(int $id): ?array
{
    $stmt = db()->prepare('SELECT id, content, created_at FROM mblog_feed_items WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();

    return $row ?: null;
}

// created_at deliberately untouched — editing a typo shouldn't bump an item
// back to the top of the feed as if it were freshly posted.
function updateFeedItem(int $id, string $content): void
{
    $stmt = db()->prepare('UPDATE mblog_feed_items SET content = ? WHERE id = ?');
    $stmt->execute([$content, $id]);
}

function deleteFeedItem(int $id): void
{
    $stmt = db()->prepare('DELETE FROM mblog_feed_items WHERE id = ?');
    $stmt->execute([$id]);
}
