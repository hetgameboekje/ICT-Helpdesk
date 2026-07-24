import { api, ApiError } from '/assets/js/api/client.js';

/**
 * Uitgifte: lijst + detail in één split-view scherm, o.b.v. src/routes/modules.uitgifte.tsx
 * (Lovable MCP read_file, project 4675b36f-276e-4fc5-9606-d83a98f9d801).
 *
 * Bewuste afwijking t.o.v. de mockup: geen "soort"-tabs (hardware/telefoon/toegangspas/overig) —
 * die classificatie leeft op het gekoppelde voorraad_items.type_id, niet op het uitgifte-record
 * zelf, dus vervangen door een status-filter (uitgegeven/geretourneerd), een echte kolom
 * (UitgifteModel::SELECT). Retour nemen (met resultaat op_voorraad/afgeschreven + opmerking) is de
 * enige mutatie op een uitgifte — er is bewust geen edit/destroy-route (permanente historie).
 */

function esc(value) {
    const div = document.createElement('div');
    div.textContent = value ?? '';
    return div.innerHTML;
}
function formatDatum(value) {
    if (!value) return '—';
    const [y, m, d] = String(value).slice(0, 10).split('-');
    return `${d}-${m}-${y}`;
}
function statusBadge(status) {
    return status === 'uitgegeven'
        ? '<span class="badge badge-in_behandeling">Uitgegeven</span>'
        : '<span class="badge badge-opgelost">Geretourneerd</span>';
}

const root = document.getElementById('uitgifte-app');

function pathId() {
    const m = window.location.pathname.match(/^\/uitgiften\/(\d+)$/);
    return m ? parseInt(m[1], 10) : null;
}

function getParams() {
    return new URLSearchParams(window.location.search);
}

function apiQuery() {
    const qs = getParams().toString();
    return '/api/v1/uitgiften' + (qs ? `?${qs}` : '');
}

let selectedId = pathId();
let currentItems = [];

function renderShell() {
    root.innerHTML = `
        <div class="page-header">
            <div class="page-title">Uitgifte</div>
            <a class="btn btn-accent" href="/uitgiften/create"><i class="bi bi-plus-lg"></i> Item toewijzen</a>
        </div>
        <section class="kpi-grid" id="uitKpis"></section>
        <div class="list-toolbar">
            <div class="search-input">
                <i class="bi bi-search"></i>
                <input type="text" id="uitZoekInput" placeholder="Zoek op medewerker of item&hellip;">
            </div>
            <select id="uitStatusSelect"><option value="">Alle statussen</option></select>
        </div>
        <div style="display:grid;grid-template-columns:minmax(0,1fr) 340px;gap:16px;align-items:start">
            <div class="card" style="padding:0;overflow:hidden">
                <div class="table-wrap"><table>
                    <thead><tr><th class="col-1">#</th><th class="col-2">Medewerker</th><th>Item</th><th class="col-2">Uitgegeven op</th><th class="col-2">Status</th></tr></thead>
                    <tbody id="uitListBody"></tbody>
                </table></div>
            </div>
            <div class="card" id="uitDetail" style="padding:20px"></div>
        </div>
    `;

    document.getElementById('uitZoekInput').value = getParams().get('q') || '';
    document.getElementById('uitZoekInput').addEventListener('input', debounce(() => {
        const params = getParams();
        const q = document.getElementById('uitZoekInput').value.trim();
        if (q) { params.set('q', q); } else { params.delete('q'); }
        window.history.replaceState({}, '', '/uitgiften' + (params.toString() ? `?${params}` : ''));
        load();
    }, 300));

    document.getElementById('uitStatusSelect').addEventListener('change', (e) => {
        const params = getParams();
        if (e.target.value) { params.set('status', e.target.value); } else { params.delete('status'); }
        window.history.replaceState({}, '', '/uitgiften' + (params.toString() ? `?${params}` : ''));
        load();
    });
}

function debounce(fn, ms) {
    let t;
    return (...args) => { clearTimeout(t); t = setTimeout(() => fn(...args), ms); };
}

