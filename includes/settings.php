<?php
// Data-access layer for site settings — the only place that knows settings
// live in the mblog_settings table. Pages call siteSetting() instead of
// querying it directly, same as every other data layer in this project.
require_once __DIR__ . '/db.php';

// $overrides merges straight into the cache without a re-query — how
// updateSiteSettings() below keeps this in-request cache in sync with what
// it just wrote, since a static local var can't be reached from another
// function any other way. Every other caller (siteSetting()) passes nothing.
function getSettings(?array $overrides = null): array
{
    static $settings = null;
    if ($settings === null) {
        $rows = db()->query('SELECT setting_key, value FROM mblog_settings')->fetchAll();
        $settings = array_column($rows, 'value', 'setting_key');
    }
    if ($overrides !== null) {
        $settings = array_merge($settings, $overrides);
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
    // Without this, a read via siteSetting() later in the same request
    // (e.g. getStickyArticleIds() right after setStickyArticles() writes
    // here) would see the pre-write value — every existing caller of this
    // function happens to redirect+exit right after, which is what hid this
    // until a same-request read-after-write case actually needed it.
    getSettings($values);
}
