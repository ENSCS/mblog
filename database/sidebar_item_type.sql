-- Sidebar item types (topic 15/16 follow-up in PLANNING.md) — adds a second
-- item shape (iframe embed) alongside the existing article-style item.
-- Additive only: every existing row defaults to type='article', so current
-- items render exactly as before with no data migration needed.

ALTER TABLE mblog_sidebar_items
  ADD COLUMN type VARCHAR(20) NOT NULL DEFAULT 'article' AFTER title,
  ADD COLUMN iframe_src VARCHAR(500) NULL AFTER link_url,
  ADD COLUMN iframe_height INT UNSIGNED NULL AFTER iframe_src;
