<?php
/** @var array $gebruikers */
/** @var array $tickets */
/** @var array $verbeterpunten */
/** @var int $huidigeGebruikerId */
/** @var array $types */
/** @var array $teamVandaag */

$flashSuccess = $_SESSION['flash_success'] ?? null;
$flashError = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);
?>
<div class="page-header">
  <div class="page-title">Agenda</div>
</div>

<?php if ($flashSuccess): ?>
  <div class="alert alert-success"><?= htmlspecialchars($flashSuccess) ?></div>
<?php endif; ?>
<?php if ($flashError): ?>
  <div class="alert alert-error"><?= htmlspecialchars($flashError) ?></div>
<?php endif; ?>

<div class="agenda-toolbar">
  <div class="agenda-toolbar-nav">
    <button class="agenda-icon-btn" type="button" id="agenda-prev"><i class="bi bi-chevron-left"></i></button>
    <button class="agenda-today-btn" type="button" id="agenda-today">Vandaag</button>
    <button class="agenda-icon-btn" type="button" id="agenda-next"><i class="bi bi-chevron-right"></i></button>
  </div>
  <div class="agenda-date-picker">
    <button type="button" class="agenda-date-btn" id="agenda-date-btn">
      <i class="bi bi-calendar3"></i>
      <span id="agenda-date-label"></span>
    </button>
    <input type="date" id="agenda-date-input" class="agenda-date-input-native" aria-label="Datum kiezen">
  </div>
  <div class="agenda-view-switch" id="agenda-view-switch">
    <button type="button" data-view="timeGridDay">Dag</button>
    <button type="button" data-view="timeGridWeek" class="active">Week</button>
    <button type="button" data-view="team">Team</button>
  </div>
  <div class="agenda-toolbar-filters">
    <select id="agenda-persoon" class="form-select" style="width:auto">
      <?php foreach ($gebruikers as $g): ?>
        <option value="<?= $g['id'] ?>" <?= (int) $g['id'] === (int) $huidigeGebruikerId ? 'selected' : '' ?>><?= htmlspecialchars($g['naam']) ?></option>
      <?php endforeach; ?>
    </select>
    <label class="agenda-toolbar-check" id="agenda-alleen-in-behandeling-wrap" style="display:none">
      <input type="checkbox" id="agenda-alleen-in-behandeling"> Alleen tickets "in behandeling"
    </label>
    <button class="btn btn-primary" type="button" id="agenda-nieuw-btn">+ Nieuwe afspraak</button>
  </div>
</div>

