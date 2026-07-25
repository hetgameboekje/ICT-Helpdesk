<?php
/** @var array $gebruikers */
/** @var array $afdelingen */
/** @var array $categorieen */
/** @var array $prioriteiten */
?>
<div class="page-header">
  <div style="display:flex;align-items:center;gap:12px">
    <a class="btn" href="/cyberrisicos" style="padding:6px 10px">&larr;</a>
    <div class="page-title">Risico melden</div>
  </div>
</div>

<div class="card">
  <form class="new-form" method="post" action="/cyberrisicos">
    <div class="form-grid">
      <div class="form-group" style="grid-column:1/-1">
        <label class="form-label">Titel</label>
        <input type="text" name="titel" required placeholder="bv. Sticky note met wachtwoord onder toetsenbord">
      </div>
      <div class="form-group" style="grid-column:1/-1">
        <label class="form-label">Omschrijving</label>
        <textarea name="omschrijving" style="min-height:100px" required></textarea>
      </div>
      <div class="form-group">
        <label class="form-label">Type risico</label>
        <select name="categorie">
          <?php foreach ($categorieen as $val => $label): ?>
            <option value="<?= $val ?>"><?= htmlspecialchars($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">Kans (1 = onwaarschijnlijk, 5 = zeer waarschijnlijk)</label>
        <select name="kans">
          <?php for ($k = 1; $k <= 5; $k++): ?>
            <option value="<?= $k ?>" <?= $k === 3 ? 'selected' : '' ?>><?= $k ?></option>
          <?php endfor; ?>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">Impact (1 = verwaarloosbaar, 5 = zeer ernstig)</label>
        <select name="impact">
          <?php for ($i = 1; $i <= 5; $i++): ?>
            <option value="<?= $i ?>" <?= $i === 3 ? 'selected' : '' ?>><?= $i ?></option>
          <?php endfor; ?>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">Locatie</label>
        <input type="text" name="locatie" placeholder="bv. Serverroom, receptie, kantoor 2e verdieping">
      </div>
      <div class="form-group">
        <label class="form-label">Gemeld door</label>
        <input type="text" name="gemeld_door" placeholder="Naam van de melder">
      </div>
      <div class="form-group">
        <label class="form-label">Eigenaar (verantwoordelijk voor opvolging)</label>
        <select name="eigenaar_id">
          <option value="">— Niet toegewezen —</option>
          <?php foreach ($gebruikers as $g): ?>
            <option value="<?= $g['id'] ?>"><?= htmlspecialchars($g['naam']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">Afdeling</label>
        <select name="afdeling_id">
          <option value="">— Geen afdeling —</option>
          <?php foreach ($afdelingen as $a): ?>
            <option value="<?= $a['id'] ?>"><?= htmlspecialchars($a['naam']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">Datum geconstateerd</label>
        <input type="date" name="datum_geconstateerd">
      </div>
      <div class="form-group">
        <label class="form-label">Datum gemeld</label>
        <input type="date" name="datum_gemeld" value="<?= date('Y-m-d') ?>">
      </div>
      <div class="form-group" style="grid-column:1/-1">
        <label class="form-label">Oplossingsadvies</label>
        <textarea name="oplossingsadvies" placeholder="Wat moet er gebeuren om dit risico weg te nemen?"></textarea>
      </div>
      <div class="form-group" style="grid-column:1/-1">
        <label class="form-label">Bewijs / notities</label>
        <textarea name="bewijs_notities" placeholder="Waar/wanneer geconstateerd, foto-locatie, extra context..."></textarea>
      </div>
      <div class="form-group" style="grid-column:1/-1">
        <label style="display:flex;align-items:center;gap:8px;font-size:13px;font-weight:normal">
          <input type="checkbox" name="is_gevoelig" value="1" style="width:auto">
          Bevat gevoelige informatie (bijv. echte wachtwoorden, credentials) — wees terughoudend met details hierboven
        </label>
      </div>
    </div>
    <div style="display:flex;gap:8px;margin-top:8px">
      <button class="btn btn-primary" type="submit">Risico registreren</button>
      <a class="btn" href="/cyberrisicos">Annuleren</a>
    </div>
  </form>
</div>
