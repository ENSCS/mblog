<?php
require __DIR__ . '/includes/articles.php';
require __DIR__ . '/includes/menu.php';
require __DIR__ . '/includes/sidebar.php';
require __DIR__ . '/includes/feed.php';
require __DIR__ . '/includes/admin-nav.php';
requireLogin();

$draftCount = count(getDraftArticles());
$articleCount = count(getArticles());
$pageCount = count(getPages());
$stickyCount = count(getStickyArticleIds());
$feedItemCount = countFeedItems();
// Same capability-gated visibility as includes/admin-nav.php's sidebar links
// below — an 'author' has edit_articles/publish_articles only, so the
// menu/category/sidebar/stats/settings counts (each behind its own
// manage_*/view_stats capability) shouldn't leak on the dashboard even as
// just a number, since the linked page itself 403s for that role anyway.
$categoryCount = staffCan('manage_categories') ? count(getAllCategories()) : null;
$menuCount = staffCan('manage_menu') ? count(getAllMenuItems()) : null;
$sidebarItemCount = staffCan('manage_sidebar') ? count(getAllSidebarItems()) : null;

$pageTitle = 'จัดการเว็บ';
$showAdminSidebar = true;
$layout = render_header(compact('pageTitle', 'showAdminSidebar'));
?>
  <h1 class="article-title">จัดการเว็บ</h1>

  <h2 class="admin-section-title">เนื้อหา</h2>
  <div class="dashboard-grid">
    <a class="dashboard-card" href="editor.php">
      <div class="dashboard-card-label">+ เขียนบทความใหม่</div>
    </a>
    <a class="dashboard-card" href="manage-articles.php">
      <div class="dashboard-card-label">จัดการบทความ</div>
    </a>
    <a class="dashboard-card" href="drafts.php">
      <div class="dashboard-card-count"><?= $draftCount ?></div>
      <div class="dashboard-card-label">ร่าง</div>
    </a>
    <a class="dashboard-card" href="articles.php">
      <div class="dashboard-card-count"><?= $articleCount ?></div>
      <div class="dashboard-card-label">บทความทั้งหมด</div>
    </a>
    <a class="dashboard-card" href="manage-pages.php">
      <div class="dashboard-card-label">จัดการหน้า</div>
    </a>
    <a class="dashboard-card" href="pages.php">
      <div class="dashboard-card-count"><?= $pageCount ?></div>
      <div class="dashboard-card-label">หน้า</div>
    </a>
    <a class="dashboard-card" href="sticky-items.php">
      <div class="dashboard-card-count"><?= $stickyCount ?></div>
      <div class="dashboard-card-label">จัดการปักหมุด</div>
    </a>
    <a class="dashboard-card" href="import-markdown.php">
      <div class="dashboard-card-label">นำเข้าจาก Markdown</div>
    </a>
    <a class="dashboard-card" href="manage-feed.php">
      <div class="dashboard-card-count"><?= $feedItemCount ?></div>
      <div class="dashboard-card-label">จัดการฟีดข่าว</div>
    </a>
  </div>

  <?php if (staffCan('view_stats')): ?>
  <h2 class="admin-section-title">สถิติ</h2>
  <div class="dashboard-grid">
    <a class="dashboard-card" href="stats.php">
      <div class="dashboard-card-label">ดูสถิติเว็บ</div>
    </a>
  </div>
  <?php endif; ?>

  <?php if (staffCan('manage_settings') || staffCan('manage_menu') || staffCan('manage_categories') || staffCan('manage_sidebar') || staffCan('manage_backup') || staffCan('manage_orphan_files') || staffCan('manage_theme')): ?>
  <h2 class="admin-section-title">ตั้งค่าเว็บ</h2>
  <div class="dashboard-grid">
    <?php if (staffCan('manage_settings')): ?>
    <a class="dashboard-card" href="settings.php">
      <div class="dashboard-card-label">ตั้งค่าเว็บ</div>
    </a>
    <?php endif; ?>
    <?php if (staffCan('manage_menu')): ?>
    <a class="dashboard-card" href="menu.php">
      <div class="dashboard-card-count"><?= $menuCount ?></div>
      <div class="dashboard-card-label">จัดการเมนู</div>
    </a>
    <?php endif; ?>
    <?php if (staffCan('manage_categories')): ?>
    <a class="dashboard-card" href="categories.php">
      <div class="dashboard-card-count"><?= $categoryCount ?></div>
      <div class="dashboard-card-label">จัดการหมวดหมู่</div>
    </a>
    <?php endif; ?>
    <?php if (staffCan('manage_sidebar')): ?>
    <a class="dashboard-card" href="sidebar-items.php">
      <div class="dashboard-card-count"><?= $sidebarItemCount ?></div>
      <div class="dashboard-card-label">จัดการ Sidebar</div>
    </a>
    <?php endif; ?>
    <?php if (staffCan('manage_backup')): ?>
    <a class="dashboard-card" href="backup.php">
      <div class="dashboard-card-label">Backup</div>
    </a>
    <?php endif; ?>
    <?php if (staffCan('manage_orphan_files')): ?>
    <a class="dashboard-card" href="orphan-files.php">
      <div class="dashboard-card-label">ไฟล์กำพร้า</div>
    </a>
    <?php endif; ?>
    <?php if (staffCan('manage_theme')): ?>
    <a class="dashboard-card" href="color-reference.php">
      <div class="dashboard-card-label">ชุดสีของเว็บ</div>
    </a>
    <a class="dashboard-card" href="theme-preview.php">
      <div class="dashboard-card-label">พรีวิวองค์ประกอบ</div>
    </a>
    <a class="dashboard-card" href="theme-editor.php">
      <div class="dashboard-card-label">ปรับแต่งชุดสี</div>
    </a>
    <?php endif; ?>
  </div>
  <?php endif; ?>
<?php render_sidebar($layout); render_footer(); ?>
