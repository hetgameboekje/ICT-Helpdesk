import { api, ApiError } from '/assets/js/api/client.js';

/**
 * Voorraad: lijst + detail in één split-view scherm, o.b.v. src/routes/modules.voorraad.tsx
 * (Lovable MCP read_file, project 4675b36f-276e-4fc5-9606-d83a98f9d801).
 *
 * Bewuste afwijkingen t.o.v. de mockup — zie VoorraadService voor de reden: dit is een per-stuk
 * (barcode/serienummer) registratie, geen aantal/minimum-voorraadmodel:
 * - Geen "aantal/minimum"-voorraadbalk en geen "onder minimum"-KPI: die drempel bestaat niet in
 *   het datamodel. KPI's tonen in plaats daarvan de echte statusverdeling (op voorraad/uitgegeven/
 *   afgeschreven).
 * - Geen "Bijboeken/Afboeken"-knoppen (aantal-mutaties bestaan niet) — vervangen door een
 *   statuswijziging (op_voorraad/uitgegeven/afgeschreven), een actie die wel echt bestaat
 *   (VoorraadItemModel::setStatus()).
 * - Geen verzonnen "Mutatiehistorie" — vervangen door de echte itemdetails (serienummer, locatie,
 *   gekoppeld apparaat) en een link naar de barcode-printpagina.
 * - "Nieuw artikel"/"Scan" linken door naar de bestaande server-rendered formulieren
 *   (/voorraad/create met DxDiag-upload en serienummer-batches) — dat blijft buiten deze
 *   JSON-API-scope, net als tickets-import/export.
 */

function esc(value) {
    const div = document.createElement('div');
    div.textContent = value ?? '';
    return div.innerHTML;
}

const STATUS_LABELS = { op_voorraad: 'Op voorraad', uitgegeven: 'Uitgegeven', afgeschreven: 'Afgeschreven' };
const STATUS_BADGE_CLASS = { op_voorraad: 'open', uitgegeven: 'gesloten', afgeschreven: 'keyuser' };

function statusBadge(status) {
    return `<span class="badge badge-${STATUS_BADGE_CLASS[status] || 'gesloten'}">${esc(STATUS_LABELS[status] || status)}</span>`;
}

const root = document.getElementById('voorraad-app');

function pathId() {
    const m = window.location.pathname.match(/^\/voorraad\/(\d+)$/);
    return m ? parseInt(m[1], 10) : null;
}

function getParams() {
    return new URLSearchParams(window.location.search);
}

function apiQuery() {
    const qs = getParams().toString();
    return '/api/v1/voorraad' + (qs ? `?${qs}` : '');
}

let selectedId = pathId();
let currentItems = [];

function renderShell() {
    root.innerHTML = `
        <div class="page-header"><div class="page-title">Voorraad</div></div>
        <section class="kpi-grid" id="vrKpis"></section>
        <div class="list-toolbar">
            <div class="search-input">
                <i class="bi bi-search"></i>
                <input type="text" id="vrZoekInput" placeholder="Zoek op barcode of type&hellip;">
            </div>
            <select id="vrStatusSelect"><option value="">Alle statussen</option></select>
            <a class="btn btn-accent" href="/voorraad/create"><i class="bi bi-plus-lg"></i> Item toevoegen</a>
        </div>
        <div style="display:grid;grid-template-columns:minmax(0,1fr) 360px;gap:16px;align-items:start">
            <div class="card" style="padding:0;overflow:hidden">
                <div class="table-wrap"><table>
                    <thead><tr><th>Artikel</th><th class="col-2">Status</th><th class="col-2">Locatie</th></tr></thead>
                    <tbody id="vrListBody"></tbody>
                </table></div>
            </div>
            <div class="card" id="vrDetail" style="padding:20px"></div>
        </div>
    `;

    document.getElementById('vrZoekInput').value = getParams().get('q') || '';
    document.getElementById('vrZoekInput').addEventListener('input', debounce(() => {
        const params = getParams();
        const q = document.getElementById('vrZoekInput').value.trim();
        if (q) { params.set('q', q); } else { params.delete('q'); }
        window.history.replaceState({}, '', '/voorraad' + (params.toString() ? `?${params}` : ''));
        load();
    }, 300));

    document.getElementById('vrStatusSelect').addEventListener('change', (e) => {
        const params = getParams();
        if (e.target.value) { params.set('status', e.target.value); } else { params.delete('status'); }
        window.history.replaceState({}, '', '/voorraad' + (params.toString() ? `?${params}` : ''));
        load();
    });
}

