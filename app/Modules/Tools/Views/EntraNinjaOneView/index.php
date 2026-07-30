<?php
/** @var array{users:array,skipped:array,include_guests:bool,bestandsnaam:?string,gegenereerd_op:string}|null $export */
require_once APP_ROOT . '/app/Views/partials/tools-tabs.php';

$flashSuccess = $_SESSION['flash_success'] ?? null;
$flashError = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$PREVIEW_LIMIT = 50;
$tabDelimited = $export !== null ? \App\Modules\Tools\EntraNinjaOne\NinjaOneExporter::exportTabDelimited($export['users']) : '';
?>
<?= toolsTabs('entra-ninjaone') ?>
<div class="page-header">
  <div style="display:flex;align-items:center;gap:12px">
    <a class="btn" href="/tools" style="padding:6px 10px">&larr;</a>
    <div class="page-title">Entra ID naar NinjaOne</div>
  </div>
</div>

<?php if ($flashSuccess): ?>
  <div class="alert alert-success"><?= htmlspecialchars($flashSuccess) ?></div>
<?php endif; ?>
<?php if ($flashError): ?>
  <div class="alert alert-error"><?= htmlspecialchars($flashError) ?></div>
<?php endif; ?>

<div class="card" style="margin-bottom:14px">
  <div class="card-header"><span class="card-title">CSV uploaden</span></div>
  <form method="post" action="/tools/entra-ninjaone/upload" enctype="multipart/form-data" style="padding:16px">
    <p style="font-size:13px;color:var(--color-text-secondary);margin-top:0">
      Upload een gebruikersexport uit Entra ID (kolommen zoals <code>id,displayName,userPrincipalName,userType,
      onPremisesSyncEnabled,identities,companyName,creationType</code>). Alleen rijen met een geldig e-mailadres
      in <code>userPrincipalName</code> en (standaard) <code>userType = Member</code> worden meegenomen.
    </p>
    <div class="form-group">
      <label class="form-label">CSV-bestand</label>
      <input type="file" name="bestand" accept=".csv" required>
    </div>
    <div class="form-group">
      <label class="form-label">
        <input type="checkbox" name="include_guests" value="1" <?= !empty($export['include_guests']) ? 'checked' : '' ?>>
        Ook guests meenemen (standaard alleen 'Member')
      </label>
    </div>
    <button class="btn btn-primary" type="submit">Converteren</button>
  </form>
</div>

<?php if ($export !== null): ?>
  <?php
    $aantalGebruikers = count($export['users']);
    $aantalOvergeslagen = count($export['skipped']);
    $previewRows = array_slice($export['users'], 0, $PREVIEW_LIMIT);
  ?>
  <div class="card" style="margin-bottom:14px">
    <div class="card-header"><span class="card-title">Resultaat</span></div>
    <div style="padding:16px;font-size:13px;color:var(--color-text-secondary)">
      <?= $aantalGebruikers ?> gebruiker(s) geschikt voor NinjaOne-import, <?= $aantalOvergeslagen ?> overgeslagen.
      <?php if (!empty($export['bestandsnaam'])): ?>
        Bron: <strong><?= htmlspecialchars($export['bestandsnaam']) ?></strong>.
      <?php endif; ?>
      Gegenereerd op <?= htmlspecialchars($export['gegenereerd_op']) ?>.
    </div>
    <div style="padding:0 16px 16px;display:flex;gap:8px">
      <a class="btn btn-primary" href="/tools/entra-ninjaone/download">Download .tsv</a>
    </div>
  </div>

  <div class="card" style="margin-bottom:14px">
    <div class="card-header">
      <span class="card-title">Preview (<?= count($previewRows) ?> van <?= $aantalGebruikers ?>)</span>
    </div>
    <?php if ($previewRows === []): ?>
      <div class="empty-state">Geen enkele rij was geschikt voor NinjaOne-import.</div>
    <?php else: ?>
      <div class="table-wrap">
        <table>
          <thead>
            <tr><th>First name</th><th>Last name</th><th>Email</th><th>Phone</th></tr>
          </thead>
          <tbody>
            <?php foreach ($previewRows as $u): ?>
            <tr>
              <td><?= htmlspecialchars($u['firstName']) ?></td>
              <td><?= htmlspecialchars($u['lastName']) ?></td>
              <td><?= htmlspecialchars($u['email']) ?></td>
              <td><?= htmlspecialchars($u['phone']) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

  <div class="card" style="margin-bottom:14px">
    <div class="card-header"><span class="card-title">TAB-delimited (copy/paste in NinjaOne)</span></div>
    <div style="padding:16px">
      <textarea id="entraTabOutput" readonly rows="8" style="width:100%;font-family:var(--font-mono);font-size:12px" onclick="this.select()"><?= htmlspecialchars($tabDelimited) ?></textarea>
      <button type="button" class="btn" style="margin-top:8px" id="entraCopyBtn">Kopieer naar klembord</button>
      <span id="entraCopyStatus" style="font-size:12px;color:var(--color-text-secondary);margin-left:8px"></span>
    </div>
  </div>

  <?php if ($aantalOvergeslagen > 0): ?>
    <div class="card">
      <div class="card-header"><span class="card-title">Overgeslagen rijen (<?= $aantalOvergeslagen ?>)</span></div>
      <div class="table-wrap">
        <table>
          <thead><tr><th>Regel</th><th>Weergave</th><th>Reden</th></tr></thead>
          <tbody>
            <?php foreach ($export['skipped'] as $s): ?>
            <tr>
              <td class="mono"><?= (int) $s['rij'] ?></td>
              <td><?= htmlspecialchars($s['weergave']) ?></td>
              <td style="color:var(--color-text-secondary)"><?= htmlspecialchars($s['reden']) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  <?php endif; ?>

  <script>
  (function () {
      var btn = document.getElementById('entraCopyBtn');
      var textarea = document.getElementById('entraTabOutput');
      var status = document.getElementById('entraCopyStatus');
      if (!btn || !textarea) return;

      btn.addEventListener('click', function () {
          textarea.select();
          var klaar = function () {
              status.textContent = 'Gekopieerd!';
              setTimeout(function () { status.textContent = ''; }, 2000);
          };

          if (navigator.clipboard && navigator.clipboard.writeText) {
              navigator.clipboard.writeText(textarea.value).then(klaar).catch(function () {
                  document.execCommand('copy');
                  klaar();
              });
          } else {
              document.execCommand('copy');
              klaar();
          }
      });
  })();
  </script>
<?php endif; ?>
