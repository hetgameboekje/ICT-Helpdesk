import { api, ApiError } from '/assets/js/api/client.js';
import { sortableHeaderHtml, bindSortableHeaders } from '/assets/js/table-sort.js';
import { actionMenuHtml, wireActionMenu, openSheet } from '/assets/js/ui/panel-menu.js';

/**
 * Voorraad: lijst + item in één split-view scherm, zoals in het Lovable-ontwerp
 * (src/routes/modules.voorraad.tsx, via de Lovable MCP). Vervangt de eerdere server-rendered
 * index.php/show.php-inhoud.
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
 *
 * Klikbaar sorteren op de kolomkoppen (Artikel/Status/Locatie) is geen onderdeel van de mockup —
 * zie public/assets/js/table-sort.js voor de onderbouwing van die bewuste uitbreiding.
 */

function esc(value) {
    const div = document.createElement('div');
    div.textContent = value ?? '';
    return div.innerHTML;
}

const STATUS_LABELS = { op_voorraad: 'Op voorraad', uitgegeven: 'Uitgegeven', afgeschreven: 'Afgeschreven' };
const STATUS_BADGE_CLASS = { op_voorraad: 'stored', uitgegeven: 'in_behandeling', afgeschreven: 'afgekeurd' };

const root = document.getElementById('voorraad-app');

function pathId() {
    const m = window.location.pathname.match(/^\/voorraad\/(\d+)$/);
    return m ? parseInt(m[1], 10) : null;
}

function getParams() {
    return new URLSearchParams(window.location.search);
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
                <input type="text" id="vrZoekInput" placeholder="Zoek op barcode of naam&hellip;">
            </div>
            <a class="btn" href="/voorraad/barcode-templates"><i class="bi bi-upc-scan"></i> Barcode-sjablonen</a>
            <a class="btn btn-accent" href="/voorraad/create"><i class="bi bi-plus-lg"></i> Nieuw artikel</a>
        </div>
        <div class="kb-split" style="display:grid;grid-template-columns:minmax(0,1fr) 360px;gap:16px;align-items:start">
            <div class="card" style="padding:0;overflow:hidden">
                <div class="table-wrap"><table>
                    <thead id="vrThead"></thead>
                    <tbody id="vrListBody"></tbody>
                </table></div>
            </div>
            <div class="card" id="vrDetail" style="padding:24px"></div>
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

    bindSortableHeaders(document.getElementById('vrThead'), (column, dir) => {
        const params = getParams();
        params.set('sort', column);
        params.set('dir', dir);
        window.history.replaceState({}, '', '/voorraad' + (params.toString() ? `?${params}` : ''));
        load();
    });
}

function renderTableHead() {
    const params = getParams();
    const currentSort = params.get('sort');
    const currentDir = params.get('dir') || 'asc';
    document.getElementById('vrThead').innerHTML = `<tr>
        <th>${sortableHeaderHtml('type_naam', 'Artikel', currentSort, currentDir)}</th>
        <th class="col-2">${sortableHeaderHtml('status', 'Status', currentSort, currentDir)}</th>
        <th class="col-2">${sortableHeaderHtml('locatie', 'Locatie', currentSort, currentDir)}</th>
    </tr>`;
}

function debounce(fn, ms) {
    let t;
    return (...args) => { clearTimeout(t); t = setTimeout(() => fn(...args), ms); };
}

function statusBadge(status) {
    return `<span class="badge badge-${STATUS_BADGE_CLASS[status] || 'stored'}">${esc(STATUS_LABELS[status] || status)}</span>`;
}

function renderKpis(kpis) {
    document.getElementById('vrKpis').innerHTML = `
        <div class="kpi-card">
            <div class="kpi-card-head"><span class="kpi-label">Artikelen</span><span class="kpi-icon kpi-icon-neutral"><i class="bi bi-box-seam"></i></span></div>
            <div class="kpi-value">${kpis.aantal}</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-card-head"><span class="kpi-label">Op voorraad</span><span class="kpi-icon kpi-icon-open"><i class="bi bi-check-circle"></i></span></div>
            <div class="kpi-value">${kpis.opVoorraad}</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-card-head"><span class="kpi-label">Uitgegeven</span><span class="kpi-icon kpi-icon-behandeling"><i class="bi bi-arrow-up-right"></i></span></div>
            <div class="kpi-value">${kpis.uitgegeven}</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-card-head"><span class="kpi-label">Afgeschreven</span><span class="kpi-icon kpi-icon-neutral"><i class="bi bi-x-circle"></i></span></div>
            <div class="kpi-value">${kpis.afgeschreven}</div>
        </div>
    `;
}

