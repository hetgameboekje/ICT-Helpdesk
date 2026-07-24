import { api, ApiError } from '/assets/js/api/client.js';

/**
 * Apparaten: lijst + detail in één split-view scherm, o.b.v. src/routes/modules.device.tsx
 * (Lovable MCP read_file, project 4675b36f-276e-4fc5-9606-d83a98f9d801).
 *
 * Bewuste afwijkingen t.o.v. de mockup — zie DeviceService voor de reden: het echte datamodel is
 * een fleet-inventarisatie op basis van CSV-geïmporteerde software, geen live monitoring:
 * - Geen online/offline-status, hostname/os/serie/type/locatie/"laatst gezien" (bestaat niet) —
 *   vervangen door de echte kolommen: naam, gekoppelde medewerker, aantal geïmporteerde
 *   software-items, laatst geïmporteerd op.
 * - Geen "Herstart"/"Wipe"-knoppen (geen remote device management in dit systeem) — vervangen door
 *   de geïmporteerde softwarelijst met zoekfilter (bestond al in de oude server-rendered show.php).
 * - KPI's tonen daarom aantal apparaten / gekoppeld aan medewerker / totaal software-items i.p.v.
 *   verzonnen online/reparatie-tellingen.
 * - De CSV-import-knoppen en "Nieuw apparaat"-link staan als statische markup in de shell-view
 *   (index.php/show.php), niet in dit bestand — bestandsupload hoort niet in de JSON-API-scope.
 */

function esc(value) {
    const div = document.createElement('div');
    div.textContent = value ?? '';
    return div.innerHTML;
}

function formatDatumTijd(value) {
    if (!value) return '—';
    const [datum, tijd] = String(value).split(' ');
    const [y, m, d] = String(datum).slice(0, 10).split('-');
    return `${d}-${m}-${y} ${(tijd || '').slice(0, 5)}`;
}

const appRoot = document.getElementById('device-app');

function pathId() {
    const m = window.location.pathname.match(/^\/apparaten\/(\d+)$/);
    return m ? parseInt(m[1], 10) : null;
}

function getParams() {
    return new URLSearchParams(window.location.search);
}

function apiQuery() {
    const qs = getParams().toString();
    return '/api/v1/apparaten' + (qs ? `?${qs}` : '');
}

let selectedId = pathId();
let currentItems = [];

function renderShell() {
    appRoot.innerHTML = `
        <section class="kpi-grid" id="devKpis"></section>
        <div class="list-toolbar">
            <div class="search-input">
                <i class="bi bi-search"></i>
                <input type="text" id="devZoekInput" placeholder="Zoek op naam of medewerker&hellip;">
            </div>
        </div>
        <div style="display:grid;grid-template-columns:minmax(0,1fr) 360px;gap:16px;align-items:start">
            <div class="card" style="padding:0;overflow:hidden">
                <div class="table-wrap"><table>
                    <thead><tr><th>Naam</th><th class="col-2">Medewerker</th><th class="col-1">Software</th><th class="col-2">Laatst geïmporteerd</th></tr></thead>
                    <tbody id="devListBody"></tbody>
                </table></div>
            </div>
            <div class="card" id="devDetail" style="padding:20px"></div>
        </div>
    `;

    document.getElementById('devZoekInput').value = getParams().get('q') || '';
    document.getElementById('devZoekInput').addEventListener('input', debounce(() => {
        const params = getParams();
        const q = document.getElementById('devZoekInput').value.trim();
        if (q) { params.set('q', q); } else { params.delete('q'); }
        window.history.replaceState({}, '', '/apparaten' + (params.toString() ? `?${params}` : ''));
        load();
    }, 300));
}

function debounce(fn, ms) {
    let t;
    return (...args) => { clearTimeout(t); t = setTimeout(() => fn(...args), ms); };
}

function renderKpis(stats) {
    const cards = [
        { label: 'Apparaten', value: stats.totaal, icon: 'bi-laptop', tone: 'behandeling' },
        { label: 'Gekoppeld aan medewerker', value: stats.gekoppeld, icon: 'bi-person-check', tone: 'opgelost' },
        { label: 'Software-items totaal', value: stats.softwareTotaal, icon: 'bi-app-indicator', tone: 'open' },
        { label: 'Nog geen import', value: stats.zonderImport, icon: 'bi-exclamation-triangle', tone: 'wachtend' },
    ];
    document.getElementById('devKpis').innerHTML = cards.map((c) => `
        <div class="kpi-card">
            <div class="kpi-card-head"><span class="kpi-label">${esc(c.label)}</span>
                <span class="kpi-icon kpi-icon-${c.tone}"><i class="bi ${c.icon}"></i></span></div>
            <div class="kpi-value">${c.value}</div>
        </div>
    `).join('');
}

