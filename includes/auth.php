<?php
// Phase 3 — session-based staff login + capability checks.
// mblog_staff (admin/editor/author) only — public "ผู้ชมทั่วไป" accounts live in
// the separate mblog_users table (database/phase3b_readers.sql) and are
// intentionally not wired to any login flow yet (reserved for later features:
// comments / password-locked articles — see PLANNING.md หัวข้อ 13/14). Keeping
// the two tables separate means staff privilege can never leak into a reader
// account by accident, and either group can be managed/wiped independently.
//
// Capability-based, not role-based, per the project's own principle (see
// database/phase3_users.sql's own comment) — every check in the codebase
// should call userCan('...')/requireCapability('...'), never compare
// currentUser()['role'] directly, so the access matrix can change in one
// place (ROLE_CAPABILITIES below) without touching any page.

require_once __DIR__ . '/db.php';

// CLI scripts (scripts/backup.php, scripts/create-admin.php) require
// config.php too but have no cookies/session to manage.
if (php_sapi_name() !== 'cli' && session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ]);
    session_start();
}

// '*' = every capability. Everything else is an explicit allow-list — a role
// not listed here (or a capability not in its list) simply can't do that
// action. admin: everything. editor: full content control across every
// author's articles, plus categories (a content-shape decision, not a site-
// infra one). author: content capabilities but scoped to their own articles
// only — enforced by articleOwnerFilter() below, not by this list, since
// "own vs. all" is a query-time filter, not a yes/no capability.
const ROLE_CAPABILITIES = [
    'admin' => ['*'],
    'editor' => ['edit_articles', 'publish_articles', 'edit_others_articles', 'delete_articles', 'manage_categories'],
    'author' => ['edit_articles', 'publish_articles'],
];

// Static cache — one query per request, same pattern as getSettings() in
// includes/settings.php. `false` sentinel means "not looked up yet" so a
// logged-out request (currentUser() === null) doesn't re-query every call.
function currentUser(): ?array
{
    static $user = false;
    if ($user !== false) {
        return $user;
    }
    $userId = $_SESSION['user_id'] ?? null;
    if (!$userId) {
        return $user = null;
    }
    $stmt = db()->prepare('SELECT id, email, username, first_name, last_name, avatar_path, role, created_at FROM mblog_staff WHERE id = ?');
    $stmt->execute([$userId]);
    $row = $stmt->fetch();
    return $user = ($row ?: null);
}

// "ชื่อ นามสกุล" if either is set, else the username — always something more
// human than the raw email, used anywhere a user needs to be displayed
// (topbar account, the staff list, "แก้ไขโดย" bylines later).
function userDisplayName(array $user): string
{
    $name = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));

    return $name !== '' ? $name : $user['username'];
}

// Gmail-style avatar placeholder — the uppercased first letter of
// userDisplayName(), shown when avatar_path is empty.
function avatarInitial(array $user): string
{
    return mb_strtoupper(mb_substr(userDisplayName($user), 0, 1));
}

// Picks one of the 10 --avatar-color-* tokens (assets/base.css) by user id,
// so the same person always lands on the same color — id (not email/username)
// because it's the one thing about an account that never changes, even after
// editing the profile.
function avatarColorClass(int $id): string
{
    return 'avatar-color-' . ($id % 10);
}

