<?php
/** @var array $item */
/** @var array $logs */
/** @var array $tijdregistraties */
/** @var int $tijdTotaal */
/** @var array $gekoppeldeArtikelen */
/** @var array $suggestiesArtikelen */
require_once APP_ROOT . '/app/Views/partials/ticket-helpers.php';
$statussen = ['open' => 'Open', 'in_behandeling' => 'In behandeling', 'wacht_op_info' => 'Wacht op info', 'afgehandeld' => 'Afgehandeld'];

$flashError = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_error']);
?>
<div class="detail-subheader">
  <a class="detail-back" href="/tickets"><i class="bi bi-arrow-left"></i> Terug</a>
  <span style="color:var(--color-text-tertiary)">&middot;</span>
  <?= statusBadge($item['status']) ?>
  <?= prioBadge($item['prioriteit']) ?>
  <div style="flex:1"></div>
  <?php if ($item['status'] !== 'afgehandeld'): ?>
    <button type="submit" form="ticketLogForm" name="status" value="afgehandeld" class="btn btn-ghost"><i class="bi bi-check-circle"></i> Markeer afgehandeld</button>
  <?php endif; ?>
  <a class="btn btn-ghost" href="/tickets/<?= $item['id'] ?>/edit"><i class="bi bi-person-circle"></i> Toewijzen / bewerken</a>
  <?= deleteButton('tickets', $item['id']) ?>
</div>

<?php if ($flashError): ?>
  <div class="alert alert-error"><?= htmlspecialchars($flashError) ?></div>
<?php endif; ?>

