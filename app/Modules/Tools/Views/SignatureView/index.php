<?php
/** @var array $signatures */
require_once APP_ROOT . '/app/Views/partials/ticket-helpers.php';
require_once APP_ROOT . '/app/Views/partials/tools-tabs.php';

use App\Core\Table;
?>
<?= toolsTabs('handtekeningen') ?>
<div class="page-header">
  <div style="display:flex;align-items:center;gap:12px">
    <a class="btn" href="/tools" style="padding:6px 10px">&larr;</a>
    <div class="page-title">Handtekeningen</div>
  </div>
  <a class="btn btn-primary" href="/tools/handtekeningen/nieuw">+ Nieuwe handtekening</a>
</div>

<div class="card">
  <?php
  $table = (new Table())
      ->emptyText('Nog geen handtekeningen aangemaakt.')
      ->rowUrl(fn (array $s) => "/tools/handtekeningen/{$s['id']}/bewerken")
      ->column('name', 'Naam', fn (array $s) => htmlspecialchars($s['name']), ['sortable' => false])
      ->column('updated_at', 'Laatst bewerkt', fn (array $s) => formatDatumTijd($s['updated_at']), ['sortable' => false])
      ->column('acties', 'Acties', fn (array $s) => '<div style="display:flex;gap:8px">'
          . '<a class="btn" href="/tools/handtekeningen/' . (int) $s['id'] . '/bewerken">Bewerken</a>'
          . deleteButton('tools/handtekeningen', (int) $s['id'])
          . '</div>', ['sortable' => false])
      ->rows($signatures);
  echo $table->render();
  ?>
</div>
