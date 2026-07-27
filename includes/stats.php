<?php
// Data-access layer for pageview stats (see PLANNING.md Phase 9 and
// database/phase9_stats.sql) — article.php calls recordPageview() after a
// successful article lookup, this file is the only place that knows how
// device/os/bot are derived and how they get written to mblog_pageview_log
// + mblog_article_stats.

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/db.php';

// iPad on iPadOS 13+ reports itself as "Macintosh" by default (Apple's own
// choice, to get desktop sites instead of mobile ones) — so this only ever
// matches the minority of iPads with "Request Mobile Website" turned on.
// Everything else lands in desktop/macos instead. Accepted limitation (see
// database/phase9_stats.sql) — not fixed with a client-side JS beacon
// because the ROI didn't justify the added complexity.
function parseDeviceType(string $userAgent): string
{
    if (trim($userAgent) === '') {
        return 'other';
    }
    if (stripos($userAgent, 'iPhone') !== false) {
        return 'iphone';
    }
    if (stripos($userAgent, 'iPad') !== false) {
        return 'ipad';
    }
    // Android UAs always carry "Mobile" for phones and omit it for tablets —
    // Google's own convention, reliable across real devices.
    if (stripos($userAgent, 'Android') !== false) {
        return stripos($userAgent, 'Mobile') !== false ? 'android_phone' : 'android_tablet';
    }
    if (preg_match('/Windows NT|Macintosh|Mac OS X|Linux/i', $userAgent)) {
        return 'desktop';
    }

    return 'other';
}

// Android UAs also contain the literal word "Linux" (the kernel it's built
// on) — must be checked before the generic Linux fallback below, or every
// Android phone would get miscounted as Linux desktop.
function parseOs(string $userAgent): string
{
    if (trim($userAgent) === '') {
        return 'other';
    }
    if (stripos($userAgent, 'iPhone') !== false || stripos($userAgent, 'iPad') !== false) {
        return 'ios';
    }
    if (stripos($userAgent, 'Android') !== false) {
        return 'android';
    }
    if (stripos($userAgent, 'Windows NT') !== false) {
        return 'windows';
    }
    if (stripos($userAgent, 'Macintosh') !== false || stripos($userAgent, 'Mac OS X') !== false) {
        return 'macos';
    }
    if (stripos($userAgent, 'Linux') !== false) {
        return 'linux';
    }

    return 'other';
}

// Best-effort pattern match against known bots/crawlers/scrapers, plus a
// blank user_agent (real browsers always send one). Not exhaustive — new
// bots show up constantly — but good enough to keep the obvious ones out of
// view_count and the unique-visitor numbers.
function isBotUserAgent(string $userAgent): bool
{
    if (trim($userAgent) === '') {
        return true;
    }

    return (bool) preg_match(
        '/bot|spider|crawl|slurp|facebookexternalhit|whatsapp|telegrambot|python-requests|curl|wget|scrapy|ahrefsbot|semrushbot|mj12bot|petalbot|bingpreview|pingdom|uptimerobot/i',
        $userAgent
    );
}

// HMAC of the IP salted with a per-install secret (STATS_HASH_SECRET, see
// config.php) plus today's date — so the same visitor gets a consistent hash
// within one day (lets us count "unique visitors today") but a different one
// tomorrow (can't be tracked across days). Raw IP is never stored anywhere.
function computeVisitorHash(string $ip): string
{
    return hash_hmac('sha256', $ip, STATS_HASH_SECRET . date('Y-m-d'));
}

