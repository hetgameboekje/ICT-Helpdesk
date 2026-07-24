import { api, ApiError } from '/assets/js/api/client.js';

/**
 * Hardware-uitgaven: lijst + detail in één split-view scherm, o.b.v.
 * src/routes/modules.hardware-uitgaven.tsx (Lovable MCP read_file, project
 * 4675b36f-276e-4fc5-9606-d83a98f9d801).
 *
 * Bewuste afwijking t.o.v. de mockup — zie HardwareUitgaveService voor de reden: dit is een
 * aankoopaanvraag-tracker (omschrijving/leverancier/bedrag/aankoopdatum, status aangevraagd→
 * goedgekeurd/afgekeurd→besteld→geleverd), niet het "hardware aan medewerker uitgeven met
 * retour"-concept dat de mockup toont (die mockdata hoort bij het aparte, al bestaande
 * App\Modules\Uitgifte). Geen "geretourneerd"-toggle of "Print uitgiftebon" — vervangen door een
 * statuswijziging tussen de 5 echte statussen.
 */

const STATUS_LABELS = {
    aangevraagd: 'Aangevraagd', goedgekeurd: 'Goedgekeurd', afgekeurd: 'Afgekeurd', besteld: 'Besteld', geleverd: 'Geleverd',
};

function esc(value) {
    const div = document.createElement('div');
    div.textContent = value ?? '';
    return div.innerHTML;
}
function statusBadge(status) {
    return `<span class="badge badge-${esc(status)}">${esc(STATUS_LABELS[status] || status)}</span>`;
}
function formatDatum(value) {
    if (!value) return '—';
    const [y, m, d] = String(value).slice(0, 10).split('-');
    return `${d}-${m}-${y}`;
}
function formatBedrag(value) {
    return '€ ' + Number(value || 0).toLocaleString('nl-NL', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

const root = document.getElementById('hardware-uitgave-app');

function pathId() {
    const m = window.location.pathname.match(/^\/hardware-uitgaven\/(\d+)$/);
    return m ? parseInt(m[1], 10) : null;
}

function getParams() {
    return new URLSearchParams(window.location.search);
}

function apiQuery() {
    const qs = getParams().toString();
    return '/api/v1/hardware-uitgaven' + (qs ? `?${qs}` : '');
}

let selectedId = pathId();
let currentItems = [];

function renderShell() {
    root.innerHTML = `
        <div class="page-header">
            <div class="page-title">Uitgaven hardware</div>
            <a class="btn btn-accent" href="/hardware-uitgaven/create"><i class="bi bi-plus-lg"></i> Nieuwe uitgave</a>
        </div>
        <section class="kpi-grid" id="hwKpis"></section>
        <div class="list-toolbar">
            <div class="search-input">
                <i class="bi bi-search"></i>
                <input type="text" id="hwZoekInput" placeholder="Zoek op omschrijving&hellip;">
            </div>
        </div>
        <div style="display:grid;grid-template-columns:minmax(0,1fr) 340px;gap:16px;align-items:start">
            <div class="card" style="padding:0;overflow:hidden">
                <div class="table-wrap"><table>
                    <thead><tr><th class="col-1">#</th><th>Omschrijving</th><th class="col-2">Leverancier</th><th class="col-1">Bedrag</th><th class="col-2">Status</th></tr></thead>
                    <tbody id="hwListBody"></tbody>
                </table></div>
            </div>
            <div class="card" id="hwDetail" style="padding:20px"></div>
        </div>
    `;

    document.getElementById('hwZoekInput').value = getParams().get('q') || '';
    document.getElementById('hwZoekInput').addEventListener('input', debounce(() => {
        const params = getParams();
        const q = document.getElementById('hwZoekInput').value.trim();
        if (q) { params.set('q', q); } else { params.delete('q'); }
        window.history.replaceState({}, '', '/hardware-uitgaven' + (params.toString() ? `?${params}` : ''));
        load();
    }, 300));
}

function debounce(fn, ms) {
    let t;
    return (...args) => { clearTimeout(t); t = setTimeout(() => fn(...args), ms); };
}

function renderKpis(statusCounts) {
    const cards = [
        { label: 'Aangevraagd', key: 'aangevraagd', icon: 'bi-inbox', tone: 'open' },
        { label: 'Goedgekeurd', key: 'goedgekeurd', icon: 'bi-check2-circle', tone: 'behandeling' },
        { label: 'Besteld', key: 'besteld', icon: 'bi-truck', tone: 'wachtend' },
        { label: 'Geleverd', key: 'geleverd', icon: 'bi-box-seam', tone: 'opgelost' },
    ];
    document.getElementById('hwKpis').innerHTML = cards.map((c) => `
        <div class="kpi-card">
            <div class="kpi-card-head"><span class="kpi-label">${esc(c.label)}</span>
                <span class="kpi-icon kpi-icon-${c.tone}"><i class="bi ${c.icon}"></i></span></div>
            <div class="kpi-value">${statusCounts[c.key] ?? 0}</div>
        </div>
    `).join('');
}

function renderList(items) {
    currentItems = items;
    const body = document.getElementById('hwListBody');

    if (items.length === 0) {
        body.innerHTML = '<tr><td colspan="5" class="empty-state">Geen hardware-uitgaven gevonden.</td></tr>';
        return;
    }

    if (selectedId === null || !items.some((h) => h.id === selectedId)) {
        selectedId = items[0].id;
    }

    body.innerHTML = items.map((h) => `
        <tr data-id="${h.id}" style="cursor:pointer${h.id === selectedId ? ';background:var(--color-background-secondary)' : ''}">
            <td class="col-1"><span class="mono" style="color:var(--color-text-tertiary)">#${h.id}</span></td>
            <td><span class="text-truncate d-block">${esc(h.omschrijving)}</span></td>
            <td class="col-2" style="color:var(--color-text-tertiary)">${esc(h.leverancier || '—')}</td>
            <td class="col-1">${formatBedrag(h.bedrag)}</td>
            <td class="col-2">${statusBadge(h.status)}</td>
        </tr>
    `).join('');

    body.querySelectorAll('tr[data-id]').forEach((tr) => {
        tr.addEventListener('click', () => selectItem(parseInt(tr.dataset.id, 10)));
    });
}

function selectItem(id) {
    selectedId = id;
    window.history.pushState({}, '', `/hardware-uitgaven/${id}`);
    renderList(currentItems);
    loadDetail(id);
}

window.addEventListener('popstate', () => {
    selectedId = pathId();
    renderList(currentItems);
    if (selectedId) loadDetail(selectedId);
});

function flash(message) {
    const detail = document.getElementById('hwDetail');
    const el = document.createElement('div');
    el.className = 'alert alert-error';
    el.textContent = message;
    detail.prepend(el);
    window.setTimeout(() => el.remove(), 4000);
}

let detailItem = null;

async function loadDetail(id) {
    const detail = document.getElementById('hwDetail');
    detail.innerHTML = '<div class="empty-state">Laden&hellip;</div>';

    try {
        const res = await api.get(`/api/v1/hardware-uitgaven/${id}`);
        detailItem = res.data.item;
        renderDetail();
    } catch (e) {
        const message = e instanceof ApiError ? e.message : 'Kon uitgave niet laden.';
        detail.innerHTML = `<div class="empty-state" style="color:var(--color-text-danger)">${esc(message)}</div>`;
    }
}

function renderDetail() {
    const item = detailItem;

    document.getElementById('hwDetail').innerHTML = `
        <div class="mono" style="font-size:11px;color:var(--color-text-tertiary)">#${item.id} &middot; ${formatDatum(item.aankoopdatum)}</div>
        <div style="font-size:16px;font-weight:600;margin-top:4px">${esc(item.omschrijving)}</div>
        <div style="margin-top:6px">${statusBadge(item.status)}</div>

        <div style="margin-top:16px;border-top:0.5px solid var(--color-border-tertiary);padding-top:12px">
            <div class="meta-row"><span class="meta-key">Leverancier</span><span>${esc(item.leverancier || '—')}</span></div>
            <div class="meta-row"><span class="meta-key">Bedrag</span><span>${formatBedrag(item.bedrag)}</span></div>
            <div class="meta-row"><span class="meta-key">Afdeling</span><span>${esc(item.afdeling_naam || '—')}</span></div>
            <div class="meta-row"><span class="meta-key">Aangevraagd door</span><span>${esc(item.aangevraagd_door_naam || '—')}</span></div>
        </div>

        <div style="margin-top:16px;border-top:0.5px solid var(--color-border-tertiary);padding-top:12px">
            <h3 class="detail-side-heading">Status wijzigen</h3>
            <div class="status-picker">
                ${Object.entries(STATUS_LABELS).map(([val, label]) => `
                    <button type="button" data-status="${val}" class="${item.status === val ? 'active' : ''}"
                        style="background:var(--color-background-secondary)">${esc(label)}</button>`).join('')}
            </div>
        </div>

        <div style="margin-top:14px">
            <a class="btn" href="/hardware-uitgaven/${item.id}/edit">Bewerken</a>
        </div>
    `;

    document.querySelectorAll('.status-picker button[data-status]').forEach((btn) => {
        btn.addEventListener('click', async () => {
            try {
                const res = await api.put(`/api/v1/hardware-uitgaven/${item.id}/status`, { status: btn.dataset.status });
                detailItem = res.data.item;
                renderDetail();
                renderList(currentItems.map((h) => (h.id === detailItem.id ? { ...h, status: detailItem.status } : h)));
            } catch (e) {
                flash(e instanceof ApiError ? e.message : 'Status wijzigen is mislukt.');
            }
        });
    });
}

async function load() {
    if (!document.getElementById('hwListBody')) {
        renderShell();
    }
    document.getElementById('hwListBody').innerHTML = '<tr><td colspan="5" class="empty-state">Laden&hellip;</td></tr>';

    try {
        const res = await api.get(apiQuery());
        renderKpis(res.meta.statusCounts);
        renderList(res.data);
        if (selectedId) {
            loadDetail(selectedId);
        }
    } catch (e) {
        const message = e instanceof ApiError ? e.message : 'Kon hardware-uitgaven niet laden.';
        document.getElementById('hwListBody').innerHTML =
            `<tr><td colspan="5" class="empty-state" style="color:var(--color-text-danger)">${esc(message)}</td></tr>`;
    }
}

renderShell();
load();