function debounce(fn, ms) {
    let t;
    return (...args) => { clearTimeout(t); t = setTimeout(() => fn(...args), ms); };
}

function renderKpis(statusCounts) {
    const cards = [
        { label: 'Totaal items', value: statusCounts.alle, icon: 'bi-boxes', tone: 'behandeling' },
        { label: 'Op voorraad', value: statusCounts.op_voorraad, icon: 'bi-box-seam', tone: 'opgelost' },
        { label: 'Uitgegeven', value: statusCounts.uitgegeven, icon: 'bi-person-check', tone: 'wachtend' },
        { label: 'Afgeschreven', value: statusCounts.afgeschreven, icon: 'bi-trash', tone: 'gesloten' },
    ];
    document.getElementById('vrKpis').innerHTML = cards.map((c) => `
        <div class="kpi-card">
            <div class="kpi-card-head"><span class="kpi-label">${esc(c.label)}</span>
                <span class="kpi-icon kpi-icon-${c.tone}"><i class="bi ${c.icon}"></i></span></div>
            <div class="kpi-value">${c.value ?? 0}</div>
        </div>
    `).join('');
}

function renderStatusOptions(filterOptions) {
    const select = document.getElementById('vrStatusSelect');
    const current = getParams().get('status') || '';
    const opts = Object.entries(filterOptions.status).map(([val, label]) =>
        `<option value="${esc(val)}"${current === val ? ' selected' : ''}>${esc(label)}</option>`).join('');
    select.innerHTML = `<option value="">Alle statussen</option>${opts}`;
}

function renderList(items) {
    currentItems = items;
    const body = document.getElementById('vrListBody');

    if (items.length === 0) {
        body.innerHTML = '<tr><td colspan="3" class="empty-state">Geen voorraaditems gevonden.</td></tr>';
        return;
    }

    if (selectedId === null || !items.some((v) => v.id === selectedId)) {
        selectedId = items[0].id;
    }

    body.innerHTML = items.map((v) => `
        <tr data-id="${v.id}" style="cursor:pointer${v.id === selectedId ? ';background:var(--color-background-secondary)' : ''}">
            <td>
                <div class="mono" style="font-size:10px;color:var(--color-text-tertiary)">${esc(v.barcode)}</div>
                <div style="font-weight:500;margin-top:2px">${esc(v.type_naam || '—')}${v.variant ? ' &middot; ' + esc(v.variant) : ''}</div>
            </td>
            <td class="col-2">${statusBadge(v.status)}</td>
            <td class="col-2" style="color:var(--color-text-tertiary)">${esc(v.locatie || '—')}</td>
        </tr>
    `).join('');

    body.querySelectorAll('tr[data-id]').forEach((tr) => {
        tr.addEventListener('click', () => selectItem(parseInt(tr.dataset.id, 10)));
    });
}

function selectItem(id) {
    selectedId = id;
    window.history.pushState({}, '', `/voorraad/${id}`);
    renderList(currentItems);
    loadDetail(id);
}

window.addEventListener('popstate', () => {
    selectedId = pathId();
    renderList(currentItems);
    if (selectedId) loadDetail(selectedId);
});

function flash(message) {
    const detail = document.getElementById('vrDetail');
    const el = document.createElement('div');
    el.className = 'alert alert-error';
    el.textContent = message;
    detail.prepend(el);
    window.setTimeout(() => el.remove(), 4000);
}

let detailItem = null;