// Called once per public page view, from every page that wants to be
// counted — article.php passes 'article' + the article's id (the only case
// that also bumps mblog_article_stats); every other page (articles.php,
// category.php, tag.php, page.php, search.php, and whatever gets built
// later) just passes its own $pageType string, no schema change needed to
// add a new one. $articleId stays null for all of those — reusing it for a
// category/tag id would violate the FK (it points at mblog_articles only).
// page_path is captured here automatically from the request, not passed in,
// so callers don't need to know/repeat their own URL.
//
// Wrapped in try/catch on purpose: this is a side effect, not the reason the
// visitor is on the page. db() has PDO::ERRMODE_EXCEPTION set (includes/db.php),
// and an uncaught exception here would hit set_exception_handler()
// (error-handling.php) and replace the whole response with a 500 page — a
// real article that loaded fine must never fail to render just because the
// stats write hiccuped (lock timeout, disk full, ...). Logged the same way
// error-handling.php's own handlers already log, just without taking the
// page down.
function recordPageview(string $pageType, ?int $articleId = null): void
{
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $referrer = $_SERVER['HTTP_REFERER'] ?? null;
    $pagePath = $_SERVER['REQUEST_URI'] ?? null;
    $isBot = isBotUserAgent($userAgent);
    $now = date('Y-m-d H:i:s');

    try {
        $stmt = db()->prepare(
            'INSERT INTO mblog_pageview_log
                (article_id, page_type, page_path, visitor_hash, user_agent, device_type, os, is_bot, referrer, viewed_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $articleId,
            $pageType,
            $pagePath,
            computeVisitorHash($ip),
            $userAgent !== '' ? $userAgent : null,
            parseDeviceType($userAgent),
            parseOs($userAgent),
            $isBot ? 1 : 0,
            $referrer,
            $now,
        ]);

        if ($isBot || $articleId === null) {
            return;
        }

        $upsert = db()->prepare(
            'INSERT INTO mblog_article_stats (article_id, view_count, updated_at) VALUES (?, 1, ?)
             ON DUPLICATE KEY UPDATE view_count = view_count + 1, updated_at = VALUES(updated_at)'
        );
        $upsert->execute([$articleId, $now]);
    } catch (Throwable $e) {
        error_log('recordPageview failed: ' . $e->getMessage());
    }
}

// Used by article.php to show the count on the page — 0 for an article with
// no rows yet in mblog_article_stats (not every article has been viewed
// since Phase 9 shipped), and also 0 (rather than taking the page down) if
// the read itself fails — same reasoning as recordPageview() above.
function articleViewCount(int $articleId): int
{
    try {
        $stmt = db()->prepare('SELECT view_count FROM mblog_article_stats WHERE article_id = ?');
        $stmt->execute([$articleId]);

        return (int) ($stmt->fetchColumn() ?: 0);
    } catch (Throwable $e) {
        error_log('articleViewCount failed: ' . $e->getMessage());

        return 0;
    }
}

// --- stats.php dashboard queries below ---
// All read straight from mblog_pageview_log (no rollup table — deferred on
// purpose, see PLANNING.md, data volume is still small enough that querying
// raw rows is fine). Every query excludes is_bot=1 except statsSummary()
// (which reports the bot count as its own number) — same "log it, don't
// count it" split as recordPageview() already does for view_count.

// One of 'today' | '7d' | '30d' | 'all' — anything else falls back to 7d.
// Returns [$from, $to]; $from is null for 'all' (no lower bound). '7d'/'30d'
// start at -6/-29 days (not -7/-30) so today counts as one of the N days —
// otherwise "7 days" would actually span 8 calendar days including today.
function statsDateRange(string $range): array
{
    $to = date('Y-m-d H:i:s');

    switch ($range) {
        case 'today':
            return [date('Y-m-d 00:00:00'), $to];
        case '30d':
            return [date('Y-m-d 00:00:00', strtotime('-29 days')), $to];
        case 'all':
            return [null, $to];
        case '7d':
        default:
            return [date('Y-m-d 00:00:00', strtotime('-6 days')), $to];
    }
}

// Shared by every query below so the null-$from ('all') case only has to be
// handled once instead of in each function separately.
function statsWhereClause(?string $from, string $to): array
{
    if ($from === null) {
        return ['viewed_at <= ?', [$to]];
    }

    return ['viewed_at BETWEEN ? AND ?', [$from, $to]];
}

function statsSummary(?string $from, string $to): array
{
    [$where, $params] = statsWhereClause($from, $to);
    $stmt = db()->prepare(
        "SELECT
            SUM(CASE WHEN is_bot = 0 THEN 1 ELSE 0 END) AS pageviews,
            SUM(CASE WHEN is_bot = 1 THEN 1 ELSE 0 END) AS bot_hits,
            COUNT(DISTINCT CASE WHEN is_bot = 0 THEN visitor_hash END) AS unique_visitors
         FROM mblog_pageview_log WHERE $where"
    );
    $stmt->execute($params);
    $row = $stmt->fetch() ?: [];

    return [
        'pageviews' => (int) ($row['pageviews'] ?? 0),
        'bot_hits' => (int) ($row['bot_hits'] ?? 0),
        'unique_visitors' => (int) ($row['unique_visitors'] ?? 0),
    ];
}

