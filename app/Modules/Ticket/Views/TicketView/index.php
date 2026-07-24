<?php
/** @var array $items */
/** @var array $pagination */
/** @var array $filterOptions */
/** @var string $search */
/** @var string|null $sort */
/** @var string $dir */
/** @var array<string,int> $statusCounts */
require_once APP_ROOT . '/app/Views/partials/ticket-helpers.php';

use App\Core\Table;

$flashSuccess = $_SESSION['flash_success'] ?? null;
$flashError = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

/** Bouwt een querystring-link die de huidige filters/sort behoudt maar $status instelt (voor de status-tabs). */
function statusTabHref(string $status): string
{
    $params = $_GET;
    unset($params['page']);
    if ($status === '') {
        unset($params['status']);
    } else {
        $params['status'] = $status;
    }
    $qs = http_build_query($params);
    return '/tickets' . ($qs !== '' ? '?' . $qs : '');
}

// Statusfilter als pil-tabs (zie Lovable tickets.index.tsx) i.p.v. een <select>. 'Actief' (alle
// niet-afgehandelde tickets) is de standaardweergave; 'alle' + de losse statussen volgen daarna.
$statusTabs = ['' => 'Actief', 'alle' => 'Alle'] + array_diff_key($filterOptions['status'], ['alle' => true]);
$statusCounts['actief'] = $statusCounts['alle'] - $statusCounts['afgehandeld'];
$huidigeStatus = $_GET['status'] ?? '';
?>
<div class="page-header">
  <div class="page-title">Tickets</div>
</div>

<?php if ($flashSuccess): ?>
  <div class="alert alert-success"><?= htmlspecialchars($flashSuccess) ?></div>
<?php endif; ?>
<?php if ($flashError): ?>
  <div class="alert alert-error"><?= htmlspecialchars($flashError) ?></div>
<?php endif; ?>

<form method="get" action="/tickets" class="list-toolbar" id="ticketZoekForm">
  <div class="search-input">
    <i class="bi bi-search"></i>
    <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Zoek op nummer, taak of opdrachtgever&hellip;">
  </div>
  <button type="button" class="btn btn-ghost" id="filtersToggleBtn"><i class="bi bi-sliders"></i> Filters</button>
  <a class="btn btn-ghost" href="/tickets/export"><i class="bi bi-download"></i> Export</a>
  <button type="button" class="btn btn-ghost" id="import-trigger-btn"><i class="bi bi-upload"></i> Importeren</button>
  <a class="btn btn-accent" href="/tickets/create"><i class="bi bi-plus-lg"></i> Nieuw</a>
  <?php if ($sort): ?>
    <input type="hidden" name="sort" value="<?= htmlspecialchars($sort) ?>">
    <input type="hidden" name="dir" value="<?= htmlspecialchars($dir) ?>">
  <?php endif; ?>
  <?php if ($huidigeStatus !== ''): ?>
    <input type="hidden" name="status" value="<?= htmlspecialchars($huidigeStatus) ?>">
  <?php endif; ?>
</form>

<form method="post" action="/tickets/import" enctype="multipart/form-data" id="import-form">
  <input type="file" name="bestand" accept=".xlsx" id="import-file-input" style="display:none" required>
</form>

<div class="status-tabs">
  <?php foreach ($statusTabs as $val => $label): ?>
    <a class="status-tab<?= $huidigeStatus === $val ? ' active' : '' ?>" href="<?= htmlspecialchars(statusTabHref($val)) ?>">
      <?= htmlspecialchars($label) ?>
      <span class="status-tab-count"><?= (int) ($statusCounts[$val === '' ? 'actief' : $val] ?? 0) ?></span>
    </a>
  <?php endforeach; ?>
</div>

<div class="filters-panel" id="filtersPanel">
  <form method="get" action="/tickets" class="filters" id="ticketFiltersForm">
    <?= filterSelect('prioriteit', 'Alle prioriteiten', $filterOptions['prioriteit']) ?>
    <?= filterSelect('afdeling_naam', 'Alle afdelingen', $filterOptions['afdeling_naam']) ?>
    <?= filterSelect('behandelaar_naam', 'Alle behandelaars', $filterOptions['behandelaar_naam']) ?>
    <button class="btn" type="submit">Toepassen</button>
    <?php if ($search !== ''): ?><input type="hidden" name="q" value="<?= htmlspecialchars($search) ?>"><?php endif; ?>
    <?php if ($huidigeStatus !== ''): ?><input type="hidden" name="status" value="<?= htmlspecialchars($huidigeStatus) ?>"><?php endif; ?>
  </form>