function renderList(items) {
    currentItems = items;
    const body = document.getElementById('devListBody');

    if (items.length === 0) {
        body.innerHTML = '<tr><td colspan="4" class="empty-state">Geen apparaten gevonden.</td></tr>';
        return;
    }

    if (selectedId === null || !items.some((d) => d.id === selectedId)) {
        selectedId = items[0].id;
    }

    body.innerHTML = items.map((d) => `
        <tr data-id="${d.id}" style="cursor:pointer${d.id === selectedId ? ';background:var(--color-background-secondary)' : ''}">
            <td><span class="mono" style="font-size:12.5px;font-weight:500">${esc(d.naam)}</span></td>
            <td class="col-2">${esc(d.medewerker_naam || '—')}</td>
            <td class="col-1">${parseInt(d.software_aantal || 0, 10)}</td>
            <td class="col-2" style="color:var(--color-text-tertiary)">${formatDatumTijd(d.laatst_geimporteerd_op)}</td>
        </tr>
    `).join('');

    body.querySelectorAll('tr[data-id]').forEach((tr) => {
        tr.addEventListener('click', () => selectItem(parseInt(tr.dataset.id, 10)));
    });
}

function selectItem(id) {
    selectedId = id;
    window.history.pushState({}, '', `/apparaten/${id}`);
    renderList(currentItems);
    loadDetail(id);
}

window.addEventListener('popstate', () => {
    selectedId = pathId();
    renderList(currentItems);
    if (selectedId) loadDetail(selectedId);
});

async function loadDetail(id) {
    const detail = document.getElementById('devDetail');
    detail.innerHTML = '<div class="empty-state">Laden&hellip;</div>';

    try {
        const res = await api.get(`/api/v1/apparaten/${id}`);
        renderDetail(res.data.item, res.data.software);
    } catch (e) {
        const message = e instanceof ApiError ? e.message : 'Kon apparaat niet laden.';
        detail.innerHTML = `<div class="empty-state" style="color:var(--color-text-danger)">${esc(message)}</div>`;
    }
}

function renderDetail(item, software) {
    document.getElementById('devDetail').innerHTML = `
        <div class="mono" style="font-weight:600;font-size:14px">${esc(item.naam)}</div>
        <div style="font-size:12px;color:var(--color-text-tertiary);margin-top:2px">
            ${item.medewerker_id ? `<a href="/medewerkers/${item.medewerker_id}">${esc(item.medewerker_naam)}</a>` : 'Niet gekoppeld aan medewerker'}
        </div>

        <div style="margin-top:14px;border-top:0.5px solid var(--color-border-tertiary);padding-top:12px">
            <div class="meta-row"><span class="meta-key">Apparaat-ID</span><span>${esc(item.extern_apparaat_id || '—')}</span></div>
            <div class="meta-row"><span class="meta-key">Laatst geïmporteerd</span><span>${formatDatumTijd(item.laatst_geimporteerd_op)}</span></div>
        </div>

        <div style="margin-top:14px;border-top:0.5px solid var(--color-border-tertiary);padding-top:12px">
            <h3 class="detail-side-heading">Software (${software.length})</h3>
            ${software.length ? `<input type="text" id="devSoftwareZoek" placeholder="Zoeken op naam of uitgever&hellip;" style="margin-bottom:8px">` : ''}
            <div id="devSoftwareList" style="max-height:260px;overflow-y:auto">
                ${software.length === 0 ? '<div class="empty-state">Nog geen software geïmporteerd.</div>' : software.map((s) => `
                    <div class="log-item software-row" data-zoek="${esc(((s.naam || '') + ' ' + (s.publisher || '')).toLowerCase())}">
                        <div class="log-meta"><span class="log-user">${esc(s.naam)}</span><span class="log-time">${esc(s.versie || '—')}</span></div>
                        <div class="log-text">${esc(s.publisher || '—')}</div>
                    </div>
                `).join('')}
            </div>
        </div>

        <div style="display:flex;gap:6px;margin-top:14px">
            <a class="btn" href="/apparaten/${item.id}/edit">Bewerken</a>
        </div>
    `;

    const zoekInput = document.getElementById('devSoftwareZoek');
    if (zoekInput) {
        zoekInput.addEventListener('input', () => {
            const q = zoekInput.value.trim().toLowerCase();
            document.querySelectorAll('.software-row').forEach((row) => {
                row.style.display = row.dataset.zoek.indexOf(q) === -1 ? 'none' : '';
            });
        });
    }
}

async function load() {
    if (!document.getElementById('devListBody')) {
        renderShell();
    }
    document.getElementById('devListBody').innerHTML = '<tr><td colspan="4" class="empty-state">Laden&hellip;</td></tr>';

    try {
        const res = await api.get(apiQuery());
        renderKpis(res.meta.stats);
        renderList(res.data);
        if (selectedId) {
            loadDetail(selectedId);
        }
    } catch (e) {
        const message = e instanceof ApiError ? e.message : 'Kon apparaten niet laden.';
        document.getElementById('devListBody').innerHTML =
            `<tr><td colspan="4" class="empty-state" style="color:var(--color-text-danger)">${esc(message)}</td></tr>`;
    }
}

renderShell();
load();
