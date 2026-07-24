<?php
/** @var array $bezoeken */
/** @var array $pagination */
/** @var array $gebruikers */
/** @var array $ipAdressen */
/** @var array $filters */
require_once APP_ROOT . '/app/Views/partials/ticket-helpers.php';
require_once APP_ROOT . '/app/Views/partials/beheer-tabs.php';

use App\Core\Table;
?>
<?= beheerTabs('log') ?>
<div class="page-header">
  <div class="page-title">Paginabezoeken</div>
</div>

<form method="get" action="/beheer/log" class="filters" style="margin-bottom:14px">
  <input type="text" name="q" value="<?= htmlspecialchars($filters['q']) ?>" placeholder="Zoeken op URL...">
  <select name="user_id" onchange="this.form.submit()">
    <option value="">Alle gebruikers</option>
    <option value="geen" <?= $filters['user_id'] === 'geen' ? 'selected' : '' ?>>— (niet ingelogd)</option>
    <?php foreach ($gebruikers as $g): ?>
      <option value="<?= $g['id'] ?>" <?= (string) $filters['user_id'] === (string) $g['id'] ? 'selected' : '' ?>><?= htmlspecialchars($g['naam']) ?></option>
    <?php endforeach; ?>
  </select>
  <select name="ip_adres" onchange="this.form.submit()">
    <option value="">Alle IP-adressen</option>
    <?php foreach ($ipAdressen as $ip): ?>
      <option value="<?= htmlspecialchars($ip) ?>" <?= $filters['ip_adres'] === $ip ? 'selected' : '' ?>><?= htmlspecialchars($ip) ?></option>
    <?php endforeach; ?>
  </select>
  <button class="btn btn-primary" type="submit">Zoeken</button>
</form>

<div class="card">
  <?php
  $table = (new Table())
      ->emptyText('Geen paginabezoeken gevonden.')
      ->column('created_at', 'Datum/tijd', fn (array $b) => formatDatumTijd($b['created_at']), ['class' => 'col-2', 'sortable' => false])
      ->column('gebruiker_naam', 'Gebruiker', fn (array $b) => htmlspecialchars($b['gebruiker_naam'] ?? '— (niet ingelogd)'), ['sortable' => false])
      ->column('ip_adres', 'IP-adres', fn (array $b) => htmlspecialchars($b['ip_adres']), ['class' => 'col-2', 'sortable' => false])
      ->column('methode', 'Methode', fn (array $b) => htmlspecialchars($b['methode']), ['class' => 'col-2', 'sortable' => false])
      ->column('url', 'URL', fn (array $b) => '<span class="text-truncate d-block" title="' . htmlspecialchars($b['url']) . '">' . htmlspecialchars($b['url']) . '</span>', ['sortable' => false])
      ->column('parameters', 'Parameters', fn (array $b) => paginaBezoekParametersHtml($b['parameters'], (int) $b['id']), ['sortable' => false])
      ->rows($bezoeken);
  echo $table->render();
  ?>
  <?= paginationLinks($pagination) ?>
</div>
