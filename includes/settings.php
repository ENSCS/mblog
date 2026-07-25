<?php
// Data-access layer for site settings — the only place that knows settings
// live in the mblog_settings table. Pages call siteSetting() instead of
// querying it directly, same as every other data layer in this project.
require_once __DIR__ . '/db.php';

function getSettings(): array
{
    static $settings = null;
    if ($settings === null) {
        $rows = db()->query('SELECT setting_key, value FROM mblog_settings')->fetchAll();
        $settings = array_column($rows, 'value', 'setting_key');
    }

    return $settings;
}

function siteSetting(string $key, $default = null)
{
    return getSettings()[$key] ?? $default;
}

// Used by settings.php (admin form) to save all fields at once. Upserts so a
// key missing from the table for some reason still gets created rather than
// silently failing to save.
function updateSiteSettings(array $values): void
{
    $stmt = db()->prepare(
        'INSERT INTO mblog_settings (setting_key, value) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE value = VALUES(value)'
    );
    foreach ($values as $key => $value) {
        $stmt->execute([$key, $value]);
    }
}
