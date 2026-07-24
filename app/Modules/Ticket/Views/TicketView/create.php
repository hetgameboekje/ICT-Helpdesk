<?php
/** @var array $afdelingen */
/** @var array $gebruikers */
?>
<div class="page-header">
  <div style="display:flex;align-items:center;gap:12px">
    <a class="btn" href="/tickets" style="padding:6px 10px">&larr;</a>
    <div class="page-title">Nieuw ticket</div>
  </div>
</div>

<div class="card">
  <form class="new-form" method="post" action="/tickets">
    <div class="form-grid">
      <div class="form-group"><label class="form-label">Opdrachtgever</label><input type="text" name="opdrachtgever_naam" required></div>
      <div class="form-group">
        <label class="form-label">Afdeling</label>
        <select name="afdeling_id">
          <option value="">— Selecteer afdeling —</option>
          <?php foreach ($afdelingen as $a): ?>
            <option value="<?= $a['id'] ?>"><?= htmlspecialchars($a['naam']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group" style="grid-column:1/-1"><label class="form-label">Taak (korte titel)</label><input type="text" name="titel" required></div>
      <div class="form-group" style="grid-column:1/-1"><label class="form-label">Omschrijving</label><textarea name="omschrijving" style="min-height:100px"></textarea></div>
      <div class="form-group">
        <label class="form-label">Categorie</label>
        <input type="text" name="categorie" id="categorie-input" list="categorie-opties" placeholder="bijv. Printers, Netwerk, Accounts" value="Algemeen" autocomplete="off">
        <datalist id="categorie-opties"></datalist>
      </div>
      <div class="form-group">
        <label class="form-label">Prioriteit</label>
        <select name="prioriteit">
          <option value="laag">Laag</option>
          <option value="normaal" selected>Normaal</option>
          <option value="hoog">Hoog</option>
          <option value="kritiek">Kritiek</option>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">Impact</label>
        <select name="impact">
          <option>Laag</option>
          <option selected>Normaal</option>
          <option>Hoog — afdeling</option>
          <option>Kritiek — productie</option>
        </select>
      </div>
      <div class="form-group"><label class="form-label">Schatting (minuten)</label><input type="number" step="1" name="schatting_minuten"></div>
      <div class="form-group"><label class="form-label">Deadline</label><input type="date" name="deadline"></div>
      <div class="form-group">
        <label class="form-label"><input type="checkbox" name="is_cyberrisico" value="1"> Cyber risico</label>
      </div>
      <div class="form-group">
        <label class="form-label">Behandelaar</label>
        <select name="behandelaar_id">
          <option value="">— Niet toegewezen —</option>
          <?php foreach ($gebruikers as $g): ?>
            <option value="<?= $g['id'] ?>"><?= htmlspecialchars($g['naam']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <div style="display:flex;gap:8px;margin-top:8px">
      <button class="btn btn-accent" type="submit">Ticket aanmaken</button>
      <a class="btn" href="/tickets">Annuleren</a>
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
