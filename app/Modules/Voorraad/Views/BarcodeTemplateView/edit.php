<?php
/** @var array $template */
/** @var array $types */
?>
<div class="page-header">
  <div style="display:flex;align-items:center;gap:12px">
    <a class="btn" href="/voorraad/barcode-templates" style="padding:6px 10px">&larr;</a>
    <div class="page-title">Sjabloon bewerken</div>
  </div>
</div>

<div class="card">
  <form method="post" action="/voorraad/barcode-templates/<?= $template['id'] ?>" style="padding:16px">
    <div class="form-grid">
      <div class="form-group">
        <label class="form-label">Naam</label>
        <input type="text" name="naam" value="<?= htmlspecialchars($template['naam']) ?>" required>
      </div>
      <div class="form-group">
        <label class="form-label">Patroon (regex)</label>
        <input type="text" name="patroon" class="mono" value="<?= htmlspecialchars($template['patroon']) ?>" required>
      </div>
      <div class="form-group">
        <label class="form-label">Voorgesteld type</label>
        <select name="voorraad_type_id">
          <option value="">— geen (alleen herkennen, gebruiker kiest zelf) —</option>
          <?php foreach ($types as $t): ?>
            <option value="<?= $t['id'] ?>" <?= (int) ($template['voorraad_type_id'] ?? 0) === (int) $t['id'] ? 'selected' : '' ?>><?= htmlspecialchars($t['naam']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">Volgorde</label>
        <input type="number" name="volgorde" value="<?= (int) $template['volgorde'] ?>" step="1">
      </div>
      <div class="form-group" style="grid-column:1/-1">
        <label class="form-label">Omschrijving</label>
        <input type="text" name="omschrijving" value="<?= htmlspecialchars($template['omschrijving'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label class="form-label"><input type="checkbox" name="actief" value="1" <?= $template['actief'] ? 'checked' : '' ?>> Actief</label>
      </div>
    </div>
    <div style="display:flex;gap:8px;margin-top:8px">
      <button class="btn btn-primary" type="submit">Opslaan</button>
      <a class="btn" href="/voorraad/barcode-templates">Annuleren</a>
    </div>
  </form>
</div>
