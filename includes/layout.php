<?php
// Wrapper functions around partials/header.php, sidebar.php, footer.php —
// each extract()s its $args before require, so the variables a partial
// needs are declared as a real function signature (the $args keys) instead
// of implicit globals a reader has to guess by opening the partial itself.
// Mirrors WordPress's load_template(), which does the same extract-then-
// require before get_header()/get_sidebar()/get_footer().
//
// render_header() returns hasSidebar/showAdminSidebar/sidebarItems because
// header.php is what decides them (site setting + this page's own
// $showSidebar/$showAdminSidebar) — sidebar.php can't recompute them itself
// without duplicating that logic, so the caller must pass header's return
// value straight into render_sidebar().

function render_header(array $args = []): array {
    extract($args);
    require __DIR__ . '/../partials/header.php';
    return compact('hasSidebar', 'showAdminSidebar', 'sidebarItems');
}

function render_sidebar(array $args = []): void {
    extract($args);
    require __DIR__ . '/../partials/sidebar.php';
}

function render_footer(array $args = []): void {
    extract($args);
    require __DIR__ . '/../partials/footer.php';
}
