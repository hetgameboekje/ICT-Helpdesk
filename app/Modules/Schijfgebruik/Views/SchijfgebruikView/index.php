<?php
/**
 * Shell-view: zie Views/KennisbankView/index.php voor uitleg van het patroon. De CSV-import blijft
 * een gewoon multipart-formulier (bestandsupload hoort niet in de JSON-API-scope).
 * public/assets/js/pages/schijfgebruik-index.js bouwt de ring-gauge-lijst eronder, o.b.v.
 * src/routes/modules.schijfgebruik.tsx.
 */
?>
<div class="page-header">
  <div class="page-title">Schijfgebruik</div>
</div>

<div class="card" style="margin-bottom:14px">
  <div class="card-header"><span class="card-title">CSV importeren</span></div>
  <div style="padding:16px">
    <form method="post" action="/schijfgebruik/import" enctype="multipart/form-data" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
      <input type="file" name="bestand" accept=".csv" required>
      <button class="btn btn-primary" type="submit">Importeren</button>
      <span style="font-size:12px;color:var(--color-text-secondary)">
        Verwacht een NinjaRMM "Devices"-export (.csv). Vervangt bij elke import de volledige lijst met apparaten en schijven.
      </span>
    </form>
  </div>
</div>

<div id="schijfgebruik-app" data-page="schijfgebruik-index"></div>
<script type="module" src="/assets/js/pages/schijfgebruik-index.js"></script>
