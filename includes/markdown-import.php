<?php
// Bulk-import engine for the daily analyst-summary Markdown files (see
// PLANNING.md sections 6-7) — shared by import-markdown.php (manual web
// upload) and api/import-markdown.php (direct push from the local
// summarizer script), so both entry points go through exactly one pipeline
// instead of duplicating the conversion/insert logic.
//
// Layering, so a *different* future Markdown source (a different front
// matter shape, different body conventions) only means writing a new
// convert*()/import*() pair — includes/lib/parsedown.php itself is never
// touched:
//   1. includes/lib/parsedown.php — generic MD -> HTML, vendored as-is
//   2. convertAnalystSummaryMarkdown() below — this format's HTML -> the
//      Quill-flavored HTML the rest of the site expects
//   3. parseMarkdownFrontMatter() — this format's --- key: value --- header
//   4. importMarkdownArticle() — the shared orchestration both entry points call
// Parsedown 1.6.0 predates PHP 8.1's implicit-nullable-parameter deprecation
// — it fires at class-definition time (i.e. right here, on this require, not
// when a method later runs), so suppressing it around the text() call
// wouldn't work. Restored immediately after so a real deprecation notice
// anywhere else in the app is unaffected; not fixed in the vendored file itself.
$previousErrorReporting = error_reporting(E_ALL & ~E_DEPRECATED);
require_once __DIR__ . '/lib/parsedown.php';
error_reporting($previousErrorReporting);

require_once __DIR__ . '/articles.php';

const MARKDOWN_IMPORT_CATEGORY_NAME = 'สรุปนักวิเคราะห์';
const MARKDOWN_IMPORT_AI_TAG_NAME = 'สรุปโดย AI';

// Splits the "--- key: value ---" header from the body. Deliberately not a
// real YAML parser (no nesting, no lists, no quoting rules) — the
// summarizer only ever emits flat single-line values, so a full YAML
// dependency would be solving a problem this format doesn't have.
function parseMarkdownFrontMatter(string $raw): array
{
    $raw = ltrim($raw, "\xEF\xBB\xBF"); // strip a UTF-8 BOM if present

    if (!preg_match('/^---\s*\n(.*?)\n---\s*\n?/s', $raw, $m)) {
        return ['meta' => [], 'body' => $raw];
    }

    $meta = [];
    foreach (explode("\n", $m[1]) as $line) {
        if (trim($line) === '' || !str_contains($line, ':')) {
            continue;
        }
        [$key, $value] = explode(':', $line, 2);
        $meta[trim($key)] = trim($value);
    }

    return ['meta' => $meta, 'body' => substr($raw, strlen($m[0]))];
}

// Fallback only — used when front matter has no channel_name (older
// summarizer output). Splits the filename convention it actually uses
// (YYYY-MM-DD_SourceName_Title.md, or ..._SourceName_[video_id]_Title.md —
// explode's limit of 3 rolls the video_id/title tail into one piece either
// way, so both conventions land on the same $parts[1]). Returns null (no
// tag) if a filename doesn't look like that at all (e.g. it was renamed),
// rather than guessing at a wrong source name.
function deriveSourceTag(string $filename): ?string
{
    $base = pathinfo($filename, PATHINFO_FILENAME);
    $parts = explode('_', $base, 3);
    if (count($parts) < 2 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $parts[0])) {
        return null;
    }

    $source = trim($parts[1]);

    return $source !== '' ? $source : null;
}

// Prefers the front matter's own channel_name (present since the
// summarizer started emitting richer metadata) over guessing from the
// filename — it's the authoritative value straight from the pipeline
// config, not a pattern match that breaks the moment a filename is renamed.
function resolveSourceName(array $meta, string $filename): ?string
{
    $channelName = trim($meta['channel_name'] ?? '');

    return $channelName !== '' ? $channelName : deriveSourceTag($filename);
}

// What readers care about is when the video/segment actually aired, not
// when this script happened to run (which would bunch an entire imported
// backlog onto today's date instead — public listings sort by published_at,
// see includes/articles.php's fetchArticles()). Preference order:
//   1. front matter's published (ISO 8601 with explicit UTC offset, e.g.
//      "2026-07-24T10:03:10+00:00", straight from the YouTube RSS feed —
//      the real thing, added to the summarizer after channel_name/youtube_url).
//      strtotime() reads the offset in the string itself regardless of
//      PHP's default timezone, so the date()/H:i:s conversion right after it
//      correctly lands in site-local time (config.php's date_default_timezone_set)
//      the same way every other datetime column here is stored.
//   2. the filename's own YYYY-MM-DD prefix — the fallback for files from
//      before the summarizer added the published field.
//   3. $importTime, for filenames that don't even start with a date.
function derivePublishedDate(array $meta, string $filename, string $importTime): string
{
    $published = trim($meta['published'] ?? '');
    if ($published !== '' && ($timestamp = strtotime($published)) !== false) {
        return date('Y-m-d H:i:s', $timestamp);
    }

    $base = pathinfo($filename, PATHINFO_FILENAME);

    return preg_match('/^(\d{4}-\d{2}-\d{2})/', $base, $m) ? $m[1] . ' 00:00:00' : $importTime;
}

// maxresdefault.jpg (1280x720) looks much better than hqdefault.jpg
// (480x360) when it exists, but YouTube only generates it for videos with a
// high-enough-res source — plenty of videos (live-stream recordings
// especially) 404 on it. Confirmed against a real video that lacks one: the
// 404 is a real HTTP 404 with a tiny placeholder body, not a 200 with a
// small image, so a plain status-code check is reliable here. hqdefault.jpg
// is the safe fallback since YouTube generates it for every video without
// exception (same reasoning as the content-embed fallback in
// includes/articles.php's articleFeaturedImage(), just reached from a
// watch/share URL instead of an <iframe class="ql-video"> src). Setting it
// as the real featured_image (not leaving that column blank) means the
// list-card thumbnail and og:image/twitter:image machinery pick it up
// automatically, no separate code path needed for imported articles.
function youtubeThumbnailUrl(?string $youtubeUrl): ?string
{
    if (!$youtubeUrl || !preg_match('#(?:v=|youtu\.be/|/shorts/|/embed/)([\w-]{11})#i', $youtubeUrl, $m)) {
        return null;
    }

    $videoId = $m[1];
    $maxres = 'https://img.youtube.com/vi/' . $videoId . '/maxresdefault.jpg';

    return remoteImageExists($maxres) ? $maxres : 'https://img.youtube.com/vi/' . $videoId . '/hqdefault.jpg';
}

// Short timeouts on purpose — this runs during an unattended daily import
// (see stock-live-pipeline's README), so one slow/unreachable request must
// not be able to stall the whole batch. A failed/slow check just means
// youtubeThumbnailUrl() falls back to hqdefault.jpg, never a hard failure.
function remoteImageExists(string $url): bool
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_NOBODY => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_TIMEOUT => 5,
        CURLOPT_FOLLOWLOCATION => true,
    ]);
    curl_exec($ch);

    // No curl_close() — a no-op since PHP 8.0 (the handle is freed when $ch
    // goes out of scope regardless), and deprecated outright as of PHP 8.5.
    return !curl_errno($ch) && curl_getinfo($ch, CURLINFO_HTTP_CODE) === 200;
}

// AI-generated markdown sometimes indents a sub-item with a fancy Unicode
// space (em space U+2003, ideographic space U+3000, NBSP, ...) instead of a
// plain ASCII one — confirmed against a real sample file, where a "- STPI:"
// sub-bullet used U+2003 and silently failed to register as a list item at
// all (neither Parsedown's own indent handling nor a plain \s regex treats
// those as whitespace). Normalizing them to a real space first is what lets
// both this file's own list detection below and Parsedown's list blocks work.
function normalizeMarkdownWhitespace(string $body): string
{
    return preg_replace('/[\x{00A0}\x{2000}-\x{200A}\x{202F}\x{205F}\x{3000}]/u', ' ', $body);
}