<div style="display:grid;grid-template-columns:minmax(0,1fr) 300px;gap:16px;align-items:start">
  <div class="card agenda-calendar-fixed" style="padding:0;overflow:hidden">
    <div id="agenda-calendar"></div>
  </div>

  <div>
    <div class="card" style="padding:16px;margin-bottom:16px">
      <h3 class="detail-side-heading"><i class="bi bi-people" style="margin-right:6px"></i>Team vandaag</h3>
      <?php if (empty($teamVandaag)): ?>
        <div class="empty-state" style="padding:12px 0">Geen actieve medewerkers met login.</div>
      <?php else: ?>
        <div style="display:flex;flex-direction:column;gap:10px;margin-top:8px">
          <?php foreach ($teamVandaag as $m): ?>
            <div style="display:flex;align-items:center;gap:8px;font-size:13px">
              <span class="avatar-xs" style="width:26px;height:26px;font-size:10px"><?= htmlspecialchars(mb_strtoupper(mb_substr($m['voornaam'], 0, 1) . mb_substr($m['achternaam'], 0, 1))) ?></span>
              <div style="min-width:0;flex:1">
                <div class="text-truncate" style="font-weight:500"><?= htmlspecialchars($m['voornaam'] . ' ' . $m['achternaam']) ?></div>
                <div class="text-truncate" style="font-size:10.5px;color:var(--color-text-tertiary)"><?= htmlspecialchars($m['functie'] ?? '—') ?></div>
              </div>
              <?php if ((int) $m['in_behandeling'] > 0): ?>
                <span class="mono" style="font-size:10px;padding:2px 6px;border-radius:4px;background:var(--color-status-behandeling-bg);color:var(--color-status-behandeling)"><?= (int) $m['in_behandeling'] ?></span>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <div class="card" style="padding:16px">
      <h3 class="detail-side-heading"><i class="bi bi-calendar3" style="margin-right:6px"></i>Legenda</h3>
      <div style="display:flex;flex-direction:column;gap:8px;margin-top:8px;font-size:12.5px">
        <div style="display:flex;align-items:center;gap:8px"><span style="width:12px;height:12px;border-radius:3px;background:#e2a94a"></span> Ticket</div>
        <div style="display:flex;align-items:center;gap:8px"><span style="width:12px;height:12px;border-radius:3px;background:#7ecb57"></span> Verbeterpunt</div>
        <div style="display:flex;align-items:center;gap:8px"><span style="width:12px;height:12px;border-radius:3px;background:#378ADD"></span> Afspraak</div>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="agendaModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="agendaModalTitel">Nieuwe afspraak</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Sluiten"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="agenda-id" value="">
        <div class="form-group">
          <label class="form-label">Titel</label>
          <input type="text" id="agenda-titel" required>
        </div>
        <div class="form-group">
          <label class="form-label">Type</label>
          <select id="agenda-type">
            <?php foreach ($types as $val => $label): ?>
              <option value="<?= htmlspecialchars($val) ?>"><?= htmlspecialchars($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group" id="agenda-gekoppeld-wrap" style="display:none">
          <label class="form-label" id="agenda-gekoppeld-label">Koppelen aan</label>
          <input type="text" id="agenda-gekoppeld-search" list="agenda-gekoppeld-options" autocomplete="off" placeholder="Typ om te zoeken&hellip;">
          <datalist id="agenda-gekoppeld-options"></datalist>
          <input type="hidden" id="agenda-gekoppeld-id" value="">
        </div>
        <div class="form-group">
          <label class="form-label">Persoon</label>
          <select id="agenda-user-id">
            <?php foreach ($gebruikers as $g): ?>
              <option value="<?= $g['id'] ?>"><?= htmlspecialchars($g['naam']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-grid" style="grid-template-columns:1fr 1fr">
          <div class="form-group">
            <label class="form-label">Start</label>
            <input type="datetime-local" id="agenda-start">
          </div>
          <div class="form-group">
            <label class="form-label">Einde</label>
            <input type="datetime-local" id="agenda-eind">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Locatie</label>
          <input type="text" id="agenda-locatie">
        </div>
        <div class="form-group">
          <label class="form-label">Omschrijving</label>
          <textarea id="agenda-omschrijving"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-danger d-none" id="agenda-verwijder-btn">Verwijderen</button>
        <button type="button" class="btn" data-bs-dismiss="modal">Annuleren</button>
        <button type="button" class="btn btn-primary" id="agenda-opslaan-btn">Opslaan</button>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/locales/nl.global.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var tickets = <?= json_encode(array_map(fn ($t) => ['id' => (int) $t['id'], 'titel' => $t['titel']], $tickets), JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    var verbeterpunten = <?= json_encode(array_map(fn ($v) => ['id' => (int) $v['id'], 'titel' => $v['titel']], $verbeterpunten), JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    var huidigeGebruikerId = <?= (int) $huidigeGebruikerId ?>;

    var calendarEl = document.getElementById('agenda-calendar');
    var dateBtn = document.getElementById('agenda-date-btn');
    var dateLabel = document.getElementById('agenda-date-label');
    var dateInput = document.getElementById('agenda-date-input');
    var personSelect = document.getElementById('agenda-persoon');
    var alleenInBehandelingCheckbox = document.getElementById('agenda-alleen-in-behandeling');
    var alleenInBehandelingWrap = document.getElementById('agenda-alleen-in-behandeling-wrap');
    var modalEl = document.getElementById('agendaModal');
    var modal = new bootstrap.Modal(modalEl);

    // "Team"-tab is geen echte FullCalendar-view (geen resource-timeline zonder betaalde
    // Scheduler-licentie) — hergebruikt timeGridDay maar haalt events van iedereen op via de
    // bestaande /agenda/team-events-endpoint i.p.v. alleen de geselecteerde persoon.
    var teamMode = false;

    function setTeamMode(actief) {
        teamMode = actief;
        personSelect.disabled = actief;
        alleenInBehandelingWrap.style.display = actief ? 'flex' : 'none';
    }

    // Gekoppeld ticket/verbeterpunt kiezen via een zoekbare combobox (input + <datalist>, zelfde
    // patroon als de barcode/naam-autocomplete in uitgiften-index.js) i.p.v. een lange <select> —
    // bij veel tickets moest daar anders doorheen gescrold worden. Geen echte multi-select: elke
    // afspraak koppelt aan precies één ticket/verbeterpunt (agenda_items.gekoppeld_id is een losse
    // kolom, geen koppeltabel), dus dat zou een schemawijziging vereisen.
    var gekoppeldeLabelById = {};

    function gekoppeldLabel(item) {
        return '#' + item.id + ' — ' + item.titel;
    }

    function gekoppeldeOpties() {
        var type = document.getElementById('agenda-type').value;
        var wrap = document.getElementById('agenda-gekoppeld-wrap');
        var datalist = document.getElementById('agenda-gekoppeld-options');
        var searchInput = document.getElementById('agenda-gekoppeld-search');
        var hiddenInput = document.getElementById('agenda-gekoppeld-id');
        var label = document.getElementById('agenda-gekoppeld-label');

        datalist.innerHTML = '';
        searchInput.value = '';
        hiddenInput.value = '';
        gekoppeldeLabelById = {};

        var items = type === 'ticket' ? tickets : (type === 'verbeterpunt' ? verbeterpunten : []);
        if (items.length) {
            wrap.style.display = '';
            label.textContent = type === 'ticket' ? 'Ticket' : 'Verbeterpunt';
            items.forEach(function (item) {
                var optLabel = gekoppeldLabel(item);
                gekoppeldeLabelById[item.id] = optLabel;
                datalist.innerHTML += '<option value="' + optLabel.replace(/</g, '&lt;').replace(/"/g, '&quot;') + '">';
            });
        } else {
            wrap.style.display = 'none';
        }
    }
    document.getElementById('agenda-type').addEventListener('change', gekoppeldeOpties);

    // Bij elke wijziging in het zoekveld: alleen een exacte match met een optie uit de datalist
    // (dus daadwerkelijk gekozen, niet zomaar getypt) zet het gekoppelde id.
    document.getElementById('agenda-gekoppeld-search').addEventListener('input', function () {
        var typed = this.value;
        var hiddenInput = document.getElementById('agenda-gekoppeld-id');
        var matchId = Object.keys(gekoppeldeLabelById).find(function (id) {
            return gekoppeldeLabelById[id] === typed;
        });
        hiddenInput.value = matchId || '';
    });

    function toDatetimeLocal(iso) {
        return iso.slice(0, 16);
    }

    function openCreateModal(startStr, endStr) {
        document.getElementById('agendaModalTitel').textContent = 'Nieuwe afspraak';
        document.getElementById('agenda-id').value = '';
        document.getElementById('agenda-titel').value = '';
        document.getElementById('agenda-type').value = 'afspraak';
        document.getElementById('agenda-omschrijving').value = '';
        document.getElementById('agenda-locatie').value = '';
        document.getElementById('agenda-user-id').value = personSelect.value;
        document.getElementById('agenda-start').value = startStr ? toDatetimeLocal(startStr) : '';
        document.getElementById('agenda-eind').value = endStr ? toDatetimeLocal(endStr) : '';
        document.getElementById('agenda-verwijder-btn').classList.add('d-none');
        gekoppeldeOpties();
        modal.show();
    }

    function openEditModal(event) {
        var props = event.extendedProps;
        document.getElementById('agendaModalTitel').textContent = 'Afspraak bewerken';
        document.getElementById('agenda-id').value = event.id;
        document.getElementById('agenda-titel').value = event.title;
        document.getElementById('agenda-type').value = props.type;
        document.getElementById('agenda-omschrijving').value = props.omschrijving || '';
        document.getElementById('agenda-locatie').value = props.locatie || '';
        document.getElementById('agenda-user-id').value = props.user_id;
        document.getElementById('agenda-start').value = toDatetimeLocal(event.startStr);
        document.getElementById('agenda-eind').value = toDatetimeLocal(event.endStr);
        document.getElementById('agenda-verwijder-btn').classList.remove('d-none');
        gekoppeldeOpties();
        if (props.gekoppeld_id) {
            document.getElementById('agenda-gekoppeld-id').value = props.gekoppeld_id;
            // gekoppeldeLabelById kent alleen nog-open tickets/lopende verbeterpunten (zelfde bron
            // als voorheen de <select>-opties) — als het gekoppelde item er niet meer in zit
            // (bv. inmiddels afgehandeld) valt de zoekbalk terug op "#id" i.p.v. leeg.
            document.getElementById('agenda-gekoppeld-search').value = gekoppeldeLabelById[props.gekoppeld_id] || ('#' + props.gekoppeld_id);
        }
        modal.show();
    }

    // Datum-knop-label: bij dag-/teamweergave de volledige datum, bij weekweergave
    // "Week NN · d - d maand jaar" (zelfde formattering als de Lovable-mockup).
    var MAANDEN = ['januari', 'februari', 'maart', 'april', 'mei', 'juni', 'juli', 'augustus', 'september', 'oktober', 'november', 'december'];
    function isoWeekNumber(date) {
        var d = new Date(Date.UTC(date.getFullYear(), date.getMonth(), date.getDate()));
        var dayNum = d.getUTCDay() || 7;
        d.setUTCDate(d.getUTCDate() + 4 - dayNum);
        var yearStart = new Date(Date.UTC(d.getUTCFullYear(), 0, 1));
        return Math.ceil((((d - yearStart) / 86400000) + 1) / 7);
    }
    function formatDateLabel(view) {
        var start = view.currentStart;
        if (view.type === 'timeGridWeek') {
            var end = new Date(view.currentEnd);
            end.setDate(end.getDate() - 1);
            var week = isoWeekNumber(start);
            var sameMonth = start.getMonth() === end.getMonth();
            var range = sameMonth
                ? start.getDate() + ' - ' + end.getDate() + ' ' + MAANDEN[end.getMonth()] + ' ' + end.getFullYear()
                : start.getDate() + ' ' + MAANDEN[start.getMonth()] + ' - ' + end.getDate() + ' ' + MAANDEN[end.getMonth()] + ' ' + end.getFullYear();
            return 'Week ' + week + ' · ' + range;
        }
        return start.getDate() + ' ' + MAANDEN[start.getMonth()] + ' ' + start.getFullYear();
    }
    function toIsoDate(d) {
        return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
    }

    var calendar = new FullCalendar.Calendar(calendarEl, {
        locale: 'nl',
        firstDay: 1,
        height: 560,
        slotMinTime: '00:00:00',
        slotMaxTime: '24:00:00',
        scrollTime: '08:00:00',
        initialView: 'timeGridWeek',
        headerToolbar: false,
        editable: true,
        selectable: true,
        events: function (info, success, failure) {
            var url;
            if (teamMode) {
                url = '/agenda/team-events?start=' + info.startStr + '&end=' + info.endStr
                    + '&alleen_in_behandeling=' + (alleenInBehandelingCheckbox.checked ? '1' : '0');
            } else {
                url = '/agenda/events?user_id=' + personSelect.value + '&start=' + info.startStr + '&end=' + info.endStr;
            }
            fetch(url)
                .then(function (r) { return r.json(); })
                .then(success)
                .catch(failure);
        },
        eventDidMount: function (info) {
            var props = info.event.extendedProps;
            var tekst = info.event.title;
            if (props.gekoppeld_status) {
                tekst += ' — status: ' + props.gekoppeld_status;
            }
            info.el.title = tekst;
            if (props.type) {
                info.el.classList.add('agenda-type-' + props.type);
            }
        },
        datesSet: function (info) {
            dateLabel.textContent = formatDateLabel(info.view);
            dateInput.value = toIsoDate(info.view.currentStart);
            if (!teamMode) {
                document.querySelectorAll('#agenda-view-switch button').forEach(function (btn) {
                    btn.classList.toggle('active', btn.dataset.view === info.view.type);
                });
            }
        },
        select: function (info) {
            openCreateModal(info.startStr, info.endStr);
        },
        eventClick: function (info) {
            openEditModal(info.event);
        },
        eventDrop: function (info) {
            saveDragOrResize(info);
        },
        eventResize: function (info) {
            saveDragOrResize(info);
        }
    });
    calendar.render();

    document.getElementById('agenda-prev').addEventListener('click', function () { calendar.prev(); });
    document.getElementById('agenda-next').addEventListener('click', function () { calendar.next(); });
    document.getElementById('agenda-today').addEventListener('click', function () { calendar.today(); });
    dateBtn.addEventListener('click', function () {
        if (dateInput.showPicker) { dateInput.showPicker(); } else { dateInput.focus(); }
    });
    dateInput.addEventListener('change', function () {
        if (!dateInput.value) return;
        calendar.gotoDate(dateInput.value);
    });
    document.querySelectorAll('#agenda-view-switch button').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.querySelectorAll('#agenda-view-switch button').forEach(function (b) { b.classList.remove('active'); });
            btn.classList.add('active');

            if (btn.dataset.view === 'team') {
                setTeamMode(true);
                calendar.changeView('timeGridDay');
            } else {
                setTeamMode(false);
                calendar.changeView(btn.dataset.view);
            }
        });
    });

    personSelect.addEventListener('change', function () {
        calendar.refetchEvents();
    });

    alleenInBehandelingCheckbox.addEventListener('change', function () {
        calendar.refetchEvents();
    });

    document.getElementById('agenda-nieuw-btn').addEventListener('click', function () {
        openCreateModal(null, null);
    });

    function saveDragOrResize(info) {
        var event = info.event;
        fetch('/agenda/' + event.id, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ start_op: event.startStr, eind_op: event.endStr })
        })
        .then(function (r) {
            return r.json()
                .catch(function () {
                    throw new Error('Onverwacht antwoord van de server (status ' + r.status + ').');
                })
                .then(function (res) {
                    if (!r.ok || !res.success) {
                        throw new Error(res.error || 'Opslaan is mislukt.');
                    }
                });
        })
        .catch(function (err) {
            info.revert();
            window.alert('Verplaatsen/opslaan is mislukt: ' + err.message);
        });
    }

    document.getElementById('agenda-opslaan-btn').addEventListener('click', function () {
        var id = document.getElementById('agenda-id').value;
        var payload = {
            titel: document.getElementById('agenda-titel').value,
            type: document.getElementById('agenda-type').value,
            gekoppeld_id: document.getElementById('agenda-gekoppeld-id').value || null,
            user_id: document.getElementById('agenda-user-id').value,
            start_op: document.getElementById('agenda-start').value,
            eind_op: document.getElementById('agenda-eind').value,
            locatie: document.getElementById('agenda-locatie').value,
            omschrijving: document.getElementById('agenda-omschrijving').value
        };

        if (!payload.titel || !payload.start_op || !payload.eind_op) {
            window.alert('Titel, start en einde zijn verplicht.');
            return;
        }

        var url = id ? '/agenda/' + id : '/agenda';
        fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        }).then(function (r) { return r.json(); }).then(function (res) {
            if (res.success) {
                modal.hide();
                calendar.refetchEvents();
            } else {
                window.alert(res.error || 'Opslaan is mislukt.');
            }
        });
    });

    document.getElementById('agenda-verwijder-btn').addEventListener('click', function () {
        var id = document.getElementById('agenda-id').value;
        if (!id || !window.confirm('Deze afspraak verwijderen?')) {
            return;
        }
        fetch('/agenda/' + id + '/verwijderen', { method: 'POST' })
            .then(function (r) { return r.json(); })
            .then(function () {
                modal.hide();
                calendar.refetchEvents();
            });
    });
});
</script>
