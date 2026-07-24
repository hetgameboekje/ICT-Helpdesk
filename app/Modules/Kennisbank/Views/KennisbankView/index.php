<?php
/** @var array $items */
/** @var array $pagination */
/** @var string $search */
/** @var string|null $sort */
/** @var string $dir */
/** @var array $categorieBoom */
/** @var string $activeCategorie */
/** @var string $activeSubcategorie */
/** @var array $activeTags */
/** @var int $kpiAantalArtikelen */
/** @var int $kpiAantalCategorieen */
/** @var int $kpiConceptenInReview */
require_once APP_ROOT . '/app/Views/partials/ticket-helpers.php';

$flashSuccess = $_SESSION['flash_success'] ?? null;
$flashError = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$totaalAantal = array_sum(array_column($categorieBoom, 'aantal'));

function kbCategorieUrl(string $categorie, string $subcategorie = ''): string
{
    $params = ['categorie' => $categorie];
    if ($subcategorie !== '') {
        $params['subcategorie'] = $subcategorie;
    }
    return '/kennisbank?' . http_build_query($params);
}
?>
<div class="page-header">
  <div class="page-title">Kennisbank</div>
  <a class="btn btn-accent" href="/kennisbank/create">+ Nieuw artikel</a>
</div>

<?php if ($flashSuccess): ?>
  <div class="alert alert-success"><?= htmlspecialchars($flashSuccess) ?></div>
<?php endif; ?>
<?php if ($flashError): ?>
  <div class="alert alert-error"><?= htmlspecialchars($flashError) ?></div>
<?php endif; ?>

<div class="kpi-grid">
  <div class="kpi-card">
    <div class="kpi-card-head">
      <span class="kpi-label">Artikelen</span>
      <span class="kpi-icon kpi-icon-opgelost"><i class="bi bi-journal-bookmark"></i></span>
    </div>
    <div class="kpi-value"><?= (int) $kpiAantalArtikelen ?></div>
  </div>
  <a class="kpi-card" href="/email-verwerking/review">
    <div class="kpi-card-head">
      <span class="kpi-label">AI-concepten in review</span>
      <span class="kpi-icon kpi-icon-behandeling"><i class="bi bi-stars"></i></span>
    </div>
    <div class="kpi-value"><?= (int) $kpiConceptenInReview ?></div>
  </a>
  <div class="kpi-card">
    <div class="kpi-card-head">
      <span class="kpi-label">Categorieën</span>
      <span class="kpi-icon kpi-icon-neutral"><i class="bi bi-folder2"></i></span>
    </div>
    <div class="kpi-value"><?= (int) $kpiAantalCategorieen ?></div>
  </div>
</div>

<form method="get" action="/kennisbank" class="filters" style="margin-bottom:14px">
  <?php if ($activeCategorie !== ''): ?><input type="hidden" name="categorie" value="<?= htmlspecialchars($activeCategorie) ?>"><?php endif; ?>
  <?php if ($activeSubcategorie !== ''): ?><input type="hidden" name="subcategorie" value="<?= htmlspecialchars($activeSubcategorie) ?>"><?php endif; ?>
  <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Zoeken op titel...">
  <button class="btn" type="submit">Zoeken</button>

  <div class="kb-tag-filter" id="kb-tag-filter">
    <button type="button" class="btn" id="kb-tag-filter-btn">Tags<span id="kb-tag-filter-count"></span></button>
    <div class="kb-tag-filter-panel" id="kb-tag-filter-panel" hidden>
      <input type="text" id="kb-tag-filter-search" placeholder="Zoek tag..." autocomplete="off">
      <div class="kb-tag-filter-options" id="kb-tag-filter-options">
        <div class="kb-tag-filter-loading">Laden...</div>
      </div>
      <div class="kb-tag-filter-actions">
        <button type="button" class="btn" id="kb-tag-filter-clear">Wissen</button>
        <button type="submit" class="btn btn-primary" id="kb-tag-filter-apply">Toepassen</button>
      </div>
    </div>
  </div>
</form>

<?= activeFilterChip('kennisbank', ['categorie', 'subcategorie']) ?>

