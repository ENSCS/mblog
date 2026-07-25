<?php
// Data-access layer for site settings — the only place that knows settings
// currently live in config/settings.php. Pages call siteSetting() instead of
// reading the config file directly, so a future admin settings screen only
// means changing the inside of getSettings(), not every page that uses it.

function getSettings(): array
{
    static $settings = null;
    if ($settings === null) {
        $settings = require __DIR__ . '/../config/settings.php';
    }

    return $settings;
}

function siteSetting(string $key, $default = null)
{
    return getSettings()[$key] ?? $default;
}
