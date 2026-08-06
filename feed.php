<?php
require __DIR__ . '/includes/articles.php';
require __DIR__ . '/includes/feed.php';
require __DIR__ . '/includes/stats.php';

recordPageview('feed');

$items = getFeedItems((int) siteSetting('feed_item_limit', 50));
$lastId = $items ? (int) $items[0]['id'] : 0;

$pageTitle = 'ฟีดข่าว';
$showSidebar = true;
$extraHead = '<link rel="stylesheet" href="assets/feed.css?v=' . @filemtime(__DIR__ . '/assets/feed.css') . '">';
$footerScripts = '<script src="assets/feed.js?v=' . @filemtime(__DIR__ . '/assets/feed.js') . '" defer></script>';
$layout = render_header(compact('pageTitle', 'showSidebar', 'extraHead'));
?>
  <h1 class="article-title">ฟีดข่าว</h1>
  <div id="feed-list" class="feed-list" data-last-id="<?= $lastId ?>">
    <?php if (!$items): ?>
      <p style="color:var(--text-muted);">ยังไม่มีข้อความ</p>
    <?php else: ?>
      <?php foreach ($items as $item): ?>
        <?= renderFeedItemHtml($item, PHP_INT_MAX) ?>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
<?php render_sidebar($layout); render_footer(compact('footerScripts')); ?>
