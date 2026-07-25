<?php
// Interim redirect — index.php doesn't own the article list anymore (see
// articles.php), decoupled on purpose so the homepage can become something
// else later without touching the article-listing code at all. 302 (not
// 301) because this is explicitly a temporary arrangement, not a permanent
// move: index.php is expected to show real homepage content eventually.
header('Location: articles.php', true, 302);
exit;
