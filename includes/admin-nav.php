<?php
require_once __DIR__ . '/articles.php';
require_once __DIR__ . '/menu.php';
require_once __DIR__ . '/sidebar.php';

// Shared "back to admin" link for every admin/management page's
// $topbarActions — one place to change the label/destination (see
// admin.php) instead of the same <a href="admin.php"> string duplicated on
// every page that links here. Any page-specific extra actions (e.g.
// "+ เขียนบทความใหม่") get appended after it.
function adminTopbarActions(array $extraLinks = []): string
{
    return '<a href="admin.php">&larr; จัดการเว็บ</a>' . implode('', $extraLinks);
}

// Single source of truth for the persistent admin sidebar (partials/admin-
// sidebar.php) — same 3 groups admin.php's card grid already used, kept in
// sync here instead of admin.php computing its own separate counts. 'badge'
// is omitted (not just 0) for entries that never had a count on the
// dashboard cards either, so the sidebar doesn't invent numbers that weren't
// there before.
function adminNavGroups(): array
{
    return [
        'เนื้อหา' => [
            ['label' => '+ เขียนบทความใหม่', 'href' => 'editor.php'],
            ['label' => 'จัดการบทความ', 'href' => 'manage-articles.php', 'badge' => count(getArticles())],
            ['label' => 'ร่าง', 'href' => 'drafts.php', 'badge' => count(getDraftArticles())],
            ['label' => 'จัดการหน้า', 'href' => 'manage-pages.php'],
            ['label' => 'นำเข้าจาก Markdown', 'href' => 'import-markdown.php'],
        ],
        'สถิติ' => [
            ['label' => 'ดูสถิติเว็บ', 'href' => 'stats.php'],
        ],
        'ตั้งค่าเว็บ' => [
            ['label' => 'ตั้งค่าเว็บ', 'href' => 'settings.php'],
            ['label' => 'จัดการเมนู', 'href' => 'menu.php', 'badge' => count(getAllMenuItems())],
            ['label' => 'จัดการหมวดหมู่', 'href' => 'categories.php', 'badge' => count(getAllCategories())],
            ['label' => 'จัดการ Sidebar', 'href' => 'sidebar-items.php', 'badge' => count(getAllSidebarItems())],
        ],
    ];
}