<div class="detail-layout" style="grid-template-columns:260px 1fr">
  <div class="card kb-nav-card">
    <div class="card-header"><span class="card-title">Categorieën</span></div>
    <div class="list-group list-group-flush" id="kb-categorie-lijst">
      <a href="/kennisbank" class="list-group-item list-group-item-action kb-nav-item<?= $activeCategorie === '' ? ' active' : '' ?>">
        <span>Alle artikelen</span>
        <span class="badge kb-nav-count"><?= $totaalAantal ?></span>
      </a>
      <?php foreach ($categorieBoom as $cat): ?>
        <a href="<?= htmlspecialchars(kbCategorieUrl($cat['naam'])) ?>"
           class="list-group-item list-group-item-action kb-nav-item<?= $activeCategorie === $cat['naam'] ? ' active' : '' ?>">
          <span><?= htmlspecialchars($cat['naam']) ?></span>
          <span class="badge kb-nav-count"><?= $cat['aantal'] ?></span>
        </a>
        <?php if ($activeCategorie === $cat['naam'] && !empty($cat['subcategorieen'])): ?>
          <div class="kb-nav-subs">
            <?php foreach ($cat['subcategorieen'] as $sub): ?>
              <a href="<?= htmlspecialchars(kbCategorieUrl($cat['naam'], $sub['naam'])) ?>"
                 class="list-group-item list-group-item-action kb-nav-item kb-nav-subitem<?= $activeSubcategorie === $sub['naam'] ? ' active' : '' ?>">
                <span><?= htmlspecialchars($sub['naam']) ?></span>
                <span class="badge kb-nav-count"><?= $sub['aantal'] ?></span>
              </a>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="card">
    <div class="card-header">
      <span class="card-title">
        <?= htmlspecialchars($activeSubcategorie !== '' ? $activeSubcategorie : ($activeCategorie !== '' ? $activeCategorie : 'Alle artikelen')) ?>
      </span>
    </div>

    <?php if (empty($items)): ?>
      <div class="empty-state">Geen artikelen gevonden.</div>
    <?php else: ?>
      <div class="list-group list-group-flush" id="kb-artikel-lijst">
        <?php foreach ($items as $item): ?>
          <div class="list-group-item kb-artikel-item" style="cursor:pointer"
               onclick="if (!event.target.closest('a,button')) window.location='/kennisbank/<?= (int) $item['id'] ?>'">
            <div class="d-flex justify-content-between align-items-start" style="gap:12px">
              <div style="min-width:0">
                <div class="kb-artikel-titel"><?= htmlspecialchars($item['titel']) ?></div>
                <?php if (!empty($item['samenvatting'])): ?>
                  <div class="kb-artikel-samenvatting"><?= htmlspecialchars($item['samenvatting']) ?></div>
                <?php endif; ?>
                <?php $tags = \App\Modules\Kennisbank\Models\KennisbankModel::splitTags($item['tags'] ?? null); ?>
                <?php if (!empty($tags)): ?>
                  <div class="kb-tags">
                    <?php foreach ($tags as $tag): ?>
                      <a href="/kennisbank?tag=<?= urlencode($tag) ?>" class="badge kb-tag"><?= htmlspecialchars($tag) ?></a>
                    <?php endforeach; ?>
                  </div>
                <?php endif; ?>
              </div>
              <div class="kb-artikel-meta text-nowrap">
                <div><?= htmlspecialchars($item['categorie']) ?></div>
                <small class="text-muted"><?= htmlspecialchars($item['auteur_naam'] ?? '—') ?></small>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
      <?= paginationLinks($pagination) ?>
    <?php endif; ?>
  </div>
</div>

<style>
.kb-nav-item{display:flex;align-items:center;justify-content:space-between;gap:8px;font-size:13.5px}
.kb-nav-item.active{background:var(--color-background-info);color:var(--color-text-info);font-weight:600}
.kb-nav-count{background:var(--color-background-tertiary);color:var(--color-text-secondary)}
.kb-nav-item.active .kb-nav-count{background:transparent;color:inherit}
.kb-nav-subs{background:var(--color-background-secondary)}
.kb-nav-subitem{padding-left:28px;font-size:12.5px;color:var(--color-text-secondary)}
.kb-artikel-titel{font-weight:600;font-size:13.5px;color:var(--color-text-primary)}
.kb-artikel-samenvatting{font-size:12.5px;color:var(--color-text-secondary);margin-top:2px}
.kb-artikel-meta{font-size:12px;color:var(--color-text-secondary);text-align:right}
.kb-artikel-item:hover{background:var(--color-background-secondary)}
.kb-tags{margin-top:6px;display:flex;flex-wrap:wrap;gap:4px}
.kb-tag{background:var(--color-background-tertiary);color:var(--color-text-secondary);font-weight:400;text-decoration:none}
.kb-tag:hover{background:var(--color-background-info);color:var(--color-text-info)}
@media (max-width:800px){.detail-layout{grid-template-columns:1fr !important}}

