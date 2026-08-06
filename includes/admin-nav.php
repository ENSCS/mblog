<?php
require_once __DIR__ . '/articles.php';
require_once __DIR__ . '/menu.php';
require_once __DIR__ . '/sidebar.php';
require_once __DIR__ . '/feed.php';
require_once __DIR__ . '/orphan-files.php';
require_once __DIR__ . '/users.php';

// The topbar account menu (avatar → โปรไฟล์/ออกจากระบบ) used to be built
// here as adminTopbarActions(), passed page-by-page via $topbarActions —
// it's now topbarAccountMenu() in includes/auth.php instead, rendered
// unconditionally by partials/header.php on every page, admin or not.

// Single source of truth for the persistent admin sidebar (partials/admin-
// sidebar.php) — same 3 groups admin.php's card grid already used, kept in
// sync here instead of admin.php computing its own separate counts. 'badge'
// is omitted (not just 0) for entries that never had a count on the
// dashboard cards either, so the sidebar doesn't invent numbers that weren't
// there before. 'capability' is the same string requireCapability() checks
// at the top of that entry's own page — partials/admin-sidebar.php filters
// on it via userCan() so an author/editor never sees a link to a page
// they'd immediately get a 403 on.
function adminNavGroups(): array
{
    return [
        'เนื้อหา' => [
            ['label' => '+ เขียนบทความใหม่', 'href' => 'editor.php', 'capability' => 'edit_articles'],
            ['label' => 'จัดการบทความ', 'href' => 'manage-articles.php', 'badge' => count(getArticles()), 'capability' => 'edit_articles'],
            ['label' => 'ร่าง', 'href' => 'drafts.php', 'badge' => count(getDraftArticles()), 'capability' => 'edit_articles'],
            ['label' => 'จัดการหน้า', 'href' => 'manage-pages.php', 'capability' => 'edit_articles'],
            ['label' => 'จัดการปักหมุด', 'href' => 'sticky-items.php', 'badge' => count(getStickyArticleIds()), 'capability' => 'edit_articles'],
            ['label' => 'นำเข้าจาก Markdown', 'href' => 'import-markdown.php', 'capability' => 'edit_articles'],
            ['label' => 'จัดการฟีดข่าว', 'href' => 'manage-feed.php', 'badge' => countFeedItems(), 'capability' => 'edit_articles'],
        ],
        'สถิติ' => [
            ['label' => 'ดูสถิติเว็บ', 'href' => 'stats.php', 'capability' => 'view_stats'],
        ],
        'ตั้งค่าเว็บ' => [
            ['label' => 'ตั้งค่าเว็บ', 'href' => 'settings.php', 'capability' => 'manage_settings'],
            ['label' => 'จัดการเมนู', 'href' => 'menu.php', 'badge' => count(getAllMenuItems()), 'capability' => 'manage_menu'],
            ['label' => 'จัดการหมวดหมู่', 'href' => 'categories.php', 'badge' => count(getAllCategories()), 'capability' => 'manage_categories'],
            ['label' => 'จัดการ Sidebar', 'href' => 'sidebar-items.php', 'badge' => count(getAllSidebarItems()), 'capability' => 'manage_sidebar'],
            ['label' => 'Backup', 'href' => 'backup.php', 'capability' => 'manage_backup'],
            // scanOrphanUploads() walks uploads/ and runs 2 LIKE-scan queries
            // per file (see includes/uploads.php's uploadPathInUse()) — heavier
            // than every other badge here, which is a single COUNT(*). Fine at
            // this site's upload-folder scale; worth revisiting with a cached
            // count if uploads/ ever grows into the thousands.
            ['label' => 'ไฟล์กำพร้า', 'href' => 'orphan-files.php', 'badge' => count(scanOrphanUploads()), 'capability' => 'manage_orphan_files'],
            ['label' => 'ชุดสีของเว็บ', 'href' => 'color-reference.php', 'capability' => 'manage_theme'],
            ['label' => 'ปรับแต่งชุดสี', 'href' => 'theme-editor.php', 'capability' => 'manage_theme'],
            ['label' => 'จัดการผู้ใช้ทีมงาน', 'href' => 'users.php', 'badge' => count(getAllUsers()), 'capability' => 'manage_users'],
        ],
    ];
}
