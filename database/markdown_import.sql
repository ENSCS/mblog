-- mBlog — Markdown bulk-import dedup tracking
-- Separate from phase6_import.sql's source_url (that one credits the
-- original YouTube video link, still unused since current front matter
-- doesn't carry one) — this tracks the *source .md filename* so the same
-- daily summary file can't accidentally get imported twice, whether it
-- arrives via import-markdown.php (manual upload) or api/import-markdown.php
-- (direct push from the local summarizer). NULL for every article not
-- created through the importer.

ALTER TABLE mblog_articles
    ADD COLUMN source_file VARCHAR(255) NULL AFTER source_url,
    ADD INDEX idx_mblog_articles_source_file (source_file);
