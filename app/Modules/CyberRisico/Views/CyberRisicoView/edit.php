<?php
/** @var array $item */
/** @var array $gebruikers */
/** @var array $afdelingen */
/** @var array $categorieen */
/** @var array $prioriteiten */
/** @var array $statussen */
?>
<div class="page-header">
  <div style="display:flex;align-items:center;gap:12px">
    <a class="btn" href="/cyberrisicos/<?= $item['id'] ?>" style="padding:6px 10px">&larr;</a>
    <div class="page-title">Risico #<?= $item['id'] ?> bewerken</div>
  </div>
</div>

<div class="card">
  <form class="new-form" method="post" action="/cyberrisicos/<?= $item['id'] ?>">
    <div class="form-grid">
      <div class="form-group" style="grid-column:1/-1">
        <label class="form-label">Titel</label>
        <input type="text" name="titel" value="<?= htmlspecialchars($item['titel']) ?>" required>
      </div>
      <div class="form-group" style="grid-column:1/-1">
        <label class="form-label">Omschrijving</label>
        <textarea name="omschrijving" style="min-height:100px" required><?= htmlspecialchars($item['omschrijving']) ?></textarea>
      </div>
      <div class="form-group">
        <label class="form-label">Type risico</label>
        <select name="categorie">
          <?php foreach ($categorieen as $val => $label): ?>
            <option value="<?= $val ?>" <?= $item['categorie'] === $val ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">Kans (1 = onwaarschijnlijk, 5 = zeer waarschijnlijk)</label>
        <select name="kans">
          <?php for ($k = 1; $k <= 5; $k++): ?>
            <option value="<?= $k ?>" <?= (int) ($item['kans'] ?? 3) === $k ? 'selected' : '' ?>><?= $k ?></option>
          <?php endfor; ?>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">Impact (1 = verwaarloosbaar, 5 = zeer ernstig)</label>
        <select name="impact">
          <?php for ($i = 1; $i <= 5; $i++): ?>
            <option value="<?= $i ?>" <?= (int) ($item['impact'] ?? 3) === $i ? 'selected' : '' ?>><?= $i ?></option>
          <?php endfor; ?>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">Status</label>
        <select name="status">
          <?php foreach ($statussen as $val => $label): ?>
            <option value="<?= $val ?>" <?= $item['status'] === $val ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">Locatie</label>
        <input type="text" name="locatie" value="<?= htmlspecialchars($item['locatie'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label class="form-label">Gemeld door</label>
        <input type="text" name="gemeld_door" value="<?= htmlspecialchars($item['gemeld_door'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label class="form-label">Eigenaar (verantwoordelijk voor opvolging)</label>
        <select name="eigenaar_id">
          <option value="">— Niet toegewezen —</option>
          <?php foreach ($gebruikers as $g): ?>
            <option value="<?= $g['id'] ?>" <?= (int) ($item['eigenaar_id'] ?? 0) === (int) $g['id'] ? 'selected' : '' ?>><?= htmlspecialchars($g['naam']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">Afdeling</label>
        <select name="afdeling_id">
          <option value="">— Geen afdeling —</option>
          <?php foreach ($afdelingen as $a): ?>
            <option value="<?= $a['id'] ?>" <?= (int) ($item['afdeling_id'] ?? 0) === (int) $a['id'] ? 'selected' : '' ?>><?= htmlspecialchars($a['naam']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">Datum geconstateerd</label>
        <input type="date" name="datum_geconstateerd" value="<?= htmlspecialchars(substr((string) ($item['datum_geconstateerd'] ?? ''), 0, 10)) ?>">
      </div>
      <div class="form-group">
        <label class="form-label">Datum gemeld</label>
        <input type="date" name="datum_gemeld" value="<?= htmlspecialchars(substr((string) ($item['datum_gemeld'] ?? ''), 0, 10)) ?>">
      </div>
      <div class="form-group" style="grid-column:1/-1">
        <label class="form-label">Oplossingsadvies</label>
        <textarea name="oplossingsadvies"><?= htmlspecialchars($item['oplossingsadvies'] ?? '') ?></textarea>
      </div>
      <div class="form-group" style="grid-column:1/-1">
        <label class="form-label">Bewijs / notities</label>
        <textarea name="bewijs_notities"><?= htmlspecialchars($item['bewijs_notities'] ?? '') ?></textarea>
      </div>
      <div class="form-group" style="grid-column:1/-1">
        <label style="display:flex;align-items:center;gap:8px;font-size:13px;font-weight:normal">
          <input type="checkbox" name="is_gevoelig" value="1" style="width:auto" <?= !empty($item['is_gevoelig']) ? 'checked' : '' ?>>
          Bevat gevoelige informatie
        </label>
      </div>
    </div>
    <div style="display:flex;gap:8px;margin-top:8px">
      <button class="btn btn-primary" type="submit">Opslaan</button>
      <a class="btn" href="/cyberrisicos/<?= $item['id'] ?>">Annuleren</a>
    </div>
  </form>
</div>
