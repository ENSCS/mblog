<?php
$menuItems = require __DIR__ . '/../config/menu.php';
usort($menuItems, fn($a, $b) => $a['order'] <=> $b['order']);
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $pageTitle ?></title>
<?= $extraHead ?? '' ?>
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<div class="topbar">
  <div class="container">
    <div class="topbar-left">
      <a href="index.php" class="brand">mBlog</a>
      <nav class="topbar-menu">
        <?php foreach ($menuItems as $item): ?>
          <a href="<?= htmlspecialchars($item['href']) ?>"><?= htmlspecialchars($item['label']) ?></a>
        <?php endforeach; ?>
      </nav>
    </div>
    <div class="actions">
      <?= $topbarActions ?? '' ?>
    </div>
  </div>
</div>
<div class="container">
