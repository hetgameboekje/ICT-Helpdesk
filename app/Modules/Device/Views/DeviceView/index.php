<?php
/**
 * Shell-view, zie Kennisbank/Views/KennisbankView/index.php voor uitleg van het patroon. Inhoud
 * komt van public/assets/js/pages/apparaten-index.js tegen /api/v1/apparaten. /apparaten/{id}
 * rendert dezelfde shell (zie show.php). De software-inventaris-subpagina (`/apparaten/software`)
 * blijft ongewijzigd server-rendered — geen onderdeel van deze conversie.
 */
?>
<div id="apparaten-app" data-page="apparaten-index"></div>
<script type="module" src="/assets/js/pages/apparaten-index.js"></script>
