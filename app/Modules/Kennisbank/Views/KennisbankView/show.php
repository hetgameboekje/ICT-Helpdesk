<?php
/**
 * Shell-view, identiek aan Views/KennisbankView/index.php — zie die file voor uitleg. Deze route
 * (/kennisbank/{id}) bestaat apart zodat een directe/gedeelde link naar één artikel werkt (o.a.
 * vanuit tickets-show.js), maar rendert dezelfde JS-app; kennisbank-index.js leest het artikel-id
 * uit het URL-pad en opent dat artikel meteen in het detailpaneel.
 */
?>
<div id="kennisbank-app" data-page="kennisbank-index"></div>
<script type="module" src="/assets/js/pages/kennisbank-index.js"></script>
