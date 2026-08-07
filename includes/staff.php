<?php
// Data-access layer for staff accounts (mblog_staff: admin/editor/author) —
// the only place that knows they live in this table. staff.php (admin-only
// CRUD screen) calls these instead of querying directly. Session/capability
// logic (currentStaff(), staffCan(), login/logout) lives in includes/auth.php
// instead — this file is just the CRUD side, same split as
// includes/menu.php (data) vs. the login-adjacent bits staying in auth.php.
//
// Named *Staff (not *User) throughout, and mblog_staff is queried
// everywhere here — "User" is reserved for the general-public account layer
// instead (includes/users.php, mblog_users), which is what "user" means
// site-wide now that the site has more than just articles/tools.
//
// Never returns password_hash — every SELECT here is for admin-screen
// display, which has no reason to ever see a hash, only whether one exists.

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/uploads.php';

const STAFF_PROFILE_COLUMNS = 'id, email, username, first_name, last_name, phone, line_id, avatar_path, role, created_at';

// userDisplayName()/avatarInitial()/avatarColorClass() moved to
// includes/auth.php — topbarAccountMenu() there (rendered on every page via
// partials/header.php) needs them and only currentStaff() is guaranteed
// loaded that early, not this file's DB-backed CRUD functions below.

function getAllStaff(): array
{
    return db()->query('SELECT ' . STAFF_PROFILE_COLUMNS . ' FROM mblog_staff ORDER BY created_at')->fetchAll();
}

// setup.php's whole safety gate rests on this being 0 — a lightweight
// COUNT(*) instead of count(getAllStaff()) so that check doesn't need to
// pull every row just to test emptiness.
function countStaff(): int
{
    return (int) db()->query('SELECT COUNT(*) FROM mblog_staff')->fetchColumn();
}

function getStaffById(int $id): ?array
{
    $stmt = db()->prepare('SELECT ' . STAFF_PROFILE_COLUMNS . ' FROM mblog_staff WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();

    return $row ?: null;
}

function staffEmailExists(string $email, ?int $excludeId = null): bool
{
    $stmt = db()->prepare('SELECT id FROM mblog_staff WHERE email = ? AND id != ?');
    $stmt->execute([$email, $excludeId ?? 0]);

    return (bool) $stmt->fetch();
}

function staffUsernameExists(string $username, ?int $excludeId = null): bool
{
    $stmt = db()->prepare('SELECT id FROM mblog_staff WHERE username = ? AND id != ?');
    $stmt->execute([$username, $excludeId ?? 0]);

    return (bool) $stmt->fetch();
}

// Derives a login-safe username from the local part of an email address,
// appending -2, -3, ... on collision — same WP-style uniqueness pattern as
// sanitizeUploadFilename() (api/upload.php). Used wherever a form only
// collects email/password (staff.php's "add new user", setup.php) so
// mblog_staff.username still gets *some* value to satisfy the NOT NULL +
// UNIQUE column — the admin can always pick a nicer one later from
// profile.php.
function generateStaffUsernameFromEmail(string $email): string
{
    $base = strtolower(explode('@', $email)[0] ?? '');
    $base = preg_replace('/[^a-z0-9_.-]/', '', $base);
    $base = trim($base, '_.-');
    if ($base === '') {
        $base = 'user';
    }

    $username = $base;
    $suffix = 2;
    while (staffUsernameExists($username)) {
        $username = $base . '-' . $suffix;
        $suffix++;
    }

    return $username;
}

function createStaff(string $email, string $username, string $password, string $role = 'author', array $profile = []): int
{
    $stmt = db()->prepare('INSERT INTO mblog_staff (email, username, password_hash, role, first_name, last_name, phone, line_id, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([
        $email,
        $username,
        password_hash($password, PASSWORD_DEFAULT),
        $role,
        $profile['first_name'] ?? null,
        $profile['last_name'] ?? null,
        $profile['phone'] ?? null,
        $profile['line_id'] ?? null,
        date('Y-m-d H:i:s'),
    ]);

    return (int) db()->lastInsertId();
}

function updateStaff(int $id, string $email, string $username, string $role, array $profile = []): void
{
    $stmt = db()->prepare('UPDATE mblog_staff SET email = ?, username = ?, role = ?, first_name = ?, last_name = ?, phone = ?, line_id = ? WHERE id = ?');
    $stmt->execute([
        $email,
        $username,
        $role,
        $profile['first_name'] ?? null,
        $profile['last_name'] ?? null,
        $profile['phone'] ?? null,
        $profile['line_id'] ?? null,
        $id,
    ]);
}

// Separate from updateStaff() — the edit form's password field is optional
// (leave blank = keep current password), so this only ever runs when the
// admin actually typed a new one, never as part of the regular save.
function updateStaffPassword(int $id, string $password): void
{
    $stmt = db()->prepare('UPDATE mblog_staff SET password_hash = ? WHERE id = ?');
    $stmt->execute([password_hash($password, PASSWORD_DEFAULT), $id]);
}

// Saves an uploaded avatar to uploads/staff/{id}.{ext}, replacing any
// previous file for this staff member regardless of its old extension —
// same single-current-file-per-slot pattern as settings.php's
// saveSiteAsset() for the site logo/favicon, just keyed by id instead of a
// fixed slot name. Returns the new relative path, or null if this submit
// didn't include a file.
function saveStaffAvatar(int $id, string $fieldName = 'avatar_file'): ?string
{
    if (!isset($_FILES[$fieldName]) || $_FILES[$fieldName]['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($_FILES[$fieldName]['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('อัปโหลดรูปไม่สำเร็จ');
    }

    $file = $_FILES[$fieldName];
    $allowedExt = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExt, true)) {
        throw new RuntimeException('ไฟล์ต้องเป็นสกุล ' . implode('/', $allowedExt) . ' เท่านั้น');
    }
    if ($file['size'] > 2 * 1024 * 1024) {
        throw new RuntimeException('ไฟล์ใหญ่เกินไป (สูงสุด 2MB)');
    }
    if (@getimagesize($file['tmp_name']) === false) {
        throw new RuntimeException('ไฟล์นี้ไม่ใช่รูปภาพที่ถูกต้อง');
    }

    ensureUploadsHtaccess();
    $dir = UPLOADS_DIR . 'staff/';
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        throw new RuntimeException('สร้างโฟลเดอร์อัปโหลดไม่สำเร็จ');
    }
    foreach (glob($dir . $id . '.*') as $old) {
        unlink($old);
    }

    $filename = $id . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], $dir . $filename)) {
        throw new RuntimeException('บันทึกไฟล์ไม่สำเร็จ');
    }

    $path = 'uploads/staff/' . $filename;
    db()->prepare('UPDATE mblog_staff SET avatar_path = ? WHERE id = ?')->execute([$path, $id]);

    return $path;
}

function removeStaffAvatar(int $id): void
{
    foreach (glob(UPLOADS_DIR . 'staff/' . $id . '.*') as $old) {
        unlink($old);
    }
    db()->prepare('UPDATE mblog_staff SET avatar_path = NULL WHERE id = ?')->execute([$id]);
}

function deleteStaff(int $id): void
{
    foreach (glob(UPLOADS_DIR . 'staff/' . $id . '.*') as $old) {
        unlink($old);
    }
    $stmt = db()->prepare('DELETE FROM mblog_staff WHERE id = ?');
    $stmt->execute([$id]);
}
