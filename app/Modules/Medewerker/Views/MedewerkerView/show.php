<?php
/**
 * Shell-view, identiek aan Views/MedewerkerView/index.php (zonder de import-kaart) — zie die file
 * voor uitleg. medewerker-index.js leest het id uit het URL-pad en opent die medewerker meteen in
 * het detailpaneel.
 */
?>
<div class="page-header">
  <div class="page-title">Medewerkers</div>
  <div style="display:flex;gap:8px">
    <a class="btn" href="/medewerkers/hierarchie">Hiërarchie</a>
    <a class="btn btn-accent" href="/medewerkers/create">+ Nieuwe medewerker</a>
  </div>
</div>

<div id="medewerker-app" data-page="medewerker-index"></div>
<script type="module" src="/assets/js/pages/medewerker-index.js"></script>
