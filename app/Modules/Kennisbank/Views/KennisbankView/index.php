<?php
/**
 * Shell-view: layout/sidebar/topbar komen van app/Views/layouts/app.php, de inhoud wordt
 * client-side opgebouwd door public/assets/js/pages/kennisbank-index.js, die /api/v1/kennisbank
 * aanroept (zie CLAUDE.md > API-architectuur, Kennisbank volgt het Tickets-referentiepatroon).
 * /kennisbank/{id} (KennisbankController::show) rendert dezelfde shell — er is geen aparte
 * detailpagina, net als in het Lovable-ontwerp (split-view lijst + artikel binnen één scherm).
 */
?>
<div id="kennisbank-app" data-page="kennisbank-index"></div>
<script type="module" src="/assets/js/pages/kennisbank-index.js"></script>