.kb-tag-filter{position:relative}
.kb-tag-filter-panel{position:absolute;top:calc(100% + 4px);left:0;z-index:20;width:260px;background:var(--color-background-primary);border:1px solid var(--color-border);border-radius:6px;box-shadow:0 4px 12px rgba(0,0,0,.15);padding:10px;display:flex;flex-direction:column;gap:8px}
.kb-tag-filter-panel input[type="text"]{width:100%}
.kb-tag-filter-options{max-height:220px;overflow-y:auto;display:flex;flex-direction:column;gap:2px}
.kb-tag-filter-option{display:flex;align-items:center;gap:6px;font-size:13px;padding:3px 4px;border-radius:4px;cursor:pointer}
.kb-tag-filter-option:hover{background:var(--color-background-secondary)}
.kb-tag-filter-option input{margin:0}
.kb-tag-filter-loading,.kb-tag-filter-empty{font-size:12.5px;color:var(--color-text-secondary);padding:4px}
.kb-tag-filter-actions{display:flex;justify-content:space-between;gap:8px}
#kb-tag-filter-count{color:var(--color-text-secondary)}
</style>

<script>
(function () {
    var activeTags = <?= json_encode(array_map('mb_strtolower', $activeTags)) ?>;

    var wrapper = document.getElementById('kb-tag-filter');
    var btn = document.getElementById('kb-tag-filter-btn');
    var panel = document.getElementById('kb-tag-filter-panel');
    var optionsEl = document.getElementById('kb-tag-filter-options');
    var searchInput = document.getElementById('kb-tag-filter-search');
    var countEl = document.getElementById('kb-tag-filter-count');
    var clearBtn = document.getElementById('kb-tag-filter-clear');

    function updateCount() {
        var checked = optionsEl.querySelectorAll('input[type="checkbox"]:checked').length;
        countEl.textContent = checked > 0 ? ' (' + checked + ')' : '';
    }

    fetch('/kennisbank/tags')
        .then(function (r) { return r.json(); })
        .then(function (tags) {
            optionsEl.innerHTML = '';
            if (tags.length === 0) {
                optionsEl.innerHTML = '<div class="kb-tag-filter-empty">Geen tags gevonden.</div>';
                return;
            }
            tags.forEach(function (tag) {
                var label = document.createElement('label');
                label.className = 'kb-tag-filter-option';

                var checkbox = document.createElement('input');
                checkbox.type = 'checkbox';
                checkbox.name = 'tag[]';
                checkbox.value = tag;
                checkbox.checked = activeTags.indexOf(tag.toLowerCase()) !== -1;
                checkbox.addEventListener('change', updateCount);

                label.appendChild(checkbox);
                label.appendChild(document.createTextNode(' ' + tag));
                optionsEl.appendChild(label);
            });
            updateCount();
        });

    btn.addEventListener('click', function (e) {
        e.stopPropagation();
        panel.hidden = !panel.hidden;
    });

    document.addEventListener('click', function (e) {
        if (!panel.hidden && !wrapper.contains(e.target)) {
            panel.hidden = true;
        }
    });

    searchInput.addEventListener('input', function () {
        var q = searchInput.value.trim().toLowerCase();
        optionsEl.querySelectorAll('.kb-tag-filter-option').forEach(function (opt) {
            opt.style.display = opt.textContent.trim().toLowerCase().indexOf(q) === -1 ? 'none' : '';
        });
    });

    clearBtn.addEventListener('click', function () {
        optionsEl.querySelectorAll('input[type="checkbox"]').forEach(function (c) { c.checked = false; });
        updateCount();
    });
})();
</script>
