<?php
// Shared card+pagination renderer for articles.php, pages.php, category.php,
// tag.php, and drafts.php — one markup template so a design tweak lands on
// all five at once. Pairs with getArticleList() in includes/articles.php on
// the query side. Expected variables from the including page:
//   $articles          (required) rows from getArticleList()
//   $emptyMessage      (required) raw HTML shown when $articles is empty
//   $showCategoryBadge show the category/"หน้า" badge next to the title
//   $showStatusBadge   show the "ร่าง" badge next to the title
//   $linkToView        true: title/"อ่าน" link to article.php/page.php;
//                       false: title links to editor.php, no "อ่าน" (drafts)
//   $page, $totalPages, $pageUrl  pagination — $pageUrl is fn(int $p): string
$showCategoryBadge = $showCategoryBadge ?? false;
$showStatusBadge = $showStatusBadge ?? false;
$linkToView = $linkToView ?? true;
$page = $page ?? null;
$totalPages = $totalPages ?? null;
$pageUrl = $pageUrl ?? null;
?>
<?php if (empty($articles)): ?>
  <div class="empty-state"><?= $emptyMessage ?></div>
<?php else: ?>
  <?php foreach ($articles as $a): ?>
    <div class="card article-list-item">
      <?php $titleHref = $linkToView ? articleViewUrl($a) : 'editor.php?slug=' . urlencode($a['slug']); ?>
      <h2><a href="<?= htmlspecialchars($titleHref) ?>"><?= htmlspecialchars($a['title']) ?></a>
        <?php if ($showStatusBadge): ?>
          <span class="status-badge status-draft">ร่าง</span>
        <?php endif; ?>
        <?php if ($showCategoryBadge): ?>
          <?php if (($a['type'] ?? 'post') === 'page'): ?>
            <span class="category-tag">หน้า</span>
          <?php else: ?>
            <?php $categoryName = articleCategory($a); $categorySlug = articleCategorySlug($a); $categoryColor = articleCategoryColor($a); ?>
            <?php if ($categoryName !== null): ?>
              <?php if ($linkToView && $categorySlug): ?>
                <a class="category-tag category-tag-<?= htmlspecialchars($categoryColor) ?>" href="category.php?slug=<?= urlencode($categorySlug) ?>"><?= htmlspecialchars($categoryName) ?></a>
              <?php else: ?>
                <span class="category-tag category-tag-<?= htmlspecialchars($categoryColor) ?>"><?= htmlspecialchars($categoryName) ?></span>
              <?php endif; ?>
            <?php endif; ?>
          <?php endif; ?>
        <?php endif; ?>
      </h2>
      <div class="meta"><?= relativeTimeTag($a['published_at'] ?? $a['updated_at']) ?></div>
      <div class="row-actions">
        <?php if ($linkToView): ?>
          <a href="<?= htmlspecialchars(articleViewUrl($a)) ?>">อ่าน</a>
        <?php endif; ?>
        <a href="editor.php?slug=<?= urlencode($a['slug']) ?>">แก้ไข</a>
      </div>
    </div>
  <?php endforeach; ?>
  <?php if ($totalPages && $totalPages > 1 && $pageUrl): ?>
    <div class="pagination">
      <?php if ($page > 1): ?>
        <a href="<?= htmlspecialchars($pageUrl(1)) ?>">&laquo; แรกสุด</a>
        <a href="<?= htmlspecialchars($pageUrl($page - 1)) ?>">&lsaquo; ก่อนหน้า</a>
      <?php endif; ?>
      <?php foreach (paginationWindow($page, $totalPages) as $p): ?>
        <?php if ($p === '...'): ?>
          <span class="pagination-ellipsis">&hellip;</span>
        <?php elseif ($p === $page): ?>
          <span class="pagination-current"><?= $p ?></span>
        <?php else: ?>
          <a href="<?= htmlspecialchars($pageUrl($p)) ?>"><?= $p ?></a>
        <?php endif; ?>
      <?php endforeach; ?>
      <?php if ($page < $totalPages): ?>
        <a href="<?= htmlspecialchars($pageUrl($page + 1)) ?>">ถัดไป &rsaquo;</a>
        <a href="<?= htmlspecialchars($pageUrl($totalPages)) ?>">สุดท้าย &raquo;</a>
      <?php endif; ?>
    </div>
  <?php endif; ?>
<?php endif; ?>
