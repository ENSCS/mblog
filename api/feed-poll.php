<?php
// Public read-only endpoint feed.php's JS polls every ~20s. No token — this
// only returns the same public content feed.php itself already shows, same
// trust level as any other public page on the site.
//
// Deliberately returns the *whole* current window (feed_item_limit setting,
// same one feed.php's initial load uses — or ?limit= if the caller passes
// one, see feed-embed.php's fixed 25) pre-rendered as HTML, not just rows
// newer than last_seen_id — a full refresh means edits and deletes show up
// on the next poll exactly like new posts do, with no separate tracking
// needed for "what changed" (see includes/feed.php's
// renderFeedItemHtml()/getFeedItems() comments). The client just swaps
// #feed-list's innerHTML wholesale; last_seen_id only decides which items
// get the "just arrived" fade-in class in the HTML we send back.
header('Content-Type: application/json; charset=utf-8');
require __DIR__ . '/../includes/feed.php';

// feed.php itself is gated behind requireAnyLogin() — without the same check
// here, this poll endpoint would let anyone read the "login-required" feed
// content directly, bypassing that gate entirely.
requireApiAnyLogin();

$lastSeenId = (int) ($_GET['last_seen_id'] ?? 0);
// ?limit= lets a caller (feed-embed.php) poll for a fixed window instead of
// the site-wide feed_item_limit setting feed.php's own poll relies on —
// keeps the auto-refresh consistent with whatever count the page initially
// rendered, no jump in item count on the first poll.
$limit = isset($_GET['limit']) ? (int) $_GET['limit'] : (int) siteSetting('feed_item_limit', 50);
$limit = max(1, $limit);
$items = getFeedItems($limit);

if (!$items) {
    $html = '<p style="color:var(--text-muted);">ยังไม่มีข้อความ</p>';
} else {
    $html = '';
    foreach ($items as $item) {
        $html .= renderFeedItemHtml($item, $lastSeenId);
    }
}

echo json_encode([
    'html' => $html,
    'last_id' => $items ? (int) $items[0]['id'] : $lastSeenId,
], JSON_UNESCAPED_UNICODE);