// The site's one topbar account menu — avatar (or initial placeholder) that
// hovers open into "โปรไฟล์"/"ออกจากระบบ", rendered unconditionally by
// partials/header.php on *every* page (not just admin ones) so login state
// always has the same visible entry point, site-wide. Empty string when
// logged out — search/theme toggle are the only topbar chrome then. Lives
// here (not includes/users.php) specifically so it's available this early:
// config.php requires this file on every single page, before any page-
// specific includes, while includes/users.php's CRUD layer isn't loaded
// outside admin pages that actually need it.
function topbarAccountMenu(): string
{
    $user = currentUser();
    if (!$user) {
        // Same redirect-back pattern as requireLogin() — lands back on
        // whatever page this button was clicked from instead of always
        // dumping the visitor onto admin.php after they log in.
        $redirect = $_SERVER['REQUEST_URI'] ?? '';
        return '<a href="login.php?redirect=' . urlencode($redirect) . '" class="topbar-login-btn">เข้าสู่ระบบ</a>';
    }

    $avatar = $user['avatar_path']
        ? '<img src="' . htmlspecialchars($user['avatar_path']) . '" alt="" class="avatar-thumb avatar-thumb-md">'
        : '<span class="avatar-thumb avatar-thumb-md avatar-thumb-placeholder ' . avatarColorClass((int) $user['id']) . '">' . htmlspecialchars(avatarInitial($user)) . '</span>';

    // Capability-gated like every other check in the codebase (see
    // ROLE_CAPABILITIES above) rather than assuming every logged-in staff
    // member can write — all three roles happen to have edit_articles today,
    // but this stays correct if that ever changes.
    $writeLink = userCan('edit_articles') ? '<a href="editor.php">เขียนบทความ</a>' : '';
    // 'manage_settings' is already an admin-only capability (settings.php
    // uses the same check) — reused here rather than hardcoding
    // $user['role'] === 'admin', per the project's own capability-based-not-
    // role-based rule (see ROLE_CAPABILITIES above).
    $manageLink = userCan('manage_settings') ? '<a href="admin.php">จัดการเว็บ</a>' : '';

    return '<div class="topbar-account-menu">'
        . '<a href="profile.php" class="topbar-account-toggle">' . $avatar . '</a>'
        . '<div class="topbar-account-dropdown">'
        . $writeLink
        . $manageLink
        . '<a href="profile.php">โปรไฟล์</a>'
        . '<a href="logout.php">ออกจากระบบ</a>'
        . '</div>'
        . '</div>';
}

function userCan(string $capability): bool
{
    $user = currentUser();
    if (!$user) {
        return false;
    }
    $caps = ROLE_CAPABILITIES[$user['role']] ?? [];
    return in_array('*', $caps, true) || in_array($capability, $caps, true);
}

// 'author' role only ever sees/manages their own articles — everyone else
// (admin/editor) sees everything, so this returns null (no filter) for them.
// manage-articles.php/manage-pages.php pass this straight into
// getArticlesForAdmin()'s author_id filter.
function articleOwnerFilter(): ?int
{
    $user = currentUser();
    if (!$user || $user['role'] !== 'author') {
        return null;
    }
    return (int) $user['id'];
}

// --- Page (HTML) gates — redirect to login.php / render the shared error page ---

function requireLogin(): void
{
    if (currentUser() !== null) {
        return;
    }
    $redirect = $_SERVER['REQUEST_URI'] ?? 'admin.php';
    header('Location: login.php?redirect=' . urlencode($redirect));
    exit;
}

function requireCapability(string $capability): void
{
    requireLogin();
    if (!userCan($capability)) {
        renderErrorPage(403, 'ไม่มีสิทธิ์เข้าถึงหน้านี้');
    }
}

// --- API (JSON) gates — api/*.php calls these instead; a redirect/HTML error
// page makes no sense to a fetch() caller, so these reply 401/403 JSON. ---

function requireApiLogin(): void
{
    if (currentUser() !== null) {
        return;
    }
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'not logged in']);
    exit;
}

function requireApiCapability(string $capability): void
{
    requireApiLogin();
    if (!userCan($capability)) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'insufficient permission']);
        exit;
    }
}

// --- CSRF — one token per session (not per-form), same shared-secret,
// constant-time-compare discipline as the import token check in
// api/import-markdown.php (hash_equals(), never ==). ---

function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrfField(): string
{
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrfToken()) . '">';
}

// Call at the top of every POST handler (page forms and api/*.php alike).
// $token defaults to $_POST but callers reading a raw JSON body (api/save.php)
// pass their own decoded value in instead. $json picks the reply shape.
function verifyCsrf(?string $token = null, bool $json = false): void
{
    $token = $token ?? ($_POST['csrf_token'] ?? '');
    $expected = $_SESSION['csrf_token'] ?? '';
    if ($expected !== '' && hash_equals($expected, $token)) {
        return;
    }
    if ($json) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'invalid csrf token']);
        exit;
    }
    renderErrorPage(403, 'คำขอไม่ถูกต้อง (CSRF token ไม่ตรง) — กรุณาโหลดหน้าใหม่แล้วลองอีกครั้ง');
}