function renderList(items) {
    currentItems = items;
    const body = document.getElementById('vrListBody');

    if (items.length === 0) {
        body.innerHTML = '<div class="empty-state">Geen voorraaditems gevonden.</div>';
        return;
    }

    if (selectedId === null || !items.some((v) => v.id === selectedId)) {
        selectedId = items[0].id;
    }

    body.innerHTML = `
        <div class="table-wrap"><table>
            <thead><tr><th>Artikel</th><th>Locatie</th><th>Status</th></tr></thead>
            <tbody>${items.map((v) => `
                <tr data-id="${v.id}" style="cursor:pointer${v.id === selectedId ? ';background:var(--color-background-secondary)' : ''}">
                    <td>
                        <div class="mono" style="font-size:10px;color:var(--color-text-tertiary)">${esc(v.barcode)}</div>
                        <div style="font-weight:500;font-size:13px;margin-top:2px">${esc(v.type_naam || '—')}${v.variant ? ' (' + esc(v.variant) + ')' : ''}</div>
                    </td>
                    <td style="font-size:12px;color:var(--color-text-tertiary)">${esc(v.locatie || '—')}</td>
                    <td>${statusBadge(v.status)}</td>
                </tr>
            `).join('')}</tbody>
        </table></div>
    `;

    body.querySelectorAll('tr[data-id]').forEach((row) => {
        row.addEventListener('click', () => selectItem(parseInt(row.dataset.id, 10)));
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

async function loadDetail(id) {
    const detail = document.getElementById('vrDetail');
    detail.innerHTML = '<div class="empty-state">Laden&hellip;</div>';

    try {
        const res = await api.get(`/api/v1/voorraad/${id}`);
        renderDetail(res.data.item, res.data.uitgiften);
    } catch (e) {
        const message = e instanceof ApiError ? e.message : 'Kon item niet laden.';
        detail.innerHTML = `<div class="empty-state" style="color:var(--color-text-danger)">${esc(message)}</div>`;
    }
}

function uitgiftehistorieHtml(uitgiften) {
    return uitgiften.length === 0
        ? '<p style="font-size:12px;color:var(--color-text-tertiary)">Nog nooit uitgegeven.</p>'
        : `<ul style="list-style:none;padding:0;margin:0;display:flex;flex-direction:column;gap:10px">
            ${uitgiften.map((u) => `
                <li style="font-size:12.5px;border-bottom:0.5px solid var(--color-border-tertiary);padding-bottom:8px">
                    <a href="/uitgiften/${u.id}" style="font-weight:500">${esc(u.medewerker_naam)}</a>
                    <div style="color:var(--color-text-tertiary);margin-top:2px">${esc(String(u.uitgegeven_op || '').slice(0, 10))}${u.teruggegeven_op ? ' &rarr; retour ' + esc(String(u.teruggegeven_op).slice(0, 10)) : ' (nog in gebruik)'}</div>
                </li>
            `).join('')}
           </ul>`;
}

function renderDetail(item, uitgiften) {
    document.getElementById('vrDetail').innerHTML = `
        <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:8px">
            <div>
                <div class="mono" style="font-size:11px;color:var(--color-text-tertiary)">${esc(item.barcode)}</div>
                <div style="font-size:16px;font-weight:600;margin-top:2px">${esc(item.type_naam || '—')}${item.variant ? ' (' + esc(item.variant) + ')' : ''}</div>
                <div style="margin-top:6px">${statusBadge(item.status)}</div>
            </div>
            ${actionMenuHtml('vr-detail')}
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;font-size:12.5px;border-top:0.5px solid var(--color-border-tertiary);margin-top:12px;padding-top:12px">
            <div><div style="font-size:11px;text-transform:uppercase;color:var(--color-text-tertiary)">Locatie</div><div>${esc(item.locatie || '—')}</div></div>
            <div><div style="font-size:11px;text-transform:uppercase;color:var(--color-text-tertiary)">Serienummer</div><div class="mono">${esc(item.serienummer || '—')}</div></div>
            <div><div style="font-size:11px;text-transform:uppercase;color:var(--color-text-tertiary)">Product-ID</div><div class="mono">${esc(item.product_id || '—')}</div></div>
            <div style="grid-column:span 2"><div style="font-size:11px;text-transform:uppercase;color:var(--color-text-tertiary)">Opmerking</div><div>${esc(item.opmerking || '—')}</div></div>
        </div>
        <div style="display:flex;gap:8px;margin-top:12px">
            <a class="btn btn-ghost" href="/voorraad/${item.id}/edit">Bewerken</a>
            <a class="btn btn-ghost" href="/voorraad/${item.id}/barcode" target="_blank">Barcode printen</a>
        </div>
    `;

    wireActionMenu(document.getElementById('vrDetail'), 'vr-detail', [
        {
            label: `Uitgiftehistorie bekijken (${uitgiften.length})`,
            icon: 'bi-clock-history',
            onClick: () => openSheet('Uitgiftehistorie', uitgiftehistorieHtml(uitgiften)),
        },
    ]);
}

async function load() {
    if (!document.getElementById('vrListBody')) {
        renderShell();
    }
    renderTableHead();
    document.getElementById('vrListBody').innerHTML = '<tr><td colspan="3" class="empty-state">Laden&hellip;</td></tr>';

    try {
        const res = await api.get('/api/v1/voorraad' + (window.location.search || ''));
        renderKpis(res.meta.kpis);
        renderList(res.data);
        if (selectedId) {
            loadDetail(selectedId);
        }
    } catch (e) {
        const message = e instanceof ApiError ? e.message : 'Kon voorraad niet laden.';
        document.getElementById('vrListBody').innerHTML =
            `<div class="empty-state" style="color:var(--color-text-danger)">${esc(message)}</div>`;
    }
}

renderShell();
load();