function renderKpis(stats) {
    const cards = [
        { label: 'Totaal uitgiften', value: stats.totaal, icon: 'bi-box-seam', tone: 'open' },
        { label: 'Openstaand', value: stats.openstaand, icon: 'bi-person-check', tone: 'behandeling' },
        { label: 'Deze week', value: stats.dezeWeek, icon: 'bi-calendar-week', tone: 'wachtend' },
        { label: 'Geretourneerd', value: stats.geretourneerd, icon: 'bi-arrow-return-left', tone: 'opgelost' },
    ];
    document.getElementById('uitKpis').innerHTML = cards.map((c) => `
        <div class="kpi-card">
            <div class="kpi-card-head"><span class="kpi-label">${esc(c.label)}</span>
                <span class="kpi-icon kpi-icon-${c.tone}"><i class="bi ${c.icon}"></i></span></div>
            <div class="kpi-value">${c.value}</div>
        </div>
    `).join('');
}

function renderStatusOptions(filterOptions) {
    const select = document.getElementById('uitStatusSelect');
    const current = getParams().get('status') || '';
    const opts = Object.entries(filterOptions.status).map(([val, label]) =>
        `<option value="${esc(val)}"${current === val ? ' selected' : ''}>${esc(label)}</option>`).join('');
    select.innerHTML = `<option value="">Alle statussen</option>${opts}`;
}

function renderList(items) {
    currentItems = items;
    const body = document.getElementById('uitListBody');

    if (items.length === 0) {
        body.innerHTML = '<tr><td colspan="5" class="empty-state">Geen uitgiften gevonden.</td></tr>';
        return;
    }

    if (selectedId === null || !items.some((u) => u.id === selectedId)) {
        selectedId = items[0].id;
    }

    body.innerHTML = items.map((u) => `
        <tr data-id="${u.id}" style="cursor:pointer${u.id === selectedId ? ';background:var(--color-background-secondary)' : ''}">
            <td class="col-1"><span class="mono" style="color:var(--color-text-tertiary)">#${u.id}</span></td>
            <td class="col-2">${esc(u.medewerker_naam)}</td>
            <td>${esc(u.type_naam || '—')}${u.variant ? ' (' + esc(u.variant) + ')' : ''} <span class="mono" style="font-size:10px;color:var(--color-text-tertiary)">${esc(u.barcode || '')}</span></td>
            <td class="col-2" style="color:var(--color-text-tertiary)">${formatDatum(u.uitgegeven_op)}</td>
            <td class="col-2">${statusBadge(u.status)}</td>
        </tr>
    `).join('');

    body.querySelectorAll('tr[data-id]').forEach((tr) => {
        tr.addEventListener('click', () => selectItem(parseInt(tr.dataset.id, 10)));
    });
}

function selectItem(id) {
    selectedId = id;
    window.history.pushState({}, '', `/uitgiften/${id}`);
    renderList(currentItems);
    loadDetail(id);
}

window.addEventListener('popstate', () => {
    selectedId = pathId();
    renderList(currentItems);
    if (selectedId) loadDetail(selectedId);
});

function flash(message) {
    const detail = document.getElementById('uitDetail');
    const el = document.createElement('div');
    el.className = 'alert alert-error';
    el.textContent = message;
    detail.prepend(el);
    window.setTimeout(() => el.remove(), 4000);
}

let detailItem = null;

async function loadDetail(id) {
    const detail = document.getElementById('uitDetail');
    detail.innerHTML = '<div class="empty-state">Laden&hellip;</div>';

    try {
        const res = await api.get(`/api/v1/uitgiften/${id}`);
        detailItem = res.data.item;
        renderDetail();
    } catch (e) {
        const message = e instanceof ApiError ? e.message : 'Kon uitgifte niet laden.';
        detail.innerHTML = `<div class="empty-state" style="color:var(--color-text-danger)">${esc(message)}</div>`;
    }
}

