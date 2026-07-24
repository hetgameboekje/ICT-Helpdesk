<?php
/**
 * Shell-view, zie Kennisbank/Views/KennisbankView/index.php voor uitleg van het patroon. Inhoud
 * komt van public/assets/js/pages/verbeterpunten-index.js tegen /api/v1/verbeterpunten.
 * /verbeterpunten/{id} rendert dezelfde shell (zie show.php) — split-view zoals in het Lovable-
 * ontwerp (src/routes/modules.verbeterpunt.tsx).
 */
?>
<div id="verbeterpunten-app" data-page="verbeterpunten-index"></div>
<script type="module" src="/assets/js/pages/verbeterpunten-index.js"></script>
