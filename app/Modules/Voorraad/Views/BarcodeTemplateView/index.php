<?php
/** @var array $templates */
/** @var array $types */

$flashSuccess = $_SESSION['flash_success'] ?? null;
$flashError = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);
?>
<div class="page-header">
  <div style="display:flex;align-items:center;gap:12px">
    <a class="btn" href="/voorraad" style="padding:6px 10px">&larr;</a>
    <div class="page-title">Barcode-sjablonen</div>
  </div>
</div>

<?php if ($flashSuccess): ?>
  <div class="alert alert-success"><?= htmlspecialchars($flashSuccess) ?></div>
<?php endif; ?>
<?php if ($flashError): ?>
  <div class="alert alert-error"><?= htmlspecialchars($flashError) ?></div>
<?php endif; ?>

<div class="card" style="margin-bottom:14px">
  <div class="card-header"><span class="card-title">Nieuw sjabloon toevoegen</span></div>
  <div style="padding:16px">
    <p style="font-size:13px;color:var(--color-text-secondary);margin-top:0">
      Een barcode-sjabloon herkent een <strong>kale scan zonder komma</strong> (dus geen "serienummer,product-ID"-
      formaat) aan de vorm van de tekst, en stelt daar een voorraadtype + omschrijving bij voor — bv. een
      fabrikant-eigen toetsenbordserienummer of een EAN-barcode van een monitor. Het patroon is een reguliere
      expressie (zonder de buitenste <code>/../</code>) die tegen de hele gescande tekst wordt getest.
      Sjablonen met een lager volgnummer worden eerst geprobeerd — <strong>let op overlap</strong>: een patroon
      dat ook cijfers toestaat (bv. <code>[A-Z0-9]</code>) matcht per ongeluk óók een kale EAN-barcode
      (alleen cijfers) als dat sjabloon een lager volgnummer heeft. Gebruik zo nodig <code>(?=.*[A-Z])</code>
      vooraan om minstens één letter af te dwingen, zoals de "Snel invullen"-knop hieronder al doet.
    </p>
    <div style="display:flex;gap:8px;margin-bottom:12px">
      <button type="button" class="btn" id="btSnelToetsenbord">Snel invullen: HP-toetsenbord</button>
      <button type="button" class="btn" id="btSnelMonitor">Snel invullen: EAN-monitorbarcode</button>
    </div>
    <form method="post" action="/voorraad/barcode-templates" id="btForm">
      <div class="form-grid">
        <div class="form-group">
          <label class="form-label">Naam</label>
          <input type="text" name="naam" id="btNaam" placeholder="bv. HP-toetsenbord (14-karakter serienummer)" required>
        </div>
        <div class="form-group">
          <label class="form-label">Patroon (regex)</label>
          <input type="text" name="patroon" id="btPatroon" class="mono" placeholder="bv. ^[A-Z0-9]{12,16}$" required>
        </div>
        <div class="form-group">
          <label class="form-label">Voorgesteld type</label>
          <select name="voorraad_type_id" id="btType">
            <option value="">— geen (alleen herkennen, gebruiker kiest zelf) —</option>
            <?php foreach ($types as $t): ?>
              <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['naam']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Volgorde</label>
          <input type="number" name="volgorde" id="btVolgorde" value="0" step="1">
        </div>
        <div class="form-group" style="grid-column:1/-1">
          <label class="form-label">Omschrijving (optioneel, getoond in de scan-suggestie)</label>
          <input type="text" name="omschrijving" id="btOmschrijving" placeholder="bv. HP-toetsenbord, herkend op serienummerformaat">
        </div>
        <div class="form-group">
          <label class="form-label"><input type="checkbox" name="actief" value="1" checked> Actief</label>
        </div>
      </div>
      <button class="btn btn-primary" type="submit">Toevoegen</button>
    </form>
  </div>
</div>

<div class="card">
  <div class="card-header"><span class="card-title">Bestaande sjablonen (<?= count($templates) ?>)</span></div>
  <?php if (empty($templates)): ?>
    <div class="empty-state">Nog geen barcode-sjablonen toegevoegd.</div>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead>
          <tr><th>Volgorde</th><th>Naam</th><th>Patroon</th><th>Type</th><th>Actief</th><th></th></tr>
        </thead>
        <tbody>
          <?php foreach ($templates as $t): ?>
          <tr>
            <td><?= (int) $t['volgorde'] ?></td>
            <td><?= htmlspecialchars($t['naam']) ?></td>
            <td class="mono" style="font-size:12px"><?= htmlspecialchars($t['patroon']) ?></td>
            <td><?= htmlspecialchars($t['voorraad_type_naam'] ?? '—') ?></td>
            <td><?= $t['actief'] ? 'Ja' : 'Nee' ?></td>
            <td style="white-space:nowrap">
              <a class="btn" style="padding:4px 8px" href="/voorraad/barcode-templates/<?= $t['id'] ?>/edit">Bewerken</a>
              <form method="post" action="/voorraad/barcode-templates/<?= $t['id'] ?>/verwijderen" style="display:inline" onsubmit="return confirm('Sjabloon &quot;<?= htmlspecialchars($t['naam'], ENT_QUOTES) ?>&quot; verwijderen?')">
                <button class="btn" style="padding:4px 8px" type="submit">Verwijderen</button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<script>
(function () {
    function vulIn(naam, patroon, omschrijving) {
        document.getElementById('btNaam').value = naam;
        document.getElementById('btPatroon').value = patroon;
        document.getElementById('btOmschrijving').value = omschrijving;
        document.getElementById('btForm').scrollIntoView({ behavior: 'smooth' });
    }

    document.getElementById('btSnelToetsenbord').addEventListener('click', function () {
        // (?=.*[A-Z]) dwingt minstens één letter af, anders zou dit patroon óók een kale
        // EAN-barcode (alleen cijfers) matchen — zie de toelichting hierboven over volgorde/overlap.
        vulIn('HP-toetsenbord (14-karakter serienummer)', '^(?=.*[A-Z])[A-Z0-9]{12,16}$', 'HP-toetsenbord, herkend op serienummerformaat');
    });
    document.getElementById('btSnelMonitor').addEventListener('click', function () {
        vulIn('EAN-monitorbarcode (bv. iiyama)', '^\\d{12,14}$', 'Beeldscherm, herkend op EAN-barcode');
    });
})();
</script>
