<?php
require __DIR__ . '/includes/manage-list.php';

renderManageListPage('page', [
    'scriptName' => 'manage-pages.php',
    'pageTitle' => 'จัดการหน้า',
    'emptyMessage' => 'ไม่พบหน้า',
    'showTaxonomyFilters' => false,
]);
