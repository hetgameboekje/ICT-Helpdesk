<?php
/**
 * Shell-view: layout/sidebar/topbar komen van app/Views/layouts/app.php zoals bij alle andere
 * pagina's, maar de inhoud van deze pagina zelf wordt client-side opgebouwd door
 * public/assets/js/pages/tickets-index.js, die /api/v1/tickets aanroept (zie CLAUDE.md >
 * API-architectuur — Tickets is het referentiepatroon voor de 3-laags herarchitectuur).
 * Geen PHP-variabelen nodig hier: alle data komt via de JSON-API, niet via server-side rendering.
 */
?>
<div id="tickets-app" data-page="tickets-index"></div>
<script type="module" src="/assets/js/pages/tickets-index.js"></script>
