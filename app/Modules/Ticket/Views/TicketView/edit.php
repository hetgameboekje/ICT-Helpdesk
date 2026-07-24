<?php
/** @var array $item */
/** @var array $afdelingen */
/** @var array $gebruikers */
?>
<div class="page-header">
  <div style="display:flex;align-items:center;gap:12px">
    <a class="btn" href="/tickets/<?= $item['id'] ?>" style="padding:6px 10px">&larr;</a>
    <div class="page-title">Ticket #<?= $item['id'] ?> bewerken</div>
  </div>
</div>

<div class="card">
  <form class="new-form" method="post" action="/tickets/<?= $item['id'] ?>">
    <div class="form-grid">
      <div class="form-group"><label class="form-label">Opdrachtgever</label><input type="text" name="opdrachtgever_naam" value="<?= htmlspecialchars($item['opdrachtgever_naam']) ?>" required></div>
      <div class="form-group">
        <label class="form-label">Afdeling</label>
        <select name="afdeling_id">
          <option value="">— Selecteer afdeling —</option>
          <?php foreach ($afdelingen as $a): ?>
            <option value="<?= $a['id'] ?>" <?= $item['afdeling_id'] == $a['id'] ? 'selected' : '' ?>><?= htmlspecialchars($a['naam']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group" style="grid-column:1/-1"><label class="form-label">Taak (korte titel)</label><input type="text" name="titel" value="<?= htmlspecialchars($item['titel']) ?>" required></div>
      <div class="form-group" style="grid-column:1/-1"><label class="form-label">Omschrijving</label><textarea name="omschrijving" style="min-height:100px"><?= htmlspecialchars($item['omschrijving']) ?></textarea></div>
      <div class="form-group">
        <label class="form-label">Categorie</label>
        <input type="text" name="categorie" id="categorie-input" list="categorie-opties" value="<?= htmlspecialchars($item['categorie'] ?? 'Algemeen') ?>" autocomplete="off">
        <datalist id="categorie-opties"></datalist>
      </div>
      <div class="form-group">
        <label class="form-label">Prioriteit</label>
        <select name="prioriteit">
          <?php foreach (['laag' => 'Laag', 'normaal' => 'Normaal', 'hoog' => 'Hoog', 'kritiek' => 'Kritiek'] as $val => $label): ?>
            <option value="<?= $val ?>" <?= $item['prioriteit'] === $val ? 'selected' : '' ?>><?= $label ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group"><label class="form-label">Impact</label><input type="text" name="impact" value="<?= htmlspecialchars($item['impact']) ?>"></div>
      <div class="form-group"><label class="form-label">Schatting (minuten)</label><input type="number" step="1" name="schatting_minuten" value="<?= htmlspecialchars((string) ($item['schatting_minuten'] ?? '')) ?>"></div>
      <div class="form-group"><label class="form-label">Deadline</label><input type="date" name="deadline" value="<?= htmlspecialchars($item['deadline'] ?? '') ?>"></div>
      <div class="form-group">
        <label class="form-label"><input type="checkbox" name="is_cyberrisico" value="1" <?= !empty($item['is_cyberrisico']) ? 'checked' : '' ?>> Cyber risico</label>
      </div>
      <div class="form-group">
        <label class="form-label">Behandelaar</label>
        <select name="behandelaar_id">
          <option value="">— Niet toegewezen —</option>
          <?php foreach ($gebruikers as $g): ?>
            <option value="<?= $g['id'] ?>" <?= $item['behandelaar_id'] == $g['id'] ? 'selected' : '' ?>><?= htmlspecialchars($g['naam']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <div style="display:flex;gap:8px;margin-top:8px">
      <button class="btn btn-accent" type="submit">Opslaan</button>
      <a class="btn" href="/tickets/<?= $item['id'] ?>">Annuleren</a>
    </div>
  </form>
</div>

<script>
(function () {
    var input = document.getElementById('categorie-input');
    var list = document.getElementById('categorie-opties');
    var timer = null;

    input.addEventListener('input', function () {
        clearTimeout(timer);
        var q = input.value.trim();
        if (q.length < 2) {
            list.innerHTML = '';
            return;
        }
        timer = setTimeout(function () {
            fetch('/tickets/categorieen?q=' + encodeURIComponent(q))
                .then(function (r) { return r.json(); })
                .then(function (categorieen) {
                    list.innerHTML = '';
                    categorieen.forEach(function (c) {
                        var opt = document.createElement('option');
                        opt.value = c;
                        list.appendChild(opt);
                    });
                });
        }, 200);
    });
})();
</script>
