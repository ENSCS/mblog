<?php
// Custom theme color override — lets an org replace the default brand
// palette (assets/base.css) without editing CSS. Stored as one JSON blob in
// mblog_settings (key: custom_theme_colors), applied by echoing a small
// <style> block after base.css/layout.css/components.css in
// partials/header.php so the cascade wins on specificity ties (same
// selector, later wins). assets/base.css itself is never touched, so
// "reset to default" is just deleting this one setting.
//
// The override mirrors base.css's own :root / :root[data-theme="dark"]
// structure exactly (light values + named -dark constants + the
// data-theme="dark" reassignment) rather than only touching the resolved
// --color-x — footer/code-block CSS reads --color-canvas-dark/
// --color-muted-dark directly, bypassing the data-theme override, so a
// custom dark canvas/muted has to land on those named constants too or the
// footer would silently keep the default dark colors after a customization.

const CUSTOM_THEME_KEYS = [
    'brand-name', 'primary', 'on-primary', 'canvas', 'card',
    'hairline', 'ink', 'body', 'muted', 'secondary', 'link',
];

// The literal values in assets/base.css — single source of truth for
// "reset to default" and for filling the editor when no override is saved
// yet. Keep in sync with assets/base.css if the defaults there ever change.
function defaultThemeColors(): array
{
    return [
        'light' => [
            'brand-name' => '#a9583e', 'primary' => '#cc785c', 'on-primary' => '#ffffff',
            'canvas' => '#efe9de', 'card' => '#faf9f5', 'hairline' => '#e6dfd8',
            'ink' => '#141413', 'body' => '#3d3d3a', 'muted' => '#6c6a64',
            'secondary' => '#f5f0e8', 'link' => '#0645ad',
        ],
        'dark' => [
            'brand-name' => '#e39073', 'primary' => '#d98a68', 'on-primary' => '#141413',
            'canvas' => '#181715', 'card' => '#252320', 'hairline' => '#34312b',
            'ink' => '#faf9f5', 'body' => '#cfccc4', 'muted' => '#a09d96',
            'secondary' => '#1f1d1b', 'link' => '#58a6ff',
        ],
    ];
}

// Validates/normalizes a raw ['light'=>[...], 'dark'=>[...]] shape from
// either the editor form or an imported JSON file — unknown keys dropped,
// non-hex values dropped. This is the one place untrusted input (a POSTed
// form or an uploaded file) turns into values that get echoed straight into
// a <style> block on every page, so nothing here is trusted past the
// whitelist + regex check regardless of where it came from.
function sanitizeThemeColors($raw): array
{
    $clean = ['light' => [], 'dark' => []];
    if (!is_array($raw)) {
        return $clean;
    }
    foreach (['light', 'dark'] as $mode) {
        if (!isset($raw[$mode]) || !is_array($raw[$mode])) {
            continue;
        }
        foreach (CUSTOM_THEME_KEYS as $key) {
            $val = $raw[$mode][$key] ?? null;
            if (is_string($val) && preg_match('/^#[0-9a-fA-F]{6}$/', $val)) {
                $clean[$mode][$key] = strtolower($val);
            }
        }
    }
    return $clean;
}

// Currently-active colors merged over the defaults — always a complete
// light+dark set (never partial), so callers never need to null-check
// individual keys. A saved override that's missing/invalid on some keys
// (e.g. an old export from before a new key existed) still works: those
// keys just fall back to default instead of breaking the merge.
function getThemeColors(): array
{
    $defaults = defaultThemeColors();
    $saved = json_decode(siteSetting('custom_theme_colors', ''), true);
    $custom = sanitizeThemeColors($saved);
    return [
        'light' => array_merge($defaults['light'], $custom['light']),
        'dark' => array_merge($defaults['dark'], $custom['dark']),
    ];
}

function saveThemeColors(array $raw): void
{
    $clean = sanitizeThemeColors($raw);
    updateSiteSettings(['custom_theme_colors' => json_encode($clean)]);
}

function resetThemeColors(): void
{
    updateSiteSettings(['custom_theme_colors' => '']);
}

// True only when a real override is saved (not just sitting at defaults) —
// lets the editor show accurate state and lets header.php skip echoing a
// no-op <style> block on the common (never-customized) case.
function hasCustomThemeColors(): bool
{
    $saved = json_decode(siteSetting('custom_theme_colors', ''), true);
    $custom = sanitizeThemeColors($saved);
    return !empty($custom['light']) || !empty($custom['dark']);
}

// Echoed in partials/header.php right after assets/components.css.
function renderThemeColorStyle(): string
{
    if (!hasCustomThemeColors()) {
        return '';
    }
    $colors = getThemeColors();
    $root = '';
    $dark = '';
    foreach (CUSTOM_THEME_KEYS as $key) {
        $root .= "--color-{$key}:{$colors['light'][$key]};--color-{$key}-dark:{$colors['dark'][$key]};";
        $dark .= "--color-{$key}:var(--color-{$key}-dark);";
    }
    return "<style>:root{{$root}}:root[data-theme=\"dark\"]{{$dark}}</style>";
}