// One row per calendar day that has at least one (non-bot) view — days with
// zero views simply don't appear, callers fill the gaps if they need an
// unbroken axis (see statsRenderDailyChart() in stats.php).
function statsPageviewsByDay(?string $from, string $to): array
{
    [$where, $params] = statsWhereClause($from, $to);
    $stmt = db()->prepare(
        "SELECT DATE(viewed_at) AS day, COUNT(*) AS count
         FROM mblog_pageview_log WHERE $where AND is_bot = 0
         GROUP BY DATE(viewed_at) ORDER BY day ASC"
    );
    $stmt->execute($params);

    return $stmt->fetchAll();
}

// Ranked by views within the selected range — deliberately not the same
// number as mblog_article_stats.view_count (that's an all-time total; this
// answers "popular recently", which is a different question).
function statsTopArticles(?string $from, string $to, int $limit = 10): array
{
    [$where, $params] = statsWhereClause($from, $to);
    $limit = max(1, $limit);
    $stmt = db()->prepare(
        "SELECT l.article_id, a.title, a.slug, COUNT(*) AS views
         FROM mblog_pageview_log l
         JOIN mblog_articles a ON a.id = l.article_id
         WHERE $where AND l.is_bot = 0 AND l.page_type = 'article'
         GROUP BY l.article_id, a.title, a.slug
         ORDER BY views DESC LIMIT $limit"
    );
    $stmt->execute($params);

    return $stmt->fetchAll();
}

function statsDeviceBreakdown(?string $from, string $to): array
{
    [$where, $params] = statsWhereClause($from, $to);
    $stmt = db()->prepare(
        "SELECT device_type, COUNT(*) AS count
         FROM mblog_pageview_log WHERE $where AND is_bot = 0
         GROUP BY device_type ORDER BY count DESC"
    );
    $stmt->execute($params);

    return $stmt->fetchAll();
}

function statsOsBreakdown(?string $from, string $to): array
{
    [$where, $params] = statsWhereClause($from, $to);
    $stmt = db()->prepare(
        "SELECT os, COUNT(*) AS count
         FROM mblog_pageview_log WHERE $where AND is_bot = 0
         GROUP BY os ORDER BY count DESC"
    );
    $stmt->execute($params);

    return $stmt->fetchAll();
}

function statsPageTypeBreakdown(?string $from, string $to): array
{
    [$where, $params] = statsWhereClause($from, $to);
    $stmt = db()->prepare(
        "SELECT page_type, COUNT(*) AS count
         FROM mblog_pageview_log WHERE $where AND is_bot = 0
         GROUP BY page_type ORDER BY count DESC"
    );
    $stmt->execute($params);

    return $stmt->fetchAll();
}

// Grouped by host, not full URL (a raw referrer URL is almost never
// identical twice, so grouping on it would just list individual hits, not a
// meaningful "source"). Same-site referrers (readers clicking from one of
// our own pages to another) are excluded — that's internal navigation, not
// a traffic source, same distinction GA/Plausible make.
function statsTopReferrers(?string $from, string $to, int $limit = 10): array
{
    [$where, $params] = statsWhereClause($from, $to);
    $stmt = db()->prepare(
        "SELECT referrer FROM mblog_pageview_log
         WHERE $where AND is_bot = 0 AND referrer IS NOT NULL AND referrer != ''"
    );
    $stmt->execute($params);

    $selfHost = $_SERVER['HTTP_HOST'] ?? '';
    $counts = [];
    while (($referrer = $stmt->fetchColumn()) !== false) {
        $host = parse_url($referrer, PHP_URL_HOST);
        if (!$host || $host === $selfHost) {
            continue;
        }
        $counts[$host] = ($counts[$host] ?? 0) + 1;
    }
    arsort($counts);

    return array_slice($counts, 0, max(1, $limit), true);
}
