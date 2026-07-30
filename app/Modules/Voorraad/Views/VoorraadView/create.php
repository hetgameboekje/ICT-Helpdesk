<?php /** @var array $types */ ?>
<div class="page-header">
  <div style="display:flex;align-items:center;gap:12px">
    <a class="btn" href="/voorraad" style="padding:6px 10px">&larr;</a>
    <div class="page-title">Item toevoegen aan voorraad</div>
  </div>
</div>

<div class="card">
  <form class="new-form" method="post" action="/voorraad" enctype="multipart/form-data">
    <div class="form-group" style="margin-bottom:16px">
      <label class="form-label">Scan apparaat (optioneel — scan/plak het fabrieks-assetlabel)</label>
      <input type="text" id="scanInput" autocomplete="off" placeholder="bv. 1H86265279,E08ZGET#ABH">
      <p style="font-size:12px;color:var(--color-text-secondary);margin:4px 0 0">
        Herkent automatisch serienummer + product-ID (en eventuele omschrijving) uit een gescand
        assetlabel en zoekt of dit apparaat al bekend is, o.a. via de NinjaOne/RMM-import.
      </p>
      <div id="scanSuggestie" style="display:none;margin-top:8px"></div>
    </div>

    <div class="form-grid">
      <div class="form-group">
        <label class="form-label">Type</label>
        <select name="type_id" id="typeSelect" required>
          <option value="">— Kies type —</option>
          <?php foreach ($types as $t): ?>
            <option value="<?= $t['id'] ?>" data-naam="<?= htmlspecialchars(strtolower($t['naam'])) ?>"><?= htmlspecialchars($t['naam']) ?> (<?= htmlspecialchars($t['code']) ?>)</option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">Variant (bv. lengte: 3m, 5m)</label>
        <input type="text" name="variant" id="variantInput" placeholder="optioneel">
      </div>
      <div class="form-group">
        <label class="form-label">Aantal</label>
        <input type="number" name="aantal" id="aantalInput" value="1" min="1" step="1">
      </div>
      <div class="form-group">
        <label class="form-label">Locatie</label>
        <input type="text" name="locatie" placeholder="bv. Serverroom schap 3">
      </div>
      <div class="form-group">
        <label class="form-label">Product-ID (optioneel, alleen bij aantal = 1)</label>
        <input type="text" name="product_id" id="productIdInput" placeholder="bv. E08ZGET#ABH">
      </div>
    </div>

    <div class="form-group">
      <label class="form-label">Serienummer(s)</label>
      <div id="serienummerVelden">
        <input type="text" name="serienummers[]" placeholder="optioneel — laat leeg bij kabels e.d." style="margin-bottom:8px">
      </div>
      <p style="font-size:12px;color:var(--color-text-secondary);margin:4px 0 0">
        Met serienummer(s) krijgt elk item een eigen unieke barcode — vul er dan voor elk van de <span id="aantalLabel">1</span> item(en) één in.
        Laat ze allemaal leeg voor items zonder serienummer (bv. kabels, muizen): die delen dezelfde barcode per type + variant.
      </p>
    </div>

    <div class="form-group">
      <label class="form-label">Opmerking</label>
      <textarea name="opmerking"></textarea>
    </div>

    <div class="form-group">
      <label class="form-label">DxDiag-rapport (optioneel, .txt — alleen bij aantal = 1, bv. een laptop)</label>
      <input type="file" name="dxdiag_bestand" accept=".txt">
      <p style="font-size:12px;color:var(--color-text-secondary);margin:4px 0 0">
        Upload het .txt-bestand van "DxDiag" (Windows: dxdiag.exe &rarr; Alles opslaan) om systeem- en videokaartgegevens
        beknopt bij dit item op te slaan.
      </p>
    </div>

    <div style="display:flex;gap:8px;margin-top:8px">
      <button class="btn btn-primary" type="submit">Toevoegen</button>
      <a class="btn" href="/voorraad">Annuleren</a>
    </div>
  </form>
