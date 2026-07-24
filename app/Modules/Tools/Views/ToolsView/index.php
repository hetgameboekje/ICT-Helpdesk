<?php
/** @var array|null $laatsteTelefoonlijst */
require_once APP_ROOT . '/app/Views/partials/ticket-helpers.php';
require_once APP_ROOT . '/app/Views/partials/tools-tabs.php';
?>
<?= toolsTabs('overzicht') ?>
<div class="page-header">
  <div class="page-title">Tools</div>
</div>

<div style="display:grid;grid-template-columns:repeat(auto-fill, minmax(260px, 1fr));gap:16px">
  <div class="card">
    <div class="card-header"><span class="card-title">Telefoonlijst naar VCF</span></div>
    <div style="padding:16px">
      <p style="font-size:13px;color:var(--color-text-secondary);margin-top:0">
        Upload een interne telefoonlijst (.xlsx) en zet deze om naar een .vcf-bestand voor import op Android/iPhone.
      </p>
      <a class="btn btn-primary" href="/tools/telefoonlijst">Openen</a>
    </div>
  </div>

  <div class="card">
    <div class="card-header"><span class="card-title">Laatste telefoonlijst</span></div>
    <div style="padding:16px">
      <?php if ($laatsteTelefoonlijst === null): ?>
        <p style="font-size:13px;color:var(--color-text-secondary);margin-top:0">Nog geen telefoonlijst verwerkt.</p>
      <?php else: ?>
        <p style="font-size:13px;color:var(--color-text-secondary);margin-top:0">
          Laatst verwerkt: <?= formatDatumTijd($laatsteTelefoonlijst['processed_at']) ?><br>
          <?= (int) $laatsteTelefoonlijst['contact_count'] ?> contact(en).
        </p>
        <a class="btn btn-primary" href="/tools/telefoonlijst/<?= (int) $laatsteTelefoonlijst['id'] ?>/download">Download .vcf</a>
      <?php endif; ?>
    </div>
  </div>

  <div class="card">
    <div class="card-header"><span class="card-title">Handtekeningen</span></div>
    <div style="padding:16px">
      <p style="font-size:13px;color:var(--color-text-secondary);margin-top:0">
        Stel een e-mailhandtekening samen uit regels tekst, iconen en logo's, en kopieer de HTML naar Outlook.
      </p>
      <a class="btn btn-primary" href="/tools/handtekeningen">Openen</a>
    </div>
  </div>

  <div class="card">
    <div class="card-header"><span class="card-title">Herstart-herinneringen</span></div>
    <div style="padding:16px">
      <p style="font-size:13px;color:var(--color-text-secondary);margin-top:0">
        Overzicht van apparaten die al lang niet herstart zijn, met een instelbare herinneringsmail naar de gekoppelde medewerker.
      </p>
      <a class="btn btn-primary" href="/tools/herstart-herinneringen">Openen</a>
    </div>
  </div>

  <div class="card">
    <div class="card-header"><span class="card-title">Installatie</span></div>
    <div style="padding:16px">
      <p style="font-size:13px;color:var(--color-text-secondary);margin-top:0">
        Beheer de installatie-checklist (applicaties + profielen), print een blanco afvinklijst of wijs hem toe aan een apparaat om digitaal af te vinken.
      </p>
      <a class="btn btn-primary" href="/tools/installatie">Openen</a>
    </div>
  </div>
</div>
