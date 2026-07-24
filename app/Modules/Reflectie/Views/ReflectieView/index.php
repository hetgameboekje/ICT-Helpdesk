<?php
/**
 * Shell-view, zie Kennisbank/Views/KennisbankView/index.php voor uitleg van het patroon. Inhoud
 * komt van public/assets/js/pages/reflecties-index.js tegen /api/v1/reflecties. /reflecties/{id}
 * rendert dezelfde shell (zie show.php) — split-view zoals in het Lovable-ontwerp
 * (src/routes/modules.reflectie.tsx).
 */
?>
<div id="reflecties-app" data-page="reflecties-index"></div>
<script type="module" src="/assets/js/pages/reflecties-index.js"></script>
