<?php
/** @var array $mag */
/** @var array $stats */
/** @var array $actieveTickets */
/** @var array $topUitgegevenHardware */
/** @var int $cyberrisicosOpen */
/** @var array $cyberrisicosPerDag */
/** @var array $cyberrisicosByDate */
/** @var array $afdelingen */
/** @var array $gebruikers */
/** @var array $cyberCategorieen */
/** @var array $cyberPrioriteiten */
/** @var array|null $laatsteTelefoonlijst */
/** @var array $urenstaatLocaties */
/** @var array|null $urenstaatOpen */

require_once APP_ROOT . '/app/Views/partials/ticket-helpers.php';

$chartDates  = array_map(fn(array $d) => $d['datum'], $cyberrisicosPerDag);
$chartLabels = array_map(fn(array $d) => date('d-m', strtotime($d['datum'])), $cyberrisicosPerDag);
$chartData   = array_map(fn(array $d) => $d['aantal'], $cyberrisicosPerDag);
?>

<style>
    .cursor-pointer {
        cursor: pointer;
    }

    .agenda-item:hover {
        background-color: rgba(var(--bs-secondary-rgb), .08);
    }

    .chart-wrap {
        position: relative;
        height: 220px;
    }

    .text-truncate-1 {
        display: block;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    @media (max-width: 575.98px) {
        .chart-wrap {
            height: 200px;
        }
    }
</style>

<div class="d-flex flex-wrap align-items-center gap-2 mb-3">
    <h1 class="h3 mb-0">Dashboard</h1>

    <div class="ms-auto d-flex gap-2">
        <?php if ($mag['tickets']['schrijven']): ?>
        <button type="button" class="btn btn-accent" data-bs-toggle="modal" data-bs-target="#dashTicketModal">
            <i class="bi bi-plus-circle"></i> Nieuw ticket
        </button>
        <?php endif; ?>

        <?php if ($mag['cyberrisicos']['schrijven']): ?>
        <button type="button" class="btn btn-outline-secondary position-relative" data-bs-toggle="modal" data-bs-target="#dashRisicoModal">
            <i class="bi bi-shield-exclamation"></i> Risico melden
            <?php if ($cyberrisicosOpen > 0): ?>
                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill text-bg-danger">
                    <?= (int) $cyberrisicosOpen ?>
                </span>
            <?php endif; ?>
        </button>
        <?php endif; ?>

        <?php if ($mag['urenstaat']['schrijven']): ?>
        <span id="dashDagStartenWrap" <?= $urenstaatOpen ? 'class="d-none"' : '' ?>>
            <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#dashDagStartenModal">
                <i class="bi bi-play-circle"></i> Dag starten
            </button>
        </span>
        <span id="dashDagStoppenWrap" <?= $urenstaatOpen ? '' : 'class="d-none"' ?>>
            <button type="button" class="btn btn-outline-secondary" id="dashDagStoppenBtn">
                <i class="bi bi-stop-circle"></i> Dag afsluiten
            </button>
        </span>
        <?php endif; ?>
    </div>
</div>




<div class="kpi-grid">
    <?php if ($mag['tickets']['lezen']): ?>
    <a class="kpi-card" href="/tickets?status=open">
        <div class="kpi-card-head">
            <span class="kpi-label">Open tickets</span>
            <span class="kpi-icon kpi-icon-open"><i class="bi bi-ticket-perforated"></i></span>
        </div>
        <div class="kpi-value"><?= (int) $stats['tickets_open'] ?></div>
    </a>

    <a class="kpi-card" href="/tickets?status=in_behandeling">
        <div class="kpi-card-head">
            <span class="kpi-label">In behandeling</span>
            <span class="kpi-icon kpi-icon-behandeling"><i class="bi bi-hourglass-split"></i></span>
        </div>
        <div class="kpi-value"><?= (int) $stats['tickets_in_behandeling'] ?></div>
    </a>
    <?php endif; ?>

    <?php if ($mag['verbeterpunten']['lezen']): ?>
    <a class="kpi-card" href="/verbeterpunten">
        <div class="kpi-card-head">
            <span class="kpi-label">Verbeterpunten</span>
            <span class="kpi-icon kpi-icon-neutral"><i class="bi bi-lightbulb"></i></span>
        </div>
        <div class="kpi-value"><?= (int) $stats['verbeterpunten'] ?></div>
    </a>
    <?php endif; ?>

    <?php if ($mag['medewerkers']['lezen']): ?>
    <a class="kpi-card" href="/medewerkers">
        <div class="kpi-card-head">
            <span class="kpi-label">Medewerkers</span>
            <span class="kpi-icon kpi-icon-neutral"><i class="bi bi-people"></i></span>
        </div>
        <div class="kpi-value"><?= (int) $stats['medewerkers'] ?></div>
    </a>
    <?php endif; ?>

    <div class="kpi-card">
        <div class="kpi-card-head">
            <span class="kpi-label">Laatste telefoonlijst</span>
            <span class="kpi-icon kpi-icon-neutral"><i class="bi bi-telephone"></i></span>
        </div>
        <?php if ($laatsteTelefoonlijst === null): ?>
            <div class="kpi-sublabel">Nog geen telefoonlijst verwerkt.</div>
            <a class="btn btn-sm btn-outline-secondary mt-2" href="/tools/telefoonlijst">Openen</a>
        <?php else: ?>
            <div class="kpi-sublabel">
                <?= formatDatumTijd($laatsteTelefoonlijst['processed_at']) ?> &middot;
                <?= (int) $laatsteTelefoonlijst['contact_count'] ?> contact(en)
            </div>
            <a class="btn btn-sm btn-primary mt-2" href="/tools/telefoonlijst/<?= (int) $laatsteTelefoonlijst['id'] ?>/download">Download .vcf</a>
        <?php endif; ?>
    </div>
</div>

<div class="row g-3 mb-3">
    <?php if ($mag['cyberrisicos']['lezen']): ?>
    <div class="col-12 col-lg-6 d-flex">
        <div class="card w-100 h-100">
            <div class="card-header">
                <span class="card-title">Gemelde cyberrisico's — laatste 30 dagen</span>
                <a class="btn" href="/cyberrisicos">Alle risico's &rarr;</a>
            </div>
            <div class="card-body">
                <div class="chart-wrap">
                    <canvas id="cyberrisicoChart"></canvas>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($mag['agenda']['lezen']): ?>
    <div class="col-12 col-lg-6 d-flex">
        <div class="card w-100 h-100">
            <div class="card-header">
                <a class="card-title" href="/agenda">Mijn agenda</a>
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <input type="date" id="dashAgendaDatum" class="form-control form-control-sm" style="width:auto;">
                    <?php if ($mag['agenda']['schrijven']): ?>
                    <button class="btn btn-sm btn-primary" type="button" id="dashAgendaNieuwBtn">+ Toevoegen</button>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card-body py-2" id="dashAgendaLijst">
                <div class="text-body-secondary">Laden...</div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php if ($mag['tickets']['schrijven']): ?>
<div class="modal fade" id="dashTicketModal" tabindex="-1" aria-labelledby="dashTicketModalTitel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="post" action="/tickets">
                <div class="modal-header">
                    <h5 class="modal-title" id="dashTicketModalTitel">Nieuw ticket</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Sluiten"></button>
                </div>

                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <label class="form-label">Opdrachtgever</label>
                            <input type="text" class="form-control" name="opdrachtgever_naam" required>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label">Afdeling</label>
                            <select class="form-select" name="afdeling_id">
                                <option value="">— Selecteer afdeling —</option>
                                <?php foreach ($afdelingen as $a): ?>
                                    <option value="<?= $a['id'] ?>"><?= htmlspecialchars($a['naam']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Taak (korte titel)</label>
                            <input type="text" class="form-control" name="titel" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Omschrijving</label>
                            <textarea class="form-control" name="omschrijving" style="min-height:100px"></textarea>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label">Prioriteit</label>
                            <select class="form-select" name="prioriteit">
                                <option value="laag">Laag</option>
                                <option value="normaal" selected>Normaal</option>
                                <option value="hoog">Hoog</option>
                                <option value="kritiek">Kritiek</option>
                            </select>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label">Impact</label>
                            <select class="form-select" name="impact">
                                <option>Laag</option>
                                <option selected>Normaal</option>
                                <option>Hoog — afdeling</option>
                                <option>Kritiek — productie</option>
                            </select>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label">Schatting (minuten)</label>
                            <input type="number" class="form-control" step="1" name="schatting_minuten">
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label">Deadline</label>
                            <input type="date" class="form-control" name="deadline">
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label">Behandelaar</label>
                            <select class="form-select" name="behandelaar_id">
                                <option value="">— Niet toegewezen —</option>
                                <?php foreach ($gebruikers as $g): ?>
                                    <option value="<?= $g['id'] ?>"><?= htmlspecialchars($g['naam']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuleren</button>
                    <button type="submit" class="btn btn-primary">Ticket aanmaken</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($mag['cyberrisicos']['schrijven']): ?>
<div class="modal fade" id="dashRisicoModal" tabindex="-1" aria-labelledby="dashRisicoModalTitel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="post" action="/cyberrisicos">
                <div class="modal-header">
                    <h5 class="modal-title" id="dashRisicoModalTitel">Risico melden</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Sluiten"></button>
                </div>

                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Titel</label>
                            <input type="text" class="form-control" name="titel" required placeholder="bv. Sticky note met wachtwoord onder toetsenbord">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Omschrijving</label>
                            <textarea class="form-control" name="omschrijving" style="min-height:100px" required></textarea>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label">Type risico</label>
                            <select class="form-select" name="categorie">
                                <?php foreach ($cyberCategorieen as $val => $label): ?>
                                    <option value="<?= $val ?>"><?= htmlspecialchars($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label">Prioriteit</label>
                            <select class="form-select" name="prioriteit">
                                <?php foreach ($cyberPrioriteiten as $val => $label): ?>
                                    <option value="<?= $val ?>" <?= $val === 'middel' ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label">Locatie</label>
                            <input type="text" class="form-control" name="locatie" placeholder="bv. Serverroom, receptie, kantoor 2e verdieping">
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label">Gemeld door</label>
                            <input type="text" class="form-control" name="gemeld_door" placeholder="Naam van de melder">
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label">Eigenaar (verantwoordelijk voor opvolging)</label>
                            <select class="form-select" name="eigenaar_id">
                                <option value="">— Niet toegewezen —</option>
                                <?php foreach ($gebruikers as $g): ?>
                                    <option value="<?= $g['id'] ?>"><?= htmlspecialchars($g['naam']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label">Datum geconstateerd</label>
                            <input type="date" class="form-control" name="datum_geconstateerd">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Oplossingsadvies</label>
                            <textarea class="form-control" name="oplossingsadvies" placeholder="Wat moet er gebeuren om dit risico weg te nemen?"></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Bewijs / notities</label>
                            <textarea class="form-control" name="bewijs_notities" placeholder="Waar/wanneer geconstateerd, foto-locatie, extra context..."></textarea>
                        </div>
                        <div class="col-12 form-check">
                            <input class="form-check-input" type="checkbox" id="dashRisicoGevoelig" name="is_gevoelig" value="1">
                            <label class="form-check-label" for="dashRisicoGevoelig">Bevat gevoelige informatie (bijv. echte wachtwoorden, credentials) — wees terughoudend met details hierboven</label>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuleren</button>
                    <button type="submit" class="btn btn-primary">Risico registreren</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($mag['urenstaat']['schrijven']): ?>
<div class="modal fade" id="dashDagStartenModal" tabindex="-1" aria-labelledby="dashDagStartenModalTitel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="dashDagStartenForm" method="post" action="/urenstaat/starten">
                <div class="modal-header">
                    <h5 class="modal-title" id="dashDagStartenModalTitel">Dag starten</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Sluiten"></button>
                </div>

                <div class="modal-body">
                    <label class="form-label">Locatie</label>
                    <select class="form-select" name="locatie_id">
                        <?php foreach ($urenstaatLocaties as $l): ?>
                            <option value="<?= (int) $l['id'] ?>" <?= $l['naam'] === 'Hoofdlocatie' ? 'selected' : '' ?>><?= htmlspecialchars($l['naam']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuleren</button>
                    <button type="submit" class="btn btn-primary">Starten</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($mag['agenda']['lezen']): ?>
<div class="modal fade" id="dashAgendaModal" tabindex="-1" aria-labelledby="dashAgendaModalTitel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="dashAgendaModalTitel">Nieuwe afspraak</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Sluiten"></button>
            </div>

            <div class="modal-body">
                <input type="hidden" id="dashAgendaId" value="">

                <div class="mb-3">
                    <label class="form-label" for="dashAgendaTitel">Titel</label>
                    <input type="text" id="dashAgendaTitel" class="form-control" required>
                </div>

                <div class="row g-3">
                    <div class="col-6">
                        <label class="form-label" for="dashAgendaStart">Start</label>
                        <input type="time" id="dashAgendaStart" class="form-control" value="09:00">
                    </div>
                    <div class="col-6">
                        <label class="form-label" for="dashAgendaEind">Einde</label>
                        <input type="time" id="dashAgendaEind" class="form-control" value="10:00">
                    </div>
                </div>

                <div class="mt-3">
                    <label class="form-label" for="dashAgendaLocatie">Locatie</label>
                    <input type="text" id="dashAgendaLocatie" class="form-control">
                </div>
            </div>

            <div class="modal-footer">
                <?php if ($mag['agenda']['schrijven']): ?>
                <button type="button" class="btn btn-danger d-none" id="dashAgendaVerwijderBtn">Verwijderen</button>
                <?php endif; ?>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuleren</button>
                <?php if ($mag['agenda']['schrijven']): ?>
                <button type="button" class="btn btn-primary" id="dashAgendaOpslaanBtn">Opslaan</button>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($mag['cyberrisicos']['lezen']): ?>
<div class="modal fade" id="incidentDayModal" tabindex="-1" aria-labelledby="incidentDayModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="incidentDayModalLabel">Incidenten</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Sluiten"></button>
            </div>
            <div class="modal-body">
                <div id="incidentDayModalList"></div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($mag['tickets']['lezen']): ?>
<div class="card">
    <div class="card-header">
        <span class="card-title">Actieve tickets</span>
        <a class="btn" href="/tickets">Alle tickets &rarr;</a>
    </div>

    <?php if (empty($actieveTickets)): ?>
        <div class="empty-state">Geen actieve tickets.</div>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th class="col-1">#</th>
                        <th>Taak</th>
                        <th class="col-2">Afdeling</th>
                        <th class="col-2">Prioriteit</th>
                        <th class="col-2">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($actieveTickets as $t): ?>
                        <tr onclick="window.location='/tickets/<?= (int) $t['id'] ?>'">
                            <td class="mono" style="color:var(--color-text-tertiary)">#<?= (int) $t['id'] ?></td>
                            <td>
                                <span title="<?= htmlspecialchars($t['titel']) ?>">
                                    <?= htmlspecialchars(truncateWoorden($t['titel'])) ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars($t['afdeling_naam'] ?? '—') ?></td>
                            <td><?= prioBadge($t['prioriteit']) ?></td>
                            <td><?= statusBadge($t['status']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php if ($mag['uitgiften']['lezen']): ?>
<div class="card">
    <div class="card-header">
        <span class="card-title">Top 5 uitgegeven hardware</span>
        <a class="btn" href="/uitgiften">Alle uitgiften &rarr;</a>
    </div>

    <?php if (empty($topUitgegevenHardware)): ?>
        <div class="empty-state">Nog geen hardware uitgegeven.</div>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Type</th>
                        <th class="col-2">Code</th>
                        <th class="col-2">Uitgegeven</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($topUitgegevenHardware as $t): ?>
                        <tr onclick="window.location='/voorraad?type_naam=<?= urlencode($t['naam'] ?? '') ?>'">
                            <td><?= htmlspecialchars($t['naam'] ?? 'Onbekend') ?></td>
                            <td style="color:var(--color-text-tertiary)"><?= htmlspecialchars($t['code'] ?? '—') ?></td>
                            <td><?= (int) $t['aantal'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var canvas = document.getElementById('cyberrisicoChart');
    if (!canvas || typeof Chart === 'undefined') {
        return;
    }

    var chartDates = <?= json_encode($chartDates) ?>;
    var incidentsByDate = <?= json_encode($cyberrisicosByDate) ?>;

    function escapeHtml(text) {
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function formatDatumNl(iso) {
        var parts = iso.split('-');
        return parts[2] + '-' + parts[1] + '-' + parts[0];
    }

    function showIncidentsForDate(date) {
        var incidents = incidentsByDate[date] || [];

        document.getElementById('incidentDayModalLabel').textContent = 'Incidenten op ' + formatDatumNl(date);

        var list = document.getElementById('incidentDayModalList');
        list.innerHTML = '';

        if (incidents.length === 0) {
            list.innerHTML = '<div class="text-body-secondary">Geen incidenten gemeld op deze dag.</div>';
        } else {
            var wrapper = document.createElement('div');
            wrapper.className = 'list-group list-group-flush';

            incidents.forEach(function (inc) {
                var a = document.createElement('a');
                a.href = inc.link;
                a.className = 'list-group-item list-group-item-action';

                a.innerHTML =
                    '<div class="d-flex flex-wrap justify-content-between align-items-center gap-2">' +
                        '<div class="fw-medium">' + escapeHtml(inc.titel) + '</div>' +
                        '<div class="d-flex flex-wrap gap-2">' +
                            '<span class="badge rounded-pill ' + inc.statusBadgeClass + '">' + escapeHtml(inc.statusLabel) + '</span>' +
                            '<span class="badge rounded-pill ' + inc.prioriteitBadgeClass + '">' + escapeHtml(inc.prioriteitLabel) + '</span>' +
                        '</div>' +
                    '</div>';

                wrapper.appendChild(a);
            });

            list.appendChild(wrapper);
        }

        var modalEl = document.getElementById('incidentDayModal');
        var modal = bootstrap.Modal.getOrCreateInstance(modalEl);
        modal.show();
    }

    canvas.style.cursor = 'pointer';

    var barColor = getComputedStyle(document.documentElement).getPropertyValue('--color-primary').trim() || '#4FC1A6';

    new Chart(canvas, {
        type: 'bar',
        data: {
            labels: <?= json_encode($chartLabels) ?>,
            datasets: [{
                label: 'Gemelde incidenten',
                data: <?= json_encode($chartData) ?>,
                backgroundColor: barColor,
                borderRadius: 4,
                maxBarThickness: 18
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            },
            scales: {
                x: { grid: { display: false } },
                y: { beginAtZero: true, ticks: { precision: 0 } }
            },
            onClick: function (evt, elements) {
                if (!elements.length) {
                    return;
                }
                var index = elements[0].index;
                showIncidentsForDate(chartDates[index]);
            }
        }
    });
});
</script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var datumInput = document.getElementById('dashAgendaDatum');
    var lijst = document.getElementById('dashAgendaLijst');
    var modalEl = document.getElementById('dashAgendaModal');

    if (!datumInput || !modalEl) {
        return;
    }

    var modal = new bootstrap.Modal(modalEl);

    function vandaagStr() {
        var d = new Date();
        return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
    }

    function volgendeDagStr(dateStr) {
        var d = new Date(dateStr + 'T00:00:00');
        d.setDate(d.getDate() + 1);
        return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
    }

    function tijd(iso) {
        return iso.slice(11, 16);
    }

    function laadDag() {
        var datum = datumInput.value || vandaagStr();
        lijst.innerHTML = '<div class="text-body-secondary">Laden...</div>';

        fetch('/agenda/events?start=' + datum + 'T00:00:00&end=' + volgendeDagStr(datum) + 'T00:00:00')
            .then(function (r) { return r.json(); })
            .then(function (events) {
                if (!events.length) {
                    lijst.innerHTML = '<div class="text-body-secondary">Geen afspraken op deze dag.</div>';
                    return;
                }

                lijst.innerHTML = '';
                var wrapper = document.createElement('div');
                wrapper.className = 'list-group list-group-flush';

                events.forEach(function (ev) {
                    var row = document.createElement('button');
                    row.type = 'button';
                    row.className = 'list-group-item list-group-item-action agenda-item';

                    row.innerHTML =
                        '<div class="d-flex align-items-center gap-3">' +
                            '<span class="rounded-circle flex-shrink-0" style="width:10px;height:10px;background:' + ev.color + ';"></span>' +
                            '<span class="small text-body-secondary flex-shrink-0">' + tijd(ev.start) + '–' + tijd(ev.end) + '</span>' +
                            '<span class="text-truncate">' + ev.title.replace(/</g, '&lt;') + '</span>' +
                        '</div>';

                    row.addEventListener('click', function () {
                        openBewerken(ev);
                    });

                    wrapper.appendChild(row);
                });

                lijst.appendChild(wrapper);
            });
    }

    var verwijderBtn = document.getElementById('dashAgendaVerwijderBtn');
    var opslaanBtn = document.getElementById('dashAgendaOpslaanBtn');
    var magSchrijven = !!opslaanBtn;

    function openNieuw() {
        document.getElementById('dashAgendaModalTitel').textContent = 'Nieuwe afspraak';
        document.getElementById('dashAgendaId').value = '';
        document.getElementById('dashAgendaTitel').value = '';
        document.getElementById('dashAgendaLocatie').value = '';
        document.getElementById('dashAgendaStart').value = '09:00';
        document.getElementById('dashAgendaEind').value = '10:00';
        if (verwijderBtn) {
            verwijderBtn.classList.add('d-none');
        }
        modal.show();
    }

    function openBewerken(ev) {
        if (!magSchrijven) {
            return;
        }
        document.getElementById('dashAgendaModalTitel').textContent = 'Afspraak bewerken';
        document.getElementById('dashAgendaId').value = ev.id;
        document.getElementById('dashAgendaTitel').value = ev.title;
        document.getElementById('dashAgendaLocatie').value = ev.extendedProps.locatie || '';
        document.getElementById('dashAgendaStart').value = tijd(ev.start);
        document.getElementById('dashAgendaEind').value = tijd(ev.end);
        if (verwijderBtn) {
            verwijderBtn.classList.remove('d-none');
        }
        modal.show();
    }

    var nieuwBtn = document.getElementById('dashAgendaNieuwBtn');
    if (nieuwBtn) {
        nieuwBtn.addEventListener('click', openNieuw);
    }
    datumInput.addEventListener('change', laadDag);

    if (opslaanBtn) {
    opslaanBtn.addEventListener('click', function () {
        var datum = datumInput.value || vandaagStr();
        var id = document.getElementById('dashAgendaId').value;
        var titel = document.getElementById('dashAgendaTitel').value.trim();
        var start = document.getElementById('dashAgendaStart').value;
        var eind = document.getElementById('dashAgendaEind').value;

        if (!titel || !start || !eind) {
            window.alert('Titel, start en einde zijn verplicht.');
            return;
        }

        var payload = {
            titel: titel,
            start_op: datum + 'T' + start,
            eind_op: datum + 'T' + eind,
            locatie: document.getElementById('dashAgendaLocatie').value
        };

        if (!id) {
            payload.type = 'afspraak';
        }

        var url = id ? '/agenda/' + id : '/agenda';

        fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
        .then(function (r) { return r.json(); })
        .then(function (res) {
            if (res.success) {
                modal.hide();
                laadDag();
            } else {
                window.alert(res.error || 'Opslaan is mislukt.');
            }
        });
    });
    }

    if (verwijderBtn) {
    verwijderBtn.addEventListener('click', function () {
        var id = document.getElementById('dashAgendaId').value;
        if (!id || !window.confirm('Deze afspraak verwijderen?')) {
            return;
        }

        fetch('/agenda/' + id + '/verwijderen', { method: 'POST' })
            .then(function (r) { return r.json(); })
            .then(function () {
                modal.hide();
                laadDag();
            });
    });
    }

    datumInput.value = vandaagStr();
    laadDag();
});
</script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var startenWrap = document.getElementById('dashDagStartenWrap');
    var stoppenWrap = document.getElementById('dashDagStoppenWrap');
    if (!startenWrap || !stoppenWrap) {
        return;
    }

    // Bij succes stuurt de server een redirect terug; die volgen we bewust niet
    // (redirect: 'manual', herkenbaar aan type 'opaqueredirect'), zodat je op het dashboard blijft.
    function postZonderRedirect(url) {
        return fetch(url, { method: 'POST', body: new FormData(), redirect: 'manual' })
            .then(function (r) {
                if (!r.ok && r.type !== 'opaqueredirect') {
                    throw new Error('Actie is mislukt.');
                }
            });
    }

    var startForm = document.getElementById('dashDagStartenForm');
    var startModalEl = document.getElementById('dashDagStartenModal');
    var startModal = bootstrap.Modal.getOrCreateInstance(startModalEl);

    startForm.addEventListener('submit', function (e) {
        e.preventDefault();

        fetch(startForm.action, {
            method: 'POST',
            body: new FormData(startForm),
            redirect: 'manual'
        })
        .then(function (r) {
            if (!r.ok && r.type !== 'opaqueredirect') {
                throw new Error('Starten is mislukt.');
            }
            startModal.hide();
            startenWrap.classList.add('d-none');
            stoppenWrap.classList.remove('d-none');
        })
        .catch(function () {
            window.alert('Dag starten is mislukt.');
        });
    });

    document.getElementById('dashDagStoppenBtn').addEventListener('click', function () {
        postZonderRedirect('/urenstaat/stoppen')
            .then(function () {
                stoppenWrap.classList.add('d-none');
                startenWrap.classList.remove('d-none');
            })
            .catch(function () {
                window.alert('Dag afsluiten is mislukt.');
            });
    });
});
</script>