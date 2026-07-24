<?php
/**
 * Shell-view: zie Views/KennisbankView/index.php voor uitleg van het patroon. De CSV-import blijft
 * een gewoon multipart-formulier (bestandsupload hoort niet in de JSON-API-scope).
 * public/assets/js/pages/medewerker-index.js bouwt de kaarten-grid + detail-split-view eronder,
 * o.b.v. src/routes/modules.medewerker.tsx.
 */
?>
<div class="page-header">
  <div class="page-title">Medewerkers</div>
  <div style="display:flex;gap:8px">
    <a class="btn" href="/medewerkers/hierarchie">Hiërarchie</a>
    <a class="btn btn-accent" href="/medewerkers/create">+ Nieuwe medewerker</a>
  </div>
</div>

<div class="card" style="margin-bottom:14px">
  <div class="card-header"><span class="card-title">CSV importeren</span></div>
  <div style="padding:16px">
    <form method="post" action="/medewerkers/import" enctype="multipart/form-data" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
      <input type="file" name="bestand" accept=".csv" required>
      <button class="btn btn-primary" type="submit">Importeren</button>
      <span style="font-size:12px;color:var(--color-text-secondary)">
        Verwacht een gebruikers-export (.csv) met kolommen "User name", "Email", "User access", "Phone number"
        en "Assigned devices". Werkt bestaande medewerkers bij op e-mailadres en koppelt apparaten waar mogelijk.
      </span>
    </form>
  </div>
</div>

<div id="medewerker-app" data-page="medewerker-index"></div>
<script type="module" src="/assets/js/pages/medewerker-index.js"></script>