async function loadDetail(id) {
    const detail = document.getElementById('vrDetail');
    detail.innerHTML = '<div class="empty-state">Laden&hellip;</div>';

    try {
        const res = await api.get(`/api/v1/voorraad/${id}`);
        detailItem = res.data.item;
        renderDetail();
    } catch (e) {
        const message = e instanceof ApiError ? e.message : 'Kon item niet laden.';
        detail.innerHTML = `<div class="empty-state" style="color:var(--color-text-danger)">${esc(message)}</div>`;
    }
}

function renderDetail() {
    const item = detailItem;

    document.getElementById('vrDetail').innerHTML = `
        <div class="mono" style="font-size:10px;color:var(--color-text-tertiary)">${esc(item.barcode)}</div>
        <div style="font-size:16px;font-weight:600;margin-top:2px">${esc(item.type_naam || '—')}${item.variant ? ' &middot; ' + esc(item.variant) : ''}</div>
        <div style="margin-top:6px">${statusBadge(item.status)}</div>

        <div style="margin-top:16px;border-top:0.5px solid var(--color-border-tertiary);padding-top:12px">
            <div class="meta-row"><span class="meta-key">Serienummer</span><span>${esc(item.serienummer || '—')}</span></div>
            <div class="meta-row"><span class="meta-key">Locatie</span><span>${esc(item.locatie || '—')}</span></div>
            ${item.device_naam ? `<div class="meta-row"><span class="meta-key">Apparaat</span><span><a href="/apparaten/${item.device_id}">${esc(item.device_naam)}</a></span></div>` : ''}
            <div class="meta-row"><span class="meta-key">Opmerking</span><span>${esc(item.opmerking || '—')}</span></div>
            <div class="meta-row"><span class="meta-key">Toegevoegd</span><span>${esc(String(item.created_at || '').slice(0, 10))}</span></div>
        </div>

        <div style="margin-top:16px;border-top:0.5px solid var(--color-border-tertiary);padding-top:12px">
            <h3 class="detail-side-heading">Status wijzigen</h3>
            <div class="status-picker">
                ${Object.entries(STATUS_LABELS).map(([val, label]) => `
                    <button type="button" data-status="${val}" class="${item.status === val ? 'active' : ''}"
                        style="background:var(--color-background-secondary)">${esc(label)}</button>`).join('')}
            </div>
        </div>

        <div style="display:flex;flex-direction:column;gap:6px;margin-top:16px">
            ${item.status === 'op_voorraad' ? `<a class="btn btn-accent" href="/uitgiften/create?barcode=${encodeURIComponent(item.barcode)}">Toewijzen aan medewerker</a>` : ''}
            <a class="btn" href="/voorraad/${item.id}/barcode" target="_blank">Barcode printen</a>
            <a class="btn" href="/voorraad/${item.id}/edit">Bewerken</a>
        </div>
    `;

    document.querySelectorAll('.status-picker button[data-status]').forEach((btn) => {
        btn.addEventListener('click', async () => {
            try {
                const res = await api.put(`/api/v1/voorraad/${item.id}/status`, { status: btn.dataset.status });
                detailItem = res.data.item;
                renderDetail();
                renderList(currentItems.map((v) => (v.id === detailItem.id ? { ...v, status: detailItem.status } : v)));
            } catch (e) {
                flash(e instanceof ApiError ? e.message : 'Status wijzigen is mislukt.');
            }
        });
    });
}

async function load() {
    if (!document.getElementById('vrListBody')) {
        renderShell();
    }
    document.getElementById('vrListBody').innerHTML = '<tr><td colspan="3" class="empty-state">Laden&hellip;</td></tr>';

    try {
        const res = await api.get(apiQuery());
        renderKpis(res.meta.statusCounts);
        renderStatusOptions(res.meta.filterOptions);
        renderList(res.data);
        if (selectedId) {
            loadDetail(selectedId);
        }
    } catch (e) {
        const message = e instanceof ApiError ? e.message : 'Kon voorraad niet laden.';
        document.getElementById('vrListBody').innerHTML =
            `<tr><td colspan="3" class="empty-state" style="color:var(--color-text-danger)">${esc(message)}</td></tr>`;
    }
}

renderShell();
load();
