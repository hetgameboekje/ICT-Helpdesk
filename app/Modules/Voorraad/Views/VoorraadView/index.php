<?php
/**
 * Shell-view: zie Views/KennisbankView/index.php voor uitleg van het patroon.
 * public/assets/js/pages/voorraad-index.js bouwt de split-view (lijst + detail), o.b.v.
 * src/routes/modules.voorraad.tsx. /voorraad/create en /voorraad/{id}/edit blijven de oude
 * server-rendered formulieren (DxDiag-upload, serienummer-batches) — zie VoorraadService.
 */
?>
<div id="voorraad-app" data-page="voorraad-index"></div>
<script type="module" src="/assets/js/pages/voorraad-index.js"></script>
