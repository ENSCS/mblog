<?php
// Data-access layer for general-public accounts (mblog_users: free/paid/
// premium — database/phase3b_readers.sql + database/reader_signup.sql) —
// same data/code split as includes/staff.php for staff (mblog_staff), just
// named *User throughout instead of *Staff, since "user" is what a visitor
// of any of this site's tools is, not just an article reader.
//
// Session/login logic (currentUser(), requireUserLogin()) lives in
// includes/auth.php instead, same split as staff's currentStaff()/
// requireLogin() — this file is just the CRUD side.

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/uploads.php';

const USER_PROFILE_COLUMNS = 'id, email, username, first_name, last_name, phone, line_id, avatar_path, tier, created_at';

// $search (optional) matches across id, name, phone, email — one text box on
// users.php searching everything an admin might have on hand for a given
// account, rather than separate fields per column. CAST(id AS CHAR) LIKE
// lets a partial id search work the same way the text columns do (e.g. "12"
// matches id 12 as well as 120), not just an exact id match.
function getAllUsers(string $search = ''): array
{
    $search = trim($search);
    if ($search === '') {
        return db()->query('SELECT ' . USER_PROFILE_COLUMNS . ' FROM mblog_users ORDER BY created_at DESC')->fetchAll();
    }

    $like = '%' . $search . '%';
    $stmt = db()->prepare(
        'SELECT ' . USER_PROFILE_COLUMNS . ' FROM mblog_users
         WHERE CAST(id AS CHAR) LIKE ?
            OR first_name LIKE ?
            OR last_name LIKE ?
            OR CONCAT(first_name, \' \', last_name) LIKE ?
            OR phone LIKE ?
            OR email LIKE ?
         ORDER BY created_at DESC'
    );
    $stmt->execute([$like, $like, $like, $like, $like, $like]);

    return $stmt->fetchAll();
}

// Paginated + counted version for users.php's table (getAllUsers() above
// stays as-is, unpaginated — it's also used for the admin sidebar's plain
// count badge, includes/admin-nav.php). Same shape/pattern as
// getArticlesForAdmin() in includes/articles.php: COUNT(*) query first, then
// the page itself with LIMIT/OFFSET inlined (not bound as ? — PDO's
// LIMIT/OFFSET binding is unreliable across drivers, and both are already
// int-cast so there's no injection risk).
function getUsersForAdmin(string $search, int $page, int $perPage): array
{
    $search = trim($search);
    $perPage = max(1, $perPage);
    $offset = max(0, ($page - 1) * $perPage);

    if ($search === '') {
        $whereSql = '';
        $params = [];
    } else {
        $like = '%' . $search . '%';
        $whereSql = 'WHERE CAST(id AS CHAR) LIKE ?
            OR first_name LIKE ?
            OR last_name LIKE ?
            OR CONCAT(first_name, \' \', last_name) LIKE ?
            OR phone LIKE ?
            OR email LIKE ?';
        $params = [$like, $like, $like, $like, $like, $like];
    }

    $countStmt = db()->prepare("SELECT COUNT(*) FROM mblog_users $whereSql");
    $countStmt->execute($params);
    $total = (int) $countStmt->fetchColumn();

    $stmt = db()->prepare(
        'SELECT ' . USER_PROFILE_COLUMNS . " FROM mblog_users
         $whereSql
         ORDER BY created_at DESC
         LIMIT $perPage OFFSET $offset"
    );
    $stmt->execute($params);

    return ['items' => $stmt->fetchAll(), 'total' => $total];
}

function getUserById(int $id): ?array
{
    $stmt = db()->prepare('SELECT ' . USER_PROFILE_COLUMNS . ' FROM mblog_users WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();

    return $row ?: null;
}

function userEmailExists(string $email, ?int $excludeId = null): bool
{
    $stmt = db()->prepare('SELECT id FROM mblog_users WHERE email = ? AND id != ?');
    $stmt->execute([$email, $excludeId ?? 0]);

    return (bool) $stmt->fetch();
}

function userUsernameExists(string $username, ?int $excludeId = null): bool
{
    $stmt = db()->prepare('SELECT id FROM mblog_users WHERE username = ? AND id != ?');
    $stmt->execute([$username, $excludeId ?? 0]);

    return (bool) $stmt->fetch();
}

// Always creates at the 'free' tier — self-signup never grants paid/premium,
// only users.php (admin, manage_users capability) can raise it.
function createUser(string $email, string $username, string $password, array $profile): int
{
    $stmt = db()->prepare('INSERT INTO mblog_users (email, username, password_hash, tier, first_name, last_name, phone, line_id, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([
        $email,
        $username,
        password_hash($password, PASSWORD_DEFAULT),
        'free',
        $profile['first_name'],
        $profile['last_name'],
        $profile['phone'],
        $profile['line_id'] ?? null,
        date('Y-m-d H:i:s'),
    ]);

    return (int) db()->lastInsertId();
}

// Self-service edit from my-profile.php — email + profile fields only.
// Deliberately excludes username (not editable by the user, per how this
// was asked for) and tier (admin-only, see updateUserTier() below).
function updateUserProfile(int $id, string $email, array $profile): void
{
    $stmt = db()->prepare('UPDATE mblog_users SET email = ?, first_name = ?, last_name = ?, phone = ?, line_id = ? WHERE id = ?');
    $stmt->execute([
        $email,
        $profile['first_name'] ?? null,
        $profile['last_name'] ?? null,
        $profile['phone'] ?? null,
        $profile['line_id'] ?? null,
        $id,
    ]);
}

function updateUserTier(int $id, string $tier): void
{
    $stmt = db()->prepare('UPDATE mblog_users SET tier = ? WHERE id = ?');
    $stmt->execute([$tier, $id]);
}

// Admin-side reset from user-profile.php — either a typed password or the
// user's own phone number on file (see the "ใช้เบอร์โทรเป็นรหัส" button
// there). No old-password check, unlike a self-service change would need —
// this only ever runs behind requireCapability('manage_users').
function updateUserPassword(int $id, string $password): void
{
    $stmt = db()->prepare('UPDATE mblog_users SET password_hash = ? WHERE id = ?');
    $stmt->execute([password_hash($password, PASSWORD_DEFAULT), $id]);
}

// Mirrors saveStaffAvatar()/removeStaffAvatar() in includes/staff.php
// exactly (same single-current-file-per-id-slot pattern), just pointed at
// uploads/users/ and mblog_users instead of uploads/staff/ and mblog_staff —
// uploadPathInUse() (includes/uploads.php) already checks both tables so
// orphan-files.php won't flag either one's avatar as orphaned.
function saveUserAvatar(int $id, string $fieldName = 'avatar_file'): ?string
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
    $dir = UPLOADS_DIR . 'users/';
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

    $path = 'uploads/users/' . $filename;
    db()->prepare('UPDATE mblog_users SET avatar_path = ? WHERE id = ?')->execute([$path, $id]);

    return $path;
}

function removeUserAvatar(int $id): void
{
    foreach (glob(UPLOADS_DIR . 'users/' . $id . '.*') as $old) {
        unlink($old);
    }
    db()->prepare('UPDATE mblog_users SET avatar_path = NULL WHERE id = ?')->execute([$id]);
}

function deleteUser(int $id): void
{
    foreach (glob(UPLOADS_DIR . 'users/' . $id . '.*') as $old) {
        unlink($old);
    }
    $stmt = db()->prepare('DELETE FROM mblog_users WHERE id = ?');
    $stmt->execute([$id]);
}

// Bulk versions for users.php's checkbox + "การดำเนินการเป็นชุด" bar — same
// prepare-once-execute-per-id loop as bulkTrashArticles() etc. in
// includes/articles.php, not a single IN (...) query, to stay consistent
// with that existing pattern.
function bulkUpdateUserTier(array $ids, string $tier): void
{
    if (!$ids || !in_array($tier, ['free', 'paid', 'premium'], true)) {
        return;
    }
    $stmt = db()->prepare('UPDATE mblog_users SET tier = ? WHERE id = ?');
    foreach ($ids as $id) {
        $stmt->execute([$tier, (int) $id]);
    }
}

function bulkDeleteUsers(array $ids): void
{
    if (!$ids) {
        return;
    }
    $stmt = db()->prepare('DELETE FROM mblog_users WHERE id = ?');
    foreach ($ids as $id) {
        $id = (int) $id;
        foreach (glob(UPLOADS_DIR . 'users/' . $id . '.*') as $old) {
            unlink($old);
        }
        $stmt->execute([$id]);
    }
}