// Parsedown only re-checks a line's block type (header/list/quote/...) at
// the *start* of a block — once a paragraph is under way, every following
// non-blank line just gets appended to it verbatim (see Parsedown::lines()),
// so a "- sub item" sitting mid-paragraph (this summarizer's bullets are
// joined with trailing-double-space hard breaks, not blank lines) never
// gets recognized as a list at all. Inserting a blank line right before the
// first line of such a run forces Parsedown to close the paragraph and open
// a fresh list block for it instead — consecutive list lines after that
// don't need blank lines between them, list continuation handles that part
// natively.
function ensureListsStartFresh(string $body): string
{
    $lines = explode("\n", $body);
    $result = [];
    $prevWasListItem = false;

    foreach ($lines as $line) {
        $isListItem = (bool) preg_match('/^\s*(?:[-*+]\s+|\d+\.\s+)/', $line);
        $prevIsBlank = empty($result) || trim(end($result)) === '';

        if ($isListItem && !$prevWasListItem && !$prevIsBlank) {
            $result[] = '';
        }

        $result[] = $line;
        $prevWasListItem = $isListItem;
    }

    return implode("\n", $result);
}

// Parsedown emits plain <ul>/<ol><li> — this site's public/editor rendering
// relies on Quill's own dialect instead (quill.snow.css draws bullet/number
// markers from the data-list attribute + a ql-ui marker span, not native
// browser list-style — see partials/article-list.php and assets/layout.css's
// "Shared Quill-rendered content" block), so a plain <li> would render with
// no marker at all. DOMDocument (not regex) handles this so nested lists —
// none in the current sample files, but plausible later — aren't a problem.
function convertListsToQuillFormat(string $html): string
{
    if (!str_contains($html, '<ul') && !str_contains($html, '<ol')) {
        return $html;
    }

    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="utf-8"?><div>' . $html . '</div>', LIBXML_NOERROR | LIBXML_NOWARNING);
    libxml_clear_errors();

    $xpath = new DOMXPath($dom);
    foreach (['ul' => 'bullet', 'ol' => 'ordered'] as $tag => $listType) {
        foreach (iterator_to_array($xpath->query("//$tag")) as $listEl) {
            foreach (iterator_to_array($xpath->query('./li', $listEl)) as $li) {
                $li->setAttribute('data-list', $listType);
                $marker = $dom->createElement('span');
                $marker->setAttribute('class', 'ql-ui');
                $marker->setAttribute('contenteditable', 'false');
                $li->insertBefore($marker, $li->firstChild);
            }
        }
    }

    $container = $dom->getElementsByTagName('div')->item(0);
    $result = '';
    foreach (iterator_to_array($container->childNodes) as $child) {
        $result .= $dom->saveHTML($child);
    }

    return $result;
}

// The one function that's specific to *this* Markdown profile (the daily
// analyst-summary format) — a future differently-shaped source gets its own
// sibling function here instead of branching inside this one, but always
// goes through the same Parsedown + convertListsToQuillFormat() core.
function convertAnalystSummaryMarkdown(string $body): string
{
    $body = normalizeMarkdownWhitespace($body);
    $body = ensureListsStartFresh($body);

    $html = (new Parsedown())->text($body);
    $html = convertListsToQuillFormat($html);

    // Cosmetic: a hard-break line feeding into a list item keeps its
    // trailing space(s) as literal text right up to the next tag.
    return preg_replace('/[ \x{00A0}]+(<br|<\/li>|<\/p>)/u', '$1', $html);
}

// Creates the shared import category on first use — same slug-collision
// handling as uniqueSlug(), just for categories (there's no shared helper
// for that already, and this is the only caller that creates one outside
// categories.php's own admin form).
function findOrCreateCategoryByName(string $name, string $color): int
{
    $id = categoryIdByName($name);
    if ($id !== null) {
        return $id;
    }

    $baseSlug = sanitizeSlug($name);
    $slug = $baseSlug;
    $i = 2;
    while (categorySlugExists($slug, null)) {
        $slug = $baseSlug . '-' . $i;
        $i++;
    }

    createCategory($slug, $name, $color, nextCategorySortOrder());

    return categoryIdByName($name);
}

