<?php
require __DIR__ . '/includes/articles.php';
require __DIR__ . '/includes/stats.php';
require __DIR__ . '/includes/admin-nav.php';

$range = $_GET['range'] ?? '7d';
if (!in_array($range, ['today', '7d', '30d', 'all'], true)) {
    $range = '7d';
}
[$from, $to] = statsDateRange($range);

$summary = statsSummary($from, $to);
$dailyRaw = statsPageviewsByDay($from, $to);
$topArticles = statsTopArticles($from, $to, 10);
$deviceBreakdown = statsDeviceBreakdown($from, $to);
$osBreakdown = statsOsBreakdown($from, $to);
$pageTypeBreakdown = statsPageTypeBreakdown($from, $to);
$topReferrers = statsTopReferrers($from, $to, 10);

// Fill in zero-view days so the chart doesn't silently skip them — only for
// bounded ranges (today/7d/30d has a fixed start); 'all' has no fixed start
// so it's shown as-is rather than iterating an unbounded number of days.
$dailyByDate = array_column($dailyRaw, 'count', 'day');
$daily = [];
if ($from !== null) {
    $cursor = new DateTime(substr($from, 0, 10));
    $end = new DateTime(substr($to, 0, 10));
    while ($cursor <= $end) {
        $d = $cursor->format('Y-m-d');
        $daily[] = ['day' => $d, 'count' => (int) ($dailyByDate[$d] ?? 0)];
        $cursor->modify('+1 day');
    }
} else {
    $daily = $dailyRaw;
}

$maxDaily = $daily ? max(array_column($daily, 'count')) : 0;
$maxDaily = $maxDaily ?: 1;
$maxDevice = $deviceBreakdown ? max(array_column($deviceBreakdown, 'count')) : 1;
$maxOs = $osBreakdown ? max(array_column($osBreakdown, 'count')) : 1;
$maxPageType = $pageTypeBreakdown ? max(array_column($pageTypeBreakdown, 'count')) : 1;
$maxReferrer = $topReferrers ? max($topReferrers) : 1;

$pageTypeLabels = [
    'article' => 'บทความ',
    'articles_list' => 'รวมบทความ',
    'category' => 'หมวดหมู่',
    'tag' => 'แท็ก',
    'page' => 'หน้าคงที่',
    'search' => 'ค้นหา',
    'other' => 'อื่นๆ',
];
$deviceLabels = [
    'iphone' => 'iPhone',
    'ipad' => 'iPad',
    'android_phone' => 'Android (มือถือ)',
    'android_tablet' => 'Android (แท็บเล็ต)',
    'desktop' => 'Desktop',
    'other' => 'อื่นๆ',
];
$osLabels = [
    'windows' => 'Windows',
    'macos' => 'macOS',
    'linux' => 'Linux',
    'ios' => 'iOS',
    'android' => 'Android',
    'other' => 'อื่นๆ',
];
$rangeLabels = ['today' => 'วันนี้', '7d' => '7 วัน', '30d' => '30 วัน', 'all' => 'ทั้งหมด'];

