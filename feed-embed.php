<?php
// Bare-bones feed — no site header/nav/footer/sidebar, just the feed list
// itself, auto-refreshing (reuses api/feed-poll.php + assets/feed.js
// unchanged, both already scoped to #feed-list only, nothing else on the
// page). Made for use as a sidebar item's iframe_src (sidebar-item-
// editor.php's "iframe embed" type) — same idea as youtube-embed.php.
require __DIR__ . '/includes/feed.php';
require_once __DIR__ . '/includes/theme-colors.php';

// Fixed at 25 regardless of the feed_item_limit site setting feed.php uses
// (this embed sits in a narrow sidebar iframe — 50 would make it a very
// long scroll) — assets/feed.js reads data-limit below to keep polling
// consistent with this same number instead of falling back to the site-wide
// setting after the first auto-refresh.
const FEED_EMBED_LIMIT = 25;
$items = getFeedItems(FEED_EMBED_LIMIT);
$lastId = $items ? (int) $items[0]['id'] : 0;
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<script>
  // Same "apply saved theme before paint" logic as partials/header.php —
  // this page is same-origin but a *separate* document when loaded in a
  // sidebar <iframe>, so the parent's manually-toggled data-theme attribute
  // never reaches this <html> on its own. Without this, the embed only
  // ever follows the OS's prefers-color-scheme, ignoring the site's own
  // light/dark toggle (localStorage is shared same-origin, the DOM isn't).
  (function () {
    var saved = localStorage.getItem('mblog-theme');
    if (saved === 'light' || saved === 'dark') {
      document.documentElement.setAttribute('data-theme', saved);
    }
  })();

  // Keeps it in sync live, no manual refresh needed: clicking the toggle on
  // the parent page (assets/theme.js) calls localStorage.setItem() there,
  // which fires a "storage" event in every *other* same-origin browsing
  // context — that's exactly this iframe, since the tab that made the
  // change never receives its own storage event. Applies instantly, no
  // reload of this document required.
  window.addEventListener('storage', function (e) {
    if (e.key === 'mblog-theme' && (e.newValue === 'light' || e.newValue === 'dark')) {
      document.documentElement.setAttribute('data-theme', e.newValue);
    }
  });
</script>
<link rel="stylesheet" href="assets/base.css?v=<?= @filemtime(__DIR__ . '/assets/base.css') ?>">
<link rel="stylesheet" href="assets/feed.css?v=<?= @filemtime(__DIR__ . '/assets/feed.css') ?>">
<?= renderThemeColorStyle() ?>
<style>
  body {
    margin: 0;
    /* Smaller than feed.php's default (18px/13px) — this page is meant to sit
       in a narrow sidebar iframe, not a full-width page. */
    --feed-item-font-size: 14px;
    --feed-item-time-font-size: 11px;
  }
  /* Pinned so it stays visible while the feed list scrolls beneath it —
     body has no overflow:hidden here (unlike youtube-embed.php), so normal
     page scroll + position:sticky works as-is. */
  /* --topbar-bg is literally the same color as --page-bg (both
     --color-canvas) — fine on the real site where content cards break up
     the page below it, but in this narrow embed the bar just blends into
     the feed behind it. Using --color-primary (the site's accent color,
     already used for buttons/CTAs) instead makes it an actual bar, not
     just a hairline. */
  .feed-embed-bar {
    position: sticky;
    top: 0;
    z-index: 10;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 8px 12px;
    background: var(--color-primary);
    color: var(--text-on-primary);
    font-weight: 600;
    font-size: 14px;
  }
  .feed-embed-bar a {
    color: var(--text-on-primary);
    text-decoration: underline;
    font-size: 13px;
    font-weight: 600;
  }
  .feed-embed-bar a:hover {
    opacity: 0.8;
  }
  .feed-embed-list-wrap {
    padding: 8px;
    box-sizing: border-box;
  }
</style>
</head>
<body>
  <div class="feed-embed-bar">
    <span>ฟีดข่าว</span>
    <?php /* target="_top" — this page only ever runs inside a sidebar <iframe> (see comment above), so a plain link would load the full site cramped inside that tiny box instead of navigating the real page. */ ?>
    <a href="feed.php" target="_top">ดูทั้งหมด &rarr;</a>
  </div>
  <div class="feed-embed-list-wrap">
    <div id="feed-list" class="feed-list" data-last-id="<?= $lastId ?>" data-limit="<?= FEED_EMBED_LIMIT ?>">
      <?php if (!$items): ?>
        <p style="color:var(--text-muted);">ยังไม่มีข้อความ</p>
      <?php else: ?>
        <?php foreach ($items as $item): ?>
          <?= renderFeedItemHtml($item, PHP_INT_MAX) ?>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
<script src="assets/feed.js?v=<?= @filemtime(__DIR__ . '/assets/feed.js') ?>" defer></script>
</body>
</html>
