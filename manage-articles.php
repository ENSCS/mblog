<?php
require __DIR__ . '/includes/manage-list.php';

renderManageListPage('post', [
    'scriptName' => 'manage-articles.php',
    'pageTitle' => 'จัดการบทความ',
    'emptyMessage' => 'ไม่พบบทความ',
    'showTaxonomyFilters' => true,
]);
