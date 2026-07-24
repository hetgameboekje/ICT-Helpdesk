<?php
/**
 * Shell-view, identiek aan Views/DeviceView/index.php — zie die file voor uitleg. Bestaat apart
 * zodat een directe/gedeelde link naar één apparaat werkt; device-index.js leest het id uit het
 * URL-pad en opent dat apparaat meteen in het detailpaneel.
 */
?>
<div class="page-header">
  <div class="page-title">Applicaties</div>
  <div style="display:flex;gap:8px;align-items:center">
    <a class="btn" href="/apparaten/software">Software-inventaris</a>
    <a href="/apparaten/create" style="font-size:12px;color:var(--color-text-tertiary)">Nieuw apparaat handmatig toevoegen</a>
    <button type="button" class="btn" id="lokaal-import-knop">+ Lokaal</button>
    <button type="button" class="btn btn-accent" id="globaal-import-knop">+ Globaal</button>
  </div>
</div>

<form method="post" action="/apparaten/software-import" enctype="multipart/form-data" id="globaal-import-form" style="display:none">
  <input type="file" name="bestand" accept=".csv" id="globaal-import-bestand">
</form>
<form method="post" action="/apparaten" enctype="multipart/form-data" id="lokaal-import-form" style="display:none">
  <input type="file" name="bestand" accept=".csv" id="lokaal-import-bestand">
</form>

<div id="device-app" data-page="device-index"></div>
<script type="module" src="/assets/js/pages/device-index.js"></script>

<script>
(function () {
    function koppel(knopId, bestandId, formId) {
        var knop = document.getElementById(knopId);
        var bestand = document.getElementById(bestandId);
        var form = document.getElementById(formId);
        if (!knop || !bestand || !form) return;

        knop.addEventListener('click', function () { bestand.click(); });
        bestand.addEventListener('change', function () {
            if (bestand.files.length > 0) { form.submit(); }
        });
    }

    koppel('globaal-import-knop', 'globaal-import-bestand', 'globaal-import-form');
    koppel('lokaal-import-knop', 'lokaal-import-bestand', 'lokaal-import-form');
})();
</script>