</div>

<script>
(function () {
    var aantalInput = document.getElementById('aantalInput');
    var container = document.getElementById('serienummerVelden');
    var aantalLabel = document.getElementById('aantalLabel');

    function syncVelden() {
        var aantal = Math.max(1, parseInt(aantalInput.value, 10) || 1);
        aantalLabel.textContent = aantal;

        var inputs = container.querySelectorAll('input');
        while (inputs.length < aantal) {
            var input = document.createElement('input');
            input.type = 'text';
            input.name = 'serienummers[]';
            input.placeholder = 'Serienummer ' + (inputs.length + 1);
            input.style.marginBottom = '8px';
            container.appendChild(input);
            inputs = container.querySelectorAll('input');
        }
        while (inputs.length > aantal) {
            container.removeChild(inputs[inputs.length - 1]);
            inputs = container.querySelectorAll('input');
        }
        inputs.forEach(function (input, i) {
            input.placeholder = aantal > 1 ? 'Serienummer ' + (i + 1) : 'optioneel — laat leeg bij kabels e.d.';
        });
    }

    aantalInput.addEventListener('input', syncVelden);
})();
</script>

<script>
/**
 * Herkenning van fabrieks-apparaatscans (serienummer,product-ID[,omschrijving], zie
 * App\Shared\AssetScan\BarcodeScanParser) in het losse scanveld boven dit formulier. Bij herkenning
 * wordt POST /api/v1/asset-scan aangeroepen (csrf.js voegt de CSRF-header automatisch toe, zie
 * app/Views/layouts/app.php) voor een suggestieblok: is het serienummer al bekend in de
 * voorraadcatalogus, dan wordt doorverwezen naar het bestaande item (update-flow) i.p.v. de velden
 * te vullen — anders worden serienummer/product-ID/type/variant voorgesteld, altijd aanpasbaar
 * vóór het indienen (nooit blind overgenomen).
 */
