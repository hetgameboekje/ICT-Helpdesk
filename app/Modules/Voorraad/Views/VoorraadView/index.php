<?php
/**
 * Shell-view, zie Kennisbank/Views/KennisbankView/index.php voor uitleg van het patroon. Inhoud
 * komt van public/assets/js/pages/voorraad-index.js tegen /api/v1/voorraad. /voorraad/{id} rendert
 * dezelfde shell (zie show.php). Aanmaken/bewerken blijft op de oude formulieren
 * (/voorraad/create, /voorraad/{id}/edit) — zie VoorraadService.
 */
?>
<div id="voorraad-app" data-page="voorraad-index"></div>
<script type="module" src="/assets/js/pages/voorraad-index.js"></script>
