<?php
/**
 * Shell-view: layout/sidebar/topbar komen van app/Views/layouts/app.php, de inhoud wordt
 * client-side opgebouwd door public/assets/js/pages/verbeterpunt-index.js, die
 * /api/v1/verbeterpunten aanroept (zelfde 3-laags patroon als Tickets/Kennisbank).
 * /verbeterpunten/{id} (VerbeterpuntController::show) rendert dezelfde shell — split-view
 * lijst + detailpaneel in één scherm, net als in het Lovable-ontwerp.
 */
?>
<div id="verbeterpunt-app" data-page="verbeterpunt-index"></div>
<script type="module" src="/assets/js/pages/verbeterpunt-index.js"></script>
