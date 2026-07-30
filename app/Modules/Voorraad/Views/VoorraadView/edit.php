<?php
/** @var array $item */
/** @var array $types */
?>
<div class="page-header">
  <div style="display:flex;align-items:center;gap:12px">
    <a class="btn" href="/voorraad/<?= $item['id'] ?>" style="padding:6px 10px">&larr;</a>
    <div class="page-title">Item #<?= $item['id'] ?> bewerken</div>
  </div>
</div>

<div class="card">
  <form class="new-form" method="post" action="/voorraad/<?= $item['id'] ?>" enctype="multipart/form-data">
    <div class="form-grid">
      <div class="form-group">
        <label class="form-label">Type</label>
        <select name="type_id" required>
          <option value="">— Kies type —</option>
          <?php foreach ($types as $t): ?>
            <option value="<?= $t['id'] ?>" <?= (int) $item['type_id'] === (int) $t['id'] ? 'selected' : '' ?>><?= htmlspecialchars($t['naam']) ?> (<?= htmlspecialchars($t['code']) ?>)</option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">Variant (bv. lengte: 3m, 5m)</label>
        <input type="text" name="variant" value="<?= htmlspecialchars($item['variant'] ?? '') ?>" placeholder="optioneel">
      </div>
      <div class="form-group">
        <label class="form-label">Serienummer</label>
        <input type="text" name="serienummer" value="<?= htmlspecialchars($item['serienummer'] ?? '') ?>" placeholder="optioneel — laat leeg bij kabels e.d.">
      </div>
      <div class="form-group">
        <label class="form-label">Product-ID</label>
        <input type="text" name="product_id" value="<?= htmlspecialchars($item['product_id'] ?? '') ?>" placeholder="optioneel">
      </div>
      <div class="form-group">
        <label class="form-label">Locatie</label>
        <input type="text" name="locatie" value="<?= htmlspecialchars($item['locatie'] ?? '') ?>" placeholder="bv. Serverroom schap 3">
      </div>
      <div class="form-group" style="grid-column:1/-1">
        <label class="form-label">Opmerking</label>
        <textarea name="opmerking"><?= htmlspecialchars($item['opmerking'] ?? '') ?></textarea>
      </div>
      <div class="form-group" style="grid-column:1/-1">
        <label class="form-label">DxDiag-rapport (optioneel, .txt)</label>
        <input type="file" name="dxdiag_bestand" accept=".txt">
        <p style="font-size:12px;color:var(--color-text-secondary);margin:4px 0 0">
          <?= !empty($item['specificaties']) ? 'Er staan al specificaties bij dit item — upload een nieuw bestand om ze te vervangen.' : 'Upload het .txt-bestand van "DxDiag" (Windows: dxdiag.exe &rarr; Alles opslaan) om systeem- en videokaartgegevens beknopt bij dit item op te slaan.' ?>
        </p>
      </div>
    </div>
    <p style="font-size:12px;color:var(--color-text-secondary);margin-bottom:12px">
      Huidige barcode: <strong><?= htmlspecialchars($item['barcode']) ?></strong>.
      Als je type, variant of serienummer wijzigt, wordt de barcode automatisch opnieuw berekend — print in dat geval een nieuw label.
      De status (op voorraad / uitgegeven) wijzig je via de Uitgifte-module, niet hier.
    </p>
    <div style="display:flex;gap:8px;margin-top:8px">
      <button class="btn btn-primary" type="submit">Opslaan</button>
      <a class="btn" href="/voorraad/<?= $item['id'] ?>">Annuleren</a>
    </div>
  </form>
</div>