// Best-effort dedup, checked two ways — neither is a content hash, just
// good enough to stop the same video from getting posted twice:
//   - source_url (the youtube_url from front matter, verbatim): the
//     stronger signal when present, since it identifies the video itself
//     and survives the filename/title being edited later.
//   - source_file (the original .md filename, verbatim): the only signal
//     older summarizer output has, since it predates youtube_url in the
//     front matter.
function importedArticleExists(string $sourceFile, ?string $sourceUrl): bool
{
    if ($sourceUrl !== null) {
        $stmt = db()->prepare('SELECT 1 FROM mblog_articles WHERE source_url = ? LIMIT 1');
        $stmt->execute([$sourceUrl]);
        if ($stmt->fetchColumn()) {
            return true;
        }
    }

    if ($sourceFile === '') {
        return false;
    }

    $stmt = db()->prepare('SELECT 1 FROM mblog_articles WHERE source_file = ? LIMIT 1');
    $stmt->execute([$sourceFile]);

    return (bool) $stmt->fetchColumn();
}

// Shared by import-markdown.php and api/import-markdown.php. Always
// publishes immediately (no draft review step — accepted tradeoff: readers
// understand an AI summary can be wrong, see PLANNING.md's original "draft
// first" caution, superseded by this decision) and always tags "สรุปโดย AI"
// for reader transparency, plus the source channel name (front matter's
// channel_name, or the filename as a fallback for older files).
function importMarkdownArticle(string $rawContent, string $sourceFilename): array
{
    $sourceFilename = basename($sourceFilename);
    $parsed = parseMarkdownFrontMatter($rawContent);
    $sourceUrl = trim($parsed['meta']['youtube_url'] ?? '') ?: null;

    if (importedArticleExists($sourceFilename, $sourceUrl)) {
        return [
            'success' => false,
            'skipped' => true,
            'filename' => $sourceFilename,
            'error' => 'ไฟล์นี้ถูกนำเข้าไปแล้ว',
        ];
    }

    $title = trim($parsed['meta']['title'] ?? '') ?: pathinfo($sourceFilename, PATHINFO_FILENAME);

    if ($title === '') {
        return [
            'success' => false,
            'skipped' => false,
            'filename' => $sourceFilename,
            'error' => 'ไม่พบชื่อเรื่อง (ไม่มี title ใน front matter และชื่อไฟล์ว่าง)',
        ];
    }

    $content = convertAnalystSummaryMarkdown($parsed['body']);
    $categoryId = findOrCreateCategoryByName(MARKDOWN_IMPORT_CATEGORY_NAME, 'blue');

    $tagNames = [MARKDOWN_IMPORT_AI_TAG_NAME];
    if ($sourceTag = resolveSourceName($parsed['meta'], $sourceFilename)) {
        $tagNames[] = $sourceTag;
    }

    $baseSlug = sanitizeSlug($title);
    if ($baseSlug === '') {
        $baseSlug = date('Ymd-His') . '-' . substr(bin2hex(random_bytes(3)), 0, 6);
    }
    $slug = uniqueSlug($baseSlug, null);
    $now = date('Y-m-d H:i:s');
    $publishedAt = derivePublishedDate($parsed['meta'], $sourceFilename, $now);

    $stmt = db()->prepare(
        'INSERT INTO mblog_articles
            (slug, title, content, category_id, featured_image, show_sidebar, status, type, created_at, updated_at, published_at, source_file, source_url)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $featuredImage = youtubeThumbnailUrl($sourceUrl) ?? '';
    $stmt->execute([$slug, $title, $content, $categoryId, $featuredImage, null, 'published', 'post', $now, $now, $publishedAt, $sourceFilename, $sourceUrl]);
    $articleId = (int) db()->lastInsertId();

    syncArticleTags($articleId, $tagNames);

    return [
        'success' => true,
        'skipped' => false,
        'filename' => $sourceFilename,
        'id' => $articleId,
        'slug' => $slug,
        'title' => $title,
    ];
}
