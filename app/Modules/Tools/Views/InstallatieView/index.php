<?php
/** @var array $applicaties */
/** @var array $profielen */
/** @var array $opdrachten */
require_once APP_ROOT . '/app/Views/partials/ticket-helpers.php';
require_once APP_ROOT . '/app/Views/partials/tools-tabs.php';

$flashSuccess = $_SESSION['flash_success'] ?? null;
$flashError = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);
?>
<?= toolsTabs('installatie') ?>
<div class="page-header">
  <div class="page-title">Installatie</div>
  <a class="btn btn-primary" href="/tools/installatie/opdrachten/nieuw">+ Toewijzen aan apparaat</a>
</div>

<?php if ($flashSuccess): ?>
  <div class="alert alert-success"><?= htmlspecialchars($flashSuccess) ?></div>
<?php endif; ?>
<?php if ($flashError): ?>
  <div class="alert alert-error"><?= htmlspecialchars($flashError) ?></div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:repeat(auto-fill, minmax(340px, 1fr));gap:16px;margin-bottom:16px">
  <div class="card">
    <div class="card-header"><span class="card-title">Hoofdlijst</span></div>
    <div style="padding:16px">
      <?php if (empty($applicaties)): ?>
        <div class="empty-state">Nog geen applicaties toegevoegd.</div>
      <?php else: ?>
        <?php foreach ($applicaties as $app): ?>
          <div style="display:flex;align-items:center;justify-content:space-between;padding:4px 0;border-bottom:1px solid var(--color-border-tertiary)">
            <span><?= htmlspecialchars($app['naam']) ?></span>
            <form method="post" action="/tools/installatie/applicaties/<?= (int) $app['id'] ?>/verwijderen"
                  onsubmit="return confirm('Deze applicatie uit de hoofdlijst verwijderen?')">
              <button class="btn" type="submit" style="padding:2px 8px;font-size:12px">&times;</button>
            </form>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>

      <form method="post" action="/tools/installatie/applicaties" style="display:flex;gap:8px;margin-top:12px">
        <input type="text" name="naam" placeholder="Nieuwe applicatie" required style="flex:1">
        <button class="btn btn-primary" type="submit">Toevoegen</button>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="card-header"><span class="card-title">Blanco printen</span></div>
    <div style="padding:16px">
      <form method="get" action="/tools/installatie/print">
        <p style="font-size:13px;color:var(--color-text-secondary);margin-top:0">
          Print de hoofdlijst, eventueel aangevuld met profielen, om op papier af te vinken.
        </p>
        <?php foreach ($profielen as $profiel): ?>
          <label style="display:flex;align-items:center;gap:6px;font-weight:normal;margin-bottom:4px">
            <input type="checkbox" name="profielen[]" value="<?= (int) $profiel['id'] ?>"> <?= htmlspecialchars($profiel['naam']) ?>
          </label>
        <?php endforeach; ?>
        <button class="btn btn-primary" type="submit" style="margin-top:8px">Print openen</button>
      </form>
    </div>
  </div>
</div>

<div class="card" style="margin-bottom:16px">
  <div class="card-header"><span class="card-title">Profielen</span></div>
  <div style="padding:16px">
    <?php if (empty($profielen)): ?>
      <div class="empty-state">Nog geen profielen.</div>
    <?php else: ?>
      <div style="display:grid;grid-template-columns:repeat(auto-fill, minmax(280px, 1fr));gap:16px">
        <?php foreach ($profielen as $profiel): ?>
          <div style="border:1px solid var(--color-border-tertiary);border-radius:8px;padding:12px">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px">
              <strong><?= htmlspecialchars($profiel['naam']) ?></strong>
              <form method="post" action="/tools/installatie/profielen/<?= (int) $profiel['id'] ?>/verwijderen"
                    onsubmit="return confirm('Profiel \'<?= htmlspecialchars(addslashes($profiel['naam']), ENT_QUOTES) ?>\' en al zijn items verwijderen?')">
                <button class="btn" type="submit" style="padding:2px 8px;font-size:12px">&times;</button>
              </form>
            </div>
            <?php foreach ($profiel['items'] as $item): ?>
              <div style="display:flex;align-items:center;justify-content:space-between;padding:3px 0;font-size:13px">
                <span><?= htmlspecialchars($item['naam']) ?></span>
                <form method="post" action="/tools/installatie/profielen/<?= (int) $profiel['id'] ?>/items/<?= (int) $item['id'] ?>/verwijderen">
                  <button class="btn" type="submit" style="padding:0 6px;font-size:11px">&times;</button>
                </form>
              </div>
            <?php endforeach; ?>
            <form method="post" action="/tools/installatie/profielen/<?= (int) $profiel['id'] ?>/items" style="display:flex;gap:6px;margin-top:8px">
              <input type="text" name="naam" placeholder="Extra item" required style="flex:1;font-size:13px">
              <button class="btn" type="submit" style="font-size:12px">+</button>
            </form>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <form method="post" action="/tools/installatie/profielen" style="display:flex;gap:8px;margin-top:16px">
      <input type="text" name="naam" placeholder="Nieuw profiel (bv. Engineer)" required style="flex:1">
      <button class="btn btn-primary" type="submit">Profiel toevoegen</button>
    </form>
  </div>
</div>

<div class="card">
  <div class="card-header"><span class="card-title">Toegewezen checklists</span></div>
  <?php if (empty($opdrachten)): ?>
    <div class="empty-state">Nog geen checklists toegewezen aan een apparaat.</div>
  <?php else: ?>
    <table class="table">
      <thead>
        <tr>
          <th>Apparaat</th>
          <th>Voortgang</th>
          <th>Aangemaakt</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($opdrachten as $o): ?>
        <tr>
          <td><a href="/tools/installatie/opdrachten/<?= (int) $o['id'] ?>"><?= htmlspecialchars($o['apparaat_naam'] ?? '—') ?></a></td>
          <td><?= (int) $o['items_afgevinkt'] ?> / <?= (int) $o['items_totaal'] ?></td>
          <td><?= formatDatumTijd($o['created_at']) ?></td>
          <td style="display:flex;gap:6px;justify-content:flex-end">
            <a class="btn" href="/tools/installatie/opdrachten/<?= (int) $o['id'] ?>/print" target="_blank" style="padding:2px 8px;font-size:12px">Printen</a>
            <?= deleteButton('tools/installatie/opdrachten', (int) $o['id']) ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>