$pageTitle = 'สถิติเว็บ';
$topbarActions = adminTopbarActions(['<a href="editor.php">+ เขียนบทความใหม่</a>']);
$showAdminSidebar = true;
$extraHead = '<link rel="stylesheet" href="assets/stats.css?v=' . @filemtime(__DIR__ . '/assets/stats.css') . '">';
$layout = render_header(compact('pageTitle', 'topbarActions', 'showAdminSidebar', 'extraHead'));
?>
  <h1 class="article-title">สถิติเว็บ</h1>

  <div class="stats-range-tabs">
    <?php foreach ($rangeLabels as $key => $label): ?>
      <a href="?range=<?= $key ?>" class="<?= $range === $key ? 'active' : '' ?>"><?= $label ?></a>
    <?php endforeach; ?>
  </div>

  <div class="dashboard-grid">
    <div class="dashboard-card">
      <div class="dashboard-card-count"><?= number_format($summary['pageviews']) ?></div>
      <div class="dashboard-card-label">Pageview</div>
    </div>
    <div class="dashboard-card">
      <div class="dashboard-card-count"><?= number_format($summary['unique_visitors']) ?></div>
      <div class="dashboard-card-label">Unique Visitor</div>
    </div>
    <div class="dashboard-card">
      <div class="dashboard-card-count"><?= number_format($summary['bot_hits']) ?></div>
      <div class="dashboard-card-label">บอทที่เข้ามา</div>
    </div>
  </div>

  <div class="card">
    <h2 class="admin-section-title" style="margin-top:0;">Pageview รายวัน</h2>
    <?php if (!$daily): ?>
      <p style="color:var(--text-muted);">ยังไม่มีข้อมูลในช่วงนี้</p>
    <?php else: ?>
      <div class="stats-bar-chart">
        <?php foreach ($daily as $d): ?>
          <div class="stats-bar-col" data-tooltip="<?= htmlspecialchars($d['day']) ?>: <?= (int) $d['count'] ?>">
            <div class="stats-bar" style="height:<?= $d['count'] > 0 ? max(2, round($d['count'] / $maxDaily * 100)) : 0 ?>%"></div>
            <div class="stats-bar-label"><?= htmlspecialchars(date('j/n', strtotime($d['day']))) ?></div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <div class="card">
    <h2 class="admin-section-title" style="margin-top:0;">บทความยอดนิยม</h2>
    <?php if (!$topArticles): ?>
      <p style="color:var(--text-muted);">ยังไม่มีข้อมูลในช่วงนี้</p>
    <?php else: ?>
      <div class="table-scroll">
        <table class="admin-table">
          <thead><tr><th>บทความ</th><th>ยอดอ่าน</th></tr></thead>
          <tbody>
            <?php foreach ($topArticles as $a): ?>
              <tr>
                <td><a href="article.php?slug=<?= urlencode($a['slug']) ?>"><?= htmlspecialchars($a['title']) ?></a></td>
                <td><?= number_format((int) $a['views']) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

  <div class="stats-columns">
    <div class="card">
      <h2 class="admin-section-title" style="margin-top:0;">อุปกรณ์</h2>
      <?php if (!$deviceBreakdown): ?>
        <p style="color:var(--text-muted);">ยังไม่มีข้อมูล</p>
      <?php else: ?>
        <?php foreach ($deviceBreakdown as $row): ?>
          <div class="stats-hbar-row">
            <div class="stats-hbar-label"><?= htmlspecialchars($deviceLabels[$row['device_type']] ?? $row['device_type']) ?></div>
            <div class="stats-hbar-track"><div class="stats-hbar-fill" style="width:<?= round($row['count'] / $maxDevice * 100) ?>%"></div></div>
            <div class="stats-hbar-count"><?= number_format((int) $row['count']) ?></div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <div class="card">
      <h2 class="admin-section-title" style="margin-top:0;">ระบบปฏิบัติการ</h2>
      <?php if (!$osBreakdown): ?>
        <p style="color:var(--text-muted);">ยังไม่มีข้อมูล</p>
      <?php else: ?>
        <?php foreach ($osBreakdown as $row): ?>
          <div class="stats-hbar-row">
            <div class="stats-hbar-label"><?= htmlspecialchars($osLabels[$row['os']] ?? $row['os']) ?></div>
            <div class="stats-hbar-track"><div class="stats-hbar-fill" style="width:<?= round($row['count'] / $maxOs * 100) ?>%"></div></div>
            <div class="stats-hbar-count"><?= number_format((int) $row['count']) ?></div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

  <div class="stats-columns">
    <div class="card">
      <h2 class="admin-section-title" style="margin-top:0;">ประเภทหน้า</h2>
      <?php if (!$pageTypeBreakdown): ?>
        <p style="color:var(--text-muted);">ยังไม่มีข้อมูล</p>
      <?php else: ?>
        <?php foreach ($pageTypeBreakdown as $row): ?>
          <div class="stats-hbar-row">
            <div class="stats-hbar-label"><?= htmlspecialchars($pageTypeLabels[$row['page_type']] ?? $row['page_type']) ?></div>
            <div class="stats-hbar-track"><div class="stats-hbar-fill" style="width:<?= round($row['count'] / $maxPageType * 100) ?>%"></div></div>
            <div class="stats-hbar-count"><?= number_format((int) $row['count']) ?></div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <div class="card">
      <h2 class="admin-section-title" style="margin-top:0;">ที่มา (Referrer)</h2>
      <?php if (!$topReferrers): ?>
        <p style="color:var(--text-muted);">ยังไม่มีข้อมูล (หรือเข้าตรงทั้งหมด)</p>
      <?php else: ?>
        <?php foreach ($topReferrers as $host => $count): ?>
          <div class="stats-hbar-row">
            <div class="stats-hbar-label"><?= htmlspecialchars($host) ?></div>
            <div class="stats-hbar-track"><div class="stats-hbar-fill" style="width:<?= round($count / $maxReferrer * 100) ?>%"></div></div>
            <div class="stats-hbar-count"><?= number_format($count) ?></div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
<?php render_sidebar($layout); render_footer(); ?>