<div class="grid-detail">
  <div class="grid-detail-main">

    <div class="card" style="padding:18px;margin-bottom:16px">
      <div style="display:flex;align-items:center;gap:8px;margin-bottom:10px">
        <i class="bi bi-envelope" style="color:var(--color-text-tertiary)"></i>
        <span style="font-size:12px;font-weight:500;color:var(--color-text-secondary)">Oorspronkelijk bericht van <?= htmlspecialchars($item['opdrachtgever_naam']) ?></span>
      </div>
      <div class="collapsible-text" style="font-size:13px;line-height:1.7;color:var(--color-text-secondary);overflow-wrap:anywhere;max-height:4.5em;overflow:hidden">
        <?= $item['omschrijving'] !== '' ? nl2br(htmlspecialchars($item['omschrijving'])) : '<span style="color:var(--color-text-tertiary)">Geen omschrijving</span>' ?>
      </div>
      <div class="collapsible-toggle" style="display:none;margin-top:6px">
        <a href="#" class="collapsible-toggle-link" style="font-size:12px">Meer tonen</a>
      </div>
    </div>

    <div class="card" style="margin-bottom:16px">
      <div class="card-header"><span class="card-title">Opmerking toevoegen</span></div>

      <form method="post" action="/tickets/<?= $item['id'] ?>/log" id="ticketLogForm">
        <div style="padding:16px;border-bottom:0.5px solid var(--color-border-tertiary)">
          <div class="form-group" style="margin-bottom:10px">
            <label class="form-label">Titel</label>
            <input type="text" name="titel" id="opmerkingTitel" placeholder="Korte titel voor deze opmerking">
          </div>
          <textarea name="opmerking" id="opmerkingTekst" placeholder="Beschrijf wat je gedaan hebt of vraag om meer informatie…" style="min-height:90px"></textarea>
        </div>
        <div style="display:flex;align-items:center;justify-content:flex-end;padding:10px 16px;background:var(--color-background-secondary)">
          <button class="btn btn-accent" type="submit">Opslaan</button>
        </div>
      </form>
    </div>

    <div class="card">
      <div class="card-header">
        <span class="card-title">Logboek &amp; tijdlijn</span>
        <span style="font-size:11px;color:var(--color-text-tertiary)"><?= count($logs) ?> gebeurtenissen</span>
      </div>

      <?php if (empty($logs)): ?>
        <div class="empty-state">Nog geen gebeurtenissen.</div>
      <?php else: ?>
        <div class="timeline">
          <?php foreach ($logs as $log):
            $isStatus = $log['status_naar'] !== null;
            $heeftOpmerking = trim($log['opmerking'] ?? '') !== '' || !empty($log['titel']);
          ?>
          <div class="timeline-item">
            <div class="timeline-connector"></div>
            <div class="timeline-icon <?= $isStatus ? 'timeline-icon-status' : 'timeline-icon-opmerking' ?>">
              <i class="bi <?= $isStatus ? 'bi-arrow-repeat' : 'bi-chat-left-text' ?>"></i>
            </div>
            <div class="timeline-body">
              <div class="timeline-head">
                <span class="timeline-actor"><?= htmlspecialchars($log['user_naam'] ?? 'ACA') ?></span>
                <span class="timeline-action">
                  <?= $isStatus ? 'wijzigde de status' : 'voegde een opmerking toe' ?>
                </span>
                <span class="timeline-time"><?= formatDatumTijd($log['created_at']) ?></span>
              </div>
              <?php if ($isStatus): ?>
                <div style="margin-top:6px">
                  <span class="status-change">
                    <span class="badge badge-<?= htmlspecialchars($log['status_van']) ?>" style="padding:2px 6px;font-size:10px"><?= statusLabel($log['status_van']) ?></span>
                    &rarr;
                    <span class="badge badge-<?= htmlspecialchars($log['status_naar']) ?>" style="padding:2px 6px;font-size:10px"><?= statusLabel($log['status_naar']) ?></span>
                  </span>
                </div>
              <?php endif; ?>
              <?php if ($heeftOpmerking): ?>
                <div class="timeline-detail">
                  <?php if (!empty($log['titel'])): ?><strong><?= htmlspecialchars($log['titel']) ?></strong><br><?php endif; ?>
                  <?php if (trim($log['opmerking'] ?? '') !== ''): ?><?= nl2br(htmlspecialchars($log['opmerking'])) ?><?php endif; ?>
                </div>
              <?php endif; ?>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <div class="grid-detail-side">

    <div class="card" style="padding:16px">
      <h3 class="detail-side-heading">Details</h3>
      <div class="meta-row"><span class="meta-key">Opdrachtgever</span><span><?= htmlspecialchars($item['opdrachtgever_naam']) ?></span></div>
      <div class="meta-row"><span class="meta-key">Afdeling</span><span><?= htmlspecialchars($item['afdeling_naam'] ?? '—') ?></span></div>
      <div class="meta-row"><span class="meta-key">Behandelaar</span><span><?= htmlspecialchars($item['behandelaar_naam'] ?? '—') ?></span></div>
      <div class="meta-row"><span class="meta-key">Categorie</span><span><?= htmlspecialchars($item['categorie'] ?? 'Algemeen') ?></span></div>
      <?php if (!empty($item['is_cyberrisico'])): ?>
      <div class="meta-row"><span class="meta-key">Cyber risico</span><span><?= prioBadge('kritiek') ?></span></div>
      <?php endif; ?>
      <div class="meta-row"><span class="meta-key">Impact</span><span><?= htmlspecialchars($item['impact']) ?></span></div>
      <div class="meta-row"><span class="meta-key">Schatting</span><span><?= ($item['schatting_minuten'] ?? null) !== null ? $item['schatting_minuten'] . ' min' : '—' ?></span></div>
      <div class="meta-row"><span class="meta-key">Aangemaakt</span><span><?= formatDatum($item['created_at']) ?></span></div>
      <div class="meta-row"><span class="meta-key">Bijgewerkt</span><span><?= formatDatum($item['updated_at'] ?? null) ?></span></div>
      <div class="meta-row"><span class="meta-key">Deadline</span><span><?= formatDatum($item['deadline']) ?></span></div>
    </div>

    <div class="card" style="padding:16px">
      <h3 class="detail-side-heading">Status wijzigen</h3>
      <div class="status-picker">
        <?php foreach ($statussen as $val => $label): ?>
          <button type="submit" form="ticketLogForm" name="status" value="<?= $val ?>"
                  class="<?= $item['status'] === $val ? 'active' : '' ?>"
                  style="background:var(--color-status-<?= $val === 'in_behandeling' ? 'behandeling' : ($val === 'wacht_op_info' ? 'wachtend' : ($val === 'afgehandeld' ? 'opgelost' : 'open')) ?>-bg);color:var(--color-status-<?= $val === 'in_behandeling' ? 'behandeling' : ($val === 'wacht_op_info' ? 'wachtend' : ($val === 'afgehandeld' ? 'opgelost' : 'open')) ?>)">
            <?= htmlspecialchars($label) ?>
          </button>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="card" style="padding:16px">
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px">
        <h3 class="detail-side-heading" style="margin-bottom:0">Tijdregistratie</h3>
        <span style="font-size:11px;color:var(--color-text-tertiary)">Totaal: <?= $tijdTotaal ?> min</span>
      </div>
      <form method="post" action="/tickets/<?= $item['id'] ?>/tijd" style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:<?= empty($tijdregistraties) ? '0' : '10px' ?>">
        <?php foreach ([5, 10, 15, 30, 45, 60] as $blok): ?>
          <button class="btn" type="submit" name="minuten" value="<?= $blok ?>" style="padding:4px 9px;font-size:11.5px"><?= $blok ?> min</button>
        <?php endforeach; ?>
      </form>
      <?php if (!empty($tijdregistraties)): ?>
        <div class="log-list" style="max-height:160px;margin:0 -16px -16px;border-top:0.5px solid var(--color-border-tertiary)">
        <?php foreach ($tijdregistraties as $tijd): ?>
        <div class="log-item">
          <div class="log-meta">
            <span class="log-user"><?= htmlspecialchars($tijd['user_naam'] ?? 'Onbekend') ?></span>
            <span class="log-time"><?= formatDatumTijd($tijd['created_at']) ?></span>
          </div>
          <div class="log-text"><?= (int) $tijd['minuten'] ?> min</div>
        </div>
        <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <div class="card" style="padding:16px">
      <h3 class="detail-side-heading">Escalatie</h3>
      <div class="form-group" style="margin-bottom:10px">
        <label class="form-label">Escalatienummer</label>
        <input type="text" name="escalatie_nummer" form="ticketLogForm" value="<?= htmlspecialchars($item['escalatie_nummer'] ?? '') ?>" placeholder="Bijv. CAS-109311-L4Z5L7 - ACA:000133869">
      </div>
      <div class="form-group" style="margin-bottom:10px">
        <label class="form-label">Instantie</label>
        <input type="text" name="escalatie_instantie" form="ticketLogForm" value="<?= htmlspecialchars($item['escalatie_instantie'] ?? '') ?>" placeholder="Bijv. ACA, ClearSolutions">
      </div>
      <button class="btn" type="submit" form="ticketLogForm">Opslaan</button>
    </div>

    <div class="card" style="padding:16px">
      <div style="display:flex;align-items:center;gap:6px;margin-bottom:10px">
        <i class="bi bi-stars" style="color:var(--color-primary);font-size:12px"></i>
        <h3 class="detail-side-heading" style="margin-bottom:0">Kennisbank</h3>
      </div>

      <?php if (empty($gekoppeldeArtikelen) && empty($suggestiesArtikelen)): ?>
        <div style="font-size:12.5px;color:var(--color-text-tertiary)">Nog geen artikelen gekoppeld of gesuggereerd.</div>
      <?php endif; ?>

      <?php foreach ($gekoppeldeArtikelen as $artikel): ?>
        <div class="kb-item" style="display:flex;align-items:center;justify-content:space-between;gap:8px">
          <a href="/kennisbank/<?= $artikel['id'] ?>" style="min-width:0">
            <div class="kb-item-id">Gekoppeld</div>
            <div class="kb-item-title text-truncate"><?= htmlspecialchars($artikel['titel']) ?></div>
          </a>
          <form method="post" action="/tickets/<?= $item['id'] ?>/kennisbank/<?= $artikel['id'] ?>/verwijderen"
                onsubmit="return confirm('Koppeling met dit artikel verwijderen?')">
            <button class="btn btn-danger" type="submit" style="padding:2px 8px;font-size:11px">&times;</button>
          </form>
        </div>
      <?php endforeach; ?>

      <?php if (!empty($suggestiesArtikelen)): ?>
        <?php if (!empty($gekoppeldeArtikelen)): ?><div style="font-size:10.5px;text-transform:uppercase;letter-spacing:.04em;color:var(--color-text-tertiary);margin:10px 0 6px">Suggesties</div><?php endif; ?>
        <?php foreach ($suggestiesArtikelen as $artikel): ?>
          <form method="post" action="/tickets/<?= $item['id'] ?>/kennisbank" class="kb-item" style="display:flex;align-items:center;justify-content:space-between;gap:8px">
            <span style="min-width:0">
              <span class="kb-item-id">Suggestie · <?= htmlspecialchars($item['categorie'] ?? 'Algemeen') ?></span>
              <span class="kb-item-title text-truncate" style="display:block"><?= htmlspecialchars($artikel['titel']) ?></span>
            </span>
            <input type="hidden" name="kennisbank_artikel_id" value="<?= $artikel['id'] ?>">
            <button class="btn" type="submit" style="padding:2px 8px;font-size:11px">Koppelen</button>
          </form>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var scrollKey = 'scrollPos:' + window.location.pathname;
    var savedScroll = sessionStorage.getItem(scrollKey);
    if (savedScroll !== null) {
        sessionStorage.removeItem(scrollKey);
        window.scrollTo(0, parseInt(savedScroll, 10));
    }
    document.querySelectorAll('form').forEach(function (form) {
        form.addEventListener('submit', function () {
            sessionStorage.setItem(scrollKey, String(window.scrollY));
        });
    });

    var ticketLogForm = document.getElementById('ticketLogForm');
    var opmerkingTitel = document.getElementById('opmerkingTitel');
    var opmerkingTekst = document.getElementById('opmerkingTekst');
    if (ticketLogForm && opmerkingTitel && opmerkingTekst) {
        ticketLogForm.addEventListener('submit', function (e) {
            if (opmerkingTekst.value.trim() !== '' && opmerkingTitel.value.trim() === '') {
                e.preventDefault();
                opmerkingTitel.setCustomValidity('Vul een titel in om deze opmerking op te slaan.');
                opmerkingTitel.reportValidity();
                opmerkingTitel.focus();
            }
        });
        opmerkingTitel.addEventListener('input', function () {
            opmerkingTitel.setCustomValidity('');
        });
    }

    document.querySelectorAll('.collapsible-text').forEach(function (el) {
        if (el.scrollHeight <= el.clientHeight + 1) {
            return;
        }
        var toggle = el.nextElementSibling;
        var link = toggle.querySelector('.collapsible-toggle-link');
        toggle.style.display = 'block';
        link.addEventListener('click', function (e) {
            e.preventDefault();
            var expanded = el.style.maxHeight === 'none';
            el.style.maxHeight = expanded ? '4.5em' : 'none';
            link.textContent = expanded ? 'Meer tonen' : 'Minder tonen';
        });
    });
});
</script>
