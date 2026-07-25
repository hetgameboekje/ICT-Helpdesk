<?php
/** @var string $content */
/** @var string $activeModule */
/** @var string $pageTitle */
/** @var array|null $currentUser */
/** @var array<string,bool> $navRechten */
/** @var array<string,int> $navBadges */
/** @var string $csrfToken */
/** @var array<int,string> $breadcrumbs */

$navRechten = $navRechten ?? [];
$navBadges = $navBadges ?? [];
$breadcrumbs = $breadcrumbs ?? [];
$active = $activeModule ?? '';
?>
<!DOCTYPE html>
<html lang="nl" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? 'Intranet') ?> · Intranet</title>
    <meta name="csrf-token" content="<?= htmlspecialchars($csrfToken ?? '') ?>">
    <script>
        (function () {
            var theme = localStorage.getItem('theme') === 'dark' ? 'dark' : 'light';
            document.documentElement.setAttribute('data-bs-theme', theme);
        })();
    </script>
    <script src="/assets/js/csrf.js"></script>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>

<div class="app-shell">
    <?php require APP_ROOT . '/app/Views/layouts/partials/sidebar.php'; ?>

    <div class="app-content">
        <header class="topbar">
            <button type="button" class="topbar-menu-btn d-lg-none" id="sidebarOpenBtn" aria-label="Navigatie openen">
                <i class="bi bi-list"></i>
            </button>
            <div class="topbar-titles">
                <?php if (!empty($breadcrumbs)): ?>
                    <div class="topbar-breadcrumb"><?= htmlspecialchars(implode(' / ', $breadcrumbs)) ?></div>
                <?php endif; ?>
                <div class="topbar-title"><?= htmlspecialchars($pageTitle ?? 'Intranet') ?></div>
            </div>
            <button type="button" class="topbar-search d-none d-lg-flex" id="globalSearchTrigger">
                <i class="bi bi-search"></i>
                <span class="topbar-search-placeholder">Zoek tickets, medewerkers, KB-artikelen&hellip;</span>
                <kbd>&#8984;K</kbd>
            </button>
        </header>

        <div class="global-search-backdrop" id="globalSearchBackdrop" hidden></div>
        <div class="global-search-panel" id="globalSearchPanel" role="dialog" aria-modal="true" aria-label="Zoeken" hidden>
            <div class="global-search-panel-header">
                <i class="bi bi-search"></i>
                <input type="text" id="globalSearchInput" placeholder="Zoek tickets, medewerkers, KB-artikelen&hellip;" autocomplete="off">
                <kbd>ESC</kbd>
            </div>
            <div class="global-search-body" id="globalSearchResults"></div>
            <div class="global-search-footer">
                <span><i class="bi bi-command"></i> K om te openen</span>
                <span id="globalSearchCount"></span>
            </div>
        </div>

        <main class="main<?= $active === 'dashboard' ? ' page-dashboard' : '' ?>">
            <?= $content ?>
        </main>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script src="/assets/js/app.js"></script>
<script type="module" src="/assets/js/global-search.js"></script>
<script>
(function () {
    var sidebar = document.getElementById('appSidebar');
    var backdrop = document.getElementById('sidebarBackdrop');
    var openBtn = document.getElementById('sidebarOpenBtn');
    var closeBtn = document.getElementById('sidebarCloseBtn');

    function openSidebar() {
        document.body.classList.add('sidebar-open');
    }
    function closeSidebar() {
        document.body.classList.remove('sidebar-open');
    }

    if (openBtn) { openBtn.addEventListener('click', openSidebar); }
    if (closeBtn) { closeBtn.addEventListener('click', closeSidebar); }
    if (backdrop) { backdrop.addEventListener('click', closeSidebar); }

    // Sidebar-zoekfunctie: filtert modules client-side, geen backend-zoekroute nodig.
    var searchInput = document.getElementById('sidebarSearchInput');
    if (searchInput) {
        searchInput.addEventListener('input', function () {
            var term = searchInput.value.trim().toLowerCase();
            document.querySelectorAll('[data-sidebar-group]').forEach(function (group) {
                var anyVisible = false;
                group.querySelectorAll('[data-sidebar-item]').forEach(function (item) {
                    var match = term === '' || (item.getAttribute('data-label') || '').indexOf(term) !== -1;
                    item.style.display = match ? '' : 'none';
                    if (match) { anyVisible = true; }
                });
                group.style.display = anyVisible ? '' : 'none';
            });
        });
    }
})();
</script>
</body>
</html>