(function () {
    var scanInput = document.getElementById('scanInput');
    var suggestieBox = document.getElementById('scanSuggestie');
    var typeSelect = document.getElementById('typeSelect');
    var variantInput = document.getElementById('variantInput');
    var productIdInput = document.getElementById('productIdInput');
    var timer = null;

    function esc(value) {
        var div = document.createElement('div');
        div.textContent = value == null ? '' : value;
        return div.innerHTML;
    }

    function lijktOpApparaatScan(raw) {
        var delen = raw.split(',').map(function (d) { return d.trim(); });
        return delen.length >= 2 && delen[0].length >= 4 && delen[1].length >= 4;
    }

    function hideSuggestie() {
        suggestieBox.style.display = 'none';
        suggestieBox.innerHTML = '';
    }

    function vulEersteSerienummerVeld(waarde) {
        var input = document.querySelector('#serienummerVelden input');
        if (input) { input.value = waarde; }
    }

    function selecteerTypeOpNaam(naam) {
        if (!naam) return;
        var opties = typeSelect.querySelectorAll('option[data-naam]');
        for (var i = 0; i < opties.length; i++) {
            if (opties[i].getAttribute('data-naam') === naam.toLowerCase()) {
                typeSelect.value = opties[i].value;
                return;
            }
        }
    }

    function renderSuggestie(s) {
        var stijl = 'border:0.5px solid var(--color-border-tertiary);border-radius:8px;padding:10px 12px;background:var(--color-background-secondary)';
        var delen = [];

        delen.push('<div style="font-size:12.5px;font-weight:600"><i class="bi bi-cpu"></i> Waarschijnlijk laptop of werkstation gedetecteerd</div>');
        delen.push('<div style="font-size:11.5px;color:var(--color-text-secondary);margin-top:2px">Serienummer en product-ID gevonden vanuit barcode: <span class="mono">' + esc(s.serial_number) + '</span> / <span class="mono">' + esc(s.product_id) + '</span>' + (s.description ? ' &mdash; ' + esc(s.description) : '') + '</div>');

        if (s.match && s.match.voorraad_item_id) {
            delen.push('<div style="margin-top:8px;font-size:12px;border-top:0.5px solid var(--color-border-tertiary);padding-top:8px">' +
                '<div style="color:var(--color-text-danger);font-weight:500"><i class="bi bi-exclamation-triangle"></i> Dit serienummer bestaat al in de voorraadcatalogus.</div>' +
                '<div style="margin-top:4px">' + esc(s.match.asset_naam || s.match.model || 'Item') + (s.match.fabrikant ? ' (' + esc(s.match.fabrikant) + ')' : '') + ' &mdash; status: ' + esc(s.match.status || '—') + '</div>' +
                '<a class="btn" style="margin-top:6px;display:inline-block" href="/voorraad/' + s.match.voorraad_item_id + '/edit">Bestaand item bijwerken</a>' +
                '</div>');
        } else if (s.match) {
            delen.push('<div style="margin-top:8px;font-size:12px;border-top:0.5px solid var(--color-border-tertiary);padding-top:8px">' +
                '<div style="color:var(--color-text-tertiary);font-size:11px;text-transform:uppercase">Bekend uit ' + esc(s.match.bron) + ', nog niet in de voorraadcatalogus</div>' +
                '<div>' + esc(s.match.asset_naam || s.match.model || 'Onbekend model') + (s.match.fabrikant ? ' (' + esc(s.match.fabrikant) + ')' : '') + '</div>' +
                '</div>');
        } else {
            delen.push('<div style="margin-top:8px;font-size:12px;color:var(--color-text-tertiary)">Geen bestaande registratie gevonden &mdash; wordt hieronder als nieuw voorgesteld.</div>');
        }

        if (s.suggested_employee) {
            delen.push('<div style="margin-top:8px;font-size:12px"><i class="bi bi-person"></i> Waarschijnlijk laatste gebruiker: <strong>' + esc(s.suggested_employee.naam) + '</strong><div style="color:var(--color-text-tertiary);font-size:11px">Bron: ' + esc(s.suggested_employee.bron) + '</div></div>');
        }
        if (s.last_logged_on_user) {
            delen.push('<div style="margin-top:4px;font-size:11px;color:var(--color-text-tertiary)">Laatst ingelogde gebruiker (NinjaOne): ' + esc(s.last_logged_on_user) + '</div>');
        }

        suggestieBox.style.display = 'block';
        suggestieBox.innerHTML = '<div style="' + stijl + '">' + delen.join('') + '</div>';

        if (!s.match || !s.match.voorraad_item_id) {
            vulEersteSerienummerVeld(s.serial_number || '');
            if (productIdInput) productIdInput.value = s.product_id || '';
            if (variantInput && !variantInput.value && s.description) variantInput.value = s.description.slice(0, 50);
            selecteerTypeOpNaam(s.suggested_asset_type);
        }
    }

    scanInput.addEventListener('input', function () {
        clearTimeout(timer);
        var raw = scanInput.value.trim();

        if (raw.length < 2 || !lijktOpApparaatScan(raw)) {
            hideSuggestie();
            return;
        }

        timer = setTimeout(function () {
            suggestieBox.style.display = 'block';
            suggestieBox.innerHTML = '<div style="font-size:12px;color:var(--color-text-tertiary);padding:6px 0">Apparaat herkennen&hellip;</div>';

            fetch('/api/v1/asset-scan', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ raw: raw, context: 'voorraad' }),
            })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    if (res.status === 'success') {
                        renderSuggestie(res.data);
                    } else {
                        hideSuggestie();
                    }
                })
                .catch(function () { hideSuggestie(); });
        }, 300);
    });
})();
</script>
