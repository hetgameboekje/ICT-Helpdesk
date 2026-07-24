<?php
/** @var string|null $output */
/** @var bool $gitBeschikbaar */
/** @var bool $devModus */
/** @var bool $gitPullEnabled */
require_once APP_ROOT . '/app/Views/partials/beheer-tabs.php';
?>
<?= beheerTabs('systeem') ?>
<div class="page-header">
  <div class="page-title">Beheer</div>
</div>

<div class="alert <?= $devModus ? 'alert-success' : 'alert-error' ?>" style="margin-bottom:16px">
  <?php if ($devModus): ?>
    <strong>Dev-modus actief.</strong> Bij elke keer dat het loginscherm wordt geladen, wordt automatisch
    <code>git pull</code> gedaan en het databaseschema geparsed &eacute;n toegepast. Zet <code>dev</code> naar
    <code>false</code> in <code>config/config.php</code> (of <code>APP_DEV=false</code> in <code>.env</code>)
    voordat je naar productie (bv. Hostnet) deployt.
  <?php else: ?>
    <strong>Productiemodus.</strong> Er gebeurt niets automatisch — git pull en database parsen kunnen alleen
    hieronder handmatig gestart worden.
  <?php endif; ?>
</div>

<div class="detail-layout">
  <div>
    <div class="card" style="margin-bottom:16px">
      <div class="card-header"><span class="card-title">GitHub</span></div>
      <div style="padding:16px">
        <p style="font-size:13px;color:var(--color-text-secondary);margin-top:0">
          Haalt de laatste wijzigingen op van de remote repository (<code>git pull</code>) op de server.
        </p>
        <?php if (!$gitPullEnabled): ?>
          <p style="font-size:13px;color:#b3261e">
            Uitgeschakeld op deze server (<code>gitPullEnabled = false</code>) — bedoeld voor hosts zonder
            shell-toegang, zoals Hostnet shared webhosting. Zet dit alleen aan op een server met SSH/exec
            (bv. Docker/VPS).
          </p>
        <?php elseif (!$gitBeschikbaar): ?>
          <p style="font-size:13px;color:#b3261e">Geen .git-map gevonden op de server — dit is waarschijnlijk geen git-checkout.</p>
        <?php else: ?>
          <form method="post" action="/beheer/git-pull" onsubmit="return confirm('Weet je zeker dat je git pull wilt uitvoeren op de live server?')">
            <button class="btn btn-primary" type="submit">Git pull uitvoeren</button>
          </form>
        <?php endif; ?>
      </div>
    </div>

    <div class="card">
      <div class="card-header"><span class="card-title">Database</span></div>
      <div style="padding:16px">
        <p style="font-size:13px;color:var(--color-text-secondary);margin-top:0">
          Zet de tabel-definities in <code>database/xml/*.xml</code> om naar <code>database/.parsed/schema.sql</code>
          en toont de SQL, zonder deze uit te voeren.
        </p>
        <form method="post" action="/beheer/database-parsen" style="margin-bottom:16px">
          <button class="btn" type="submit">Database parsen (alleen tonen)</button>
        </form>
        <p style="font-size:13px;color:var(--color-text-secondary)">
          Voert diezelfde SQL direct uit tegen de database: nieuwe tabellen worden aangemaakt (<code>CREATE TABLE IF NOT EXISTS</code>),
          ontbrekende kolommen op bestaande tabellen worden toegevoegd (<code>ALTER TABLE ... ADD COLUMN</code>), en seed-data
          wordt ingevoegd (<code>INSERT IGNORE</code>). Idempotent: bestaande tabellen, kolommen en rijen blijven ongewijzigd.
        </p>
        <form method="post" action="/beheer/database-toepassen" onsubmit="return confirm('Schema direct toepassen op de live database? Dit maakt ontbrekende tabellen/kolommen aan.')">
          <button class="btn btn-primary" type="submit">Toepassen op database</button>
        </form>
      </div>
    </div>

    <div class="card" style="margin-top:16px">
      <div class="card-header"><span class="card-title">Exporteren</span></div>
      <div style="padding:16px">
        <p style="font-size:13px;color:var(--color-text-secondary);margin-top:0">
          Exporteer een of meer modules naar Excel (elke module een eigen werkblad) of CSV.
        </p>
        <a class="btn btn-primary" href="/beheer/exporteren">Naar exporteren</a>
      </div>
    </div>

    <div class="card" style="margin-top:16px">
      <div class="card-header"><span class="card-title">Locaties</span></div>
      <div style="padding:16px">
        <p style="font-size:13px;color:var(--color-text-secondary);margin-top:0">
          Beheer de locaties die gebruikers kunnen kiezen bij het registreren van tijd in Urenstaat, inclusief wie elke locatie mag zien.
        </p>
        <a class="btn btn-primary" href="/beheer/locaties">Naar locaties</a>
      </div>
    </div>

    <div class="card" style="margin-top:16px">
      <div class="card-header"><span class="card-title">E-mails</span></div>
      <div style="padding:16px">
        <p style="font-size:13px;color:var(--color-text-secondary);margin-top:0">
          Overzicht van de e-mailwachtrij: ticketherinneringen/-escalaties en hun verzendstatus.
        </p>
        <a class="btn btn-primary" href="/beheer/emails">Naar e-mails</a>
      </div>
    </div>
  </div>

  <div>
    <div class="card">
      <div class="card-header"><span class="card-title">Uitvoer</span></div>
      <div style="padding:16px">
        <?php if ($output): ?>
          <pre style="white-space:pre-wrap;font-size:12px;background:var(--color-background-secondary);padding:12px;border-radius:8px;max-height:400px;overflow:auto"><?= htmlspecialchars($output) ?></pre>
        <?php else: ?>
          <p style="font-size:13px;color:var(--color-text-secondary)">Nog geen actie uitgevoerd.</p>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
