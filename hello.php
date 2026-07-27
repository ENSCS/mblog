<?php
// Minimal example page — just config.php + header/footer, no article/feed
// includes since this page doesn't need any of them. Copy this file as the
// starting point for a new simple page: change $pageTitle, put whatever
// markup you want between header/footer, done.
require __DIR__ . '/config.php';
require __DIR__ . '/includes/stats.php';

recordPageview('other');

$pageTitle = 'Hello World';
$containerWide = true;
$showMenu = false;
include __DIR__ . '/partials/header.php';
?>

    <p>Hello world</p>

<?php include __DIR__ . '/partials/footer.php'; ?>