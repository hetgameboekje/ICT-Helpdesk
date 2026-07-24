<?php
/** @var array $jobs */
require_once APP_ROOT . '/app/Views/partials/ticket-helpers.php';
require_once APP_ROOT . '/app/Views/partials/tools-tabs.php';

use App\Core\Table;

$flashSuccess = $_SESSION['flash_success'] ?? null;
$flashError = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$statusLabels = ['queued' => 'In wachtrij', 'processing' => 'Bezig', 'done' => 'Gereed', 'error' => 'Fout'];
$statusBadgeClass = ['queued' => 'badge-wacht_op_info', 'processing' => 'badge-in_behandeling', 'done' => 'badge-afgehandeld', 'error' => 'badge-afgewezen'];
?>
<?= toolsTabs('telefoonlijst') ?>
<div class="page-header">
  <div style="display:flex;align-items:center;gap:12px">
    <a class="btn" href="/tools" style="padding:6px 10px">&larr;</a>
    <div class="page-title">Telefoonlijst naar VCF</div>
  </div>
</div>

<?php if ($flashSuccess): ?>
  <div class="alert alert-success"><?= htmlspecialchars($flashSuccess) ?></div>
<?php endif; ?>
<?php if ($flashError): ?>
  <div class="alert alert-error"><?= htmlspecialchars($flashError) ?></div>
<?php endif; ?>

<div class="card" style="margin-bottom:16px">
  <div class="card-header"><span class="card-title">Bestand uploaden</span></div>
  <div style="padding:16px">
    <form method="post" action="/tools/telefoonlijst" enctype="multipart/form-data">
      <div class="form-group">
        <label class="form-label">Excel-bestand (.xlsx)</label>
        <input type="file" name="bestand" accept=".xlsx" required>
        <div style="font-size:12px;color:var(--color-text-tertiary);margin-top:4px">
          Verwachte kolommen: Naam, Functie, Afdeling, Toestel, GSM Nummer (kolomvolgorde maakt niet uit).
        </div>
      </div>
      <button class="btn btn-primary" type="submit">Uploaden</button>
    </form>
  </div>
</div>

<div class="card">
  <?php
  $table = (new Table())
      ->emptyText('Nog geen bestanden geüpload.')
      ->column('original_filename', 'Bestand', fn (array $j) => htmlspecialchars($j['original_filename']), ['sortable' => false])
      ->column('status', 'Status', fn (array $j) => '<span class="badge ' . ($statusBadgeClass[$j['status']] ?? '') . '">' . htmlspecialchars($statusLabels[$j['status']] ?? $j['status']) . '</span>', ['sortable' => false])
      ->column('contact_count', 'Contacten', fn (array $j) => $j['contact_count'] !== null ? (int) $j['contact_count'] : '—', ['sortable' => false])
      ->column('created_at', 'Geüpload', fn (array $j) => formatDatumTijd($j['created_at']), ['sortable' => false])
      ->column('acties', 'Acties', fn (array $j) => '<div style="display:flex;gap:8px">'
          . '<a class="btn" href="/tools/telefoonlijst/' . (int) $j['id'] . '">Bekijken</a>'
          . deleteButton('tools/telefoonlijst', (int) $j['id'])
          . '</div>', ['sortable' => false])
      ->rows($jobs);
  echo $table->render();
  ?>
</div>