function renderDetail() {
    const item = detailItem;

    document.getElementById('uitDetail').innerHTML = `
        <div style="display:flex;align-items:start;justify-content:space-between;gap:12px">
            <div>
                <div style="font-size:15px;font-weight:600">${esc(item.type_naam || 'Item')}${item.variant ? ' (' + esc(item.variant) + ')' : ''}</div>
                <div style="font-size:12px;color:var(--color-text-tertiary);margin-top:2px">&rarr; ${esc(item.medewerker_naam)}</div>
            </div>
            ${statusBadge(item.status)}
        </div>

        <div style="margin-top:16px;border-top:0.5px solid var(--color-border-tertiary);padding-top:12px">
            <div class="meta-row"><span class="meta-key">Barcode</span><span class="mono">${esc(item.barcode || '—')}</span></div>
            <div class="meta-row"><span class="meta-key">Serienummer</span><span>${esc(item.serienummer || '—')}</span></div>
            <div class="meta-row"><span class="meta-key">Uitgegeven op</span><span>${formatDatum(item.uitgegeven_op)}</span></div>
            <div class="meta-row"><span class="meta-key">Teruggegeven op</span><span>${formatDatum(item.teruggegeven_op)}</span></div>
            <div class="meta-row"><span class="meta-key">Opmerking</span><span>${esc(item.opmerking || '—')}</span></div>
            <div class="meta-row"><span class="meta-key">Toestemming manager</span><span>${item.toestemming_manager ? 'Ja' : 'Nee'}</span></div>
            ${item.status !== 'uitgegeven' ? `<div class="meta-row"><span class="meta-key">Retour opmerking</span><span>${esc(item.retour_opmerking || '—')}</span></div>` : ''}
        </div>

        ${item.status === 'uitgegeven' ? `
        <div style="margin-top:16px;border-top:0.5px solid var(--color-border-tertiary);padding-top:12px">
            <h3 class="detail-side-heading">Retour nemen</h3>
            <div class="form-group" style="margin-bottom:8px">
                <label style="display:flex;align-items:center;gap:6px;font-weight:normal;margin-bottom:4px">
                    <input type="radio" name="uitResultaat" value="op_voorraad" checked> Terug op voorraad
                </label>
                <label style="display:flex;align-items:center;gap:6px;font-weight:normal">
                    <input type="radio" name="uitResultaat" value="afgeschreven"> Afschrijven (defect / einde levensduur)
                </label>
            </div>
            <textarea id="uitRetourOpmerking" placeholder="Opmerking over staat (optioneel)&hellip;" style="margin-bottom:8px"></textarea>
            <button class="btn btn-accent" type="button" id="uitRetourBtn" style="width:100%;justify-content:center">Retour nemen</button>
        </div>` : ''}
    `;

    const retourBtn = document.getElementById('uitRetourBtn');
    if (retourBtn) {
        retourBtn.addEventListener('click', async () => {
            const resultaat = document.querySelector('input[name="uitResultaat"]:checked').value;
            const opmerking = document.getElementById('uitRetourOpmerking').value.trim();
            if (!window.confirm('Retour nemen?')) return;

            try {
                const res = await api.post(`/api/v1/uitgiften/${item.id}/retour`, { resultaat, opmerking });
                detailItem = res.data.item;
                renderDetail();
                renderList(currentItems.map((u) => (u.id === detailItem.id ? { ...u, status: detailItem.status } : u)));
            } catch (e) {
                flash(e instanceof ApiError ? e.message : 'Retour nemen is mislukt.');
            }
        });
    }
}

async function load() {
    if (!document.getElementById('uitListBody')) {
        renderShell();
    }
    document.getElementById('uitListBody').innerHTML = '<tr><td colspan="5" class="empty-state">Laden&hellip;</td></tr>';

    try {
        const res = await api.get(apiQuery());
        renderKpis(res.meta.stats);
        renderStatusOptions(res.meta.filterOptions);
        renderList(res.data);
        if (selectedId) {
            loadDetail(selectedId);
        }
    } catch (e) {
        const message = e instanceof ApiError ? e.message : 'Kon uitgiften niet laden.';
        document.getElementById('uitListBody').innerHTML =
            `<tr><td colspan="5" class="empty-state" style="color:var(--color-text-danger)">${esc(message)}</td></tr>`;
    }
}

renderShell();
load();