</div>

<?= activeFilterChip('tickets', ['status']) ?>

<div class="card">
  <?php
  $table = (new Table())
      ->emptyText('Geen tickets gevonden.')
      ->sortState($sort, $dir)
      ->rowUrl(fn (array $t) => "/tickets/{$t['id']}")
      ->column('id', '#', fn (array $t) => '<span class="mono" style="color:var(--color-text-tertiary)">#' . $t['id'] . '</span>', ['class' => 'col-1'])
      ->column('titel', 'Taak', fn (array $t) =>
          '<span class="text-truncate d-block" title="' . htmlspecialchars($t['titel']) . '">' . htmlspecialchars($t['titel']) . '</span>'
          . '<span class="text-truncate d-block" style="font-size:11px;color:var(--color-text-tertiary)">' . htmlspecialchars($t['opdrachtgever_naam']) . '</span>'
      )
      ->column('afdeling_naam', 'Afdeling', fn (array $t) => '<span class="text-truncate d-block" title="' . htmlspecialchars($t['afdeling_naam'] ?? '') . '">' . htmlspecialchars($t['afdeling_naam'] ?? '—') . '</span>', ['class' => 'col-2'])
      ->column('behandelaar_naam', 'Behandelaar', fn (array $t) => $t['behandelaar_naam']
          ? '<span style="display:flex;align-items:center;gap:6px"><span class="avatar-xs">' . htmlspecialchars(behandelaarInitialen($t['behandelaar_naam'])) . '</span>' . htmlspecialchars(explode(' ', $t['behandelaar_naam'])[0]) . '</span>'
          : '<span style="color:var(--color-text-tertiary)">—</span>', ['class' => 'col-2'])
      ->column('prioriteit', 'Prio', fn (array $t) => prioBadge($t['prioriteit']), ['class' => 'col-1'])
      ->column('status', 'Status', fn (array $t) => statusBadge($t['status']), ['class' => 'col-2'])
      ->column('deadline', 'Deadline', fn (array $t) => '<span style="color:var(--color-text-tertiary)">' . formatDatum($t['deadline']) . '</span>', ['class' => 'col-2'])
      ->rows($items);
  echo $table->render();
  ?>

  <div class="list-footer">
    <span><?= count($items) ?> van <?= (int) $pagination['total'] ?> tickets</span>
    <div style="display:flex;gap:4px">
      <?php if ($pagination['page'] > 1): $params = $_GET; $params['page'] = $pagination['page'] - 1; ?>
        <a class="btn btn-ghost" href="?<?= htmlspecialchars(http_build_query($params)) ?>">Vorige</a>
      <?php else: ?>
        <span class="btn btn-ghost" style="opacity:.4;pointer-events:none">Vorige</span>
      <?php endif; ?>
      <?php if ($pagination['page'] < $pagination['totalPages']): $params = $_GET; $params['page'] = $pagination['page'] + 1; ?>
        <a class="btn btn-ghost" href="?<?= htmlspecialchars(http_build_query($params)) ?>">Volgende</a>
      <?php else: ?>
        <span class="btn btn-ghost" style="opacity:.4;pointer-events:none">Volgende</span>
      <?php endif; ?>
    </div>
  </div>
</div>

<script>
(function () {
    var triggerBtn = document.getElementById('import-trigger-btn');
    var fileInput = document.getElementById('import-file-input');
    var form = document.getElementById('import-form');

    triggerBtn.addEventListener('click', function () {
        fileInput.click();
    });
    fileInput.addEventListener('change', function () {
        if (fileInput.files && fileInput.files.length > 0) {
            form.submit();
        }
    });

    var filtersBtn = document.getElementById('filtersToggleBtn');
    var filtersPanel = document.getElementById('filtersPanel');
    var filtersActief = <?= json_encode(($_GET['prioriteit'] ?? '') !== '' || ($_GET['afdeling_naam'] ?? '') !== '' || ($_GET['behandelaar_naam'] ?? '') !== '') ?>;
    if (filtersActief) {
        filtersPanel.classList.add('open');
    }
    filtersBtn.addEventListener('click', function () {
        filtersPanel.classList.toggle('open');
    });
})();
</script>
