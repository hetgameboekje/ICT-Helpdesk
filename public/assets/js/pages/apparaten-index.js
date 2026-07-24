import { api, ApiError } from '/assets/js/api/client.js';

/**
 * Apparaten (devices): lijst + item in één split-view scherm, zoals in het Lovable-ontwerp
 * (src/routes/modules.device.tsx, via de Lovable MCP). Vervangt de eerdere server-rendered
 * index.php/show.php-inhoud.
 *
 * Grote afwijking t.o.v. de mockup: Lovable gaat uit van live endpoint-monitoring (online/offline,
 * status in-gebruik/voorraad/reparatie/afgeschreven, OS, serienummer, locatie, "ingezet sinds",
 * herstart/wipe-acties) — niets daarvan bestaat in onze backend. `devices` is puur een CSV-import-
 * resultaat: naam, gekoppelde medewerker, laatste import-datum en een geïmporteerde software-lijst.
 * Al die fictieve velden/acties zijn weggelaten. Wat overblijft: een echte lijst + detail met de
 * bestaande velden plus de echte geïmporteerde software (waar Lovable niets van toont). "Registreer
 * device" is geen simpel formulier hier (aanmaken loopt alleen via CSV-upload, zie
 * DeviceController::store()) — de knop linkt daarom door naar de bestaande upload-pagina i.p.v. een
 * quick-add te faken. KPI's zijn vervangen door echte tellingen (totaal/gekoppeld/ongekoppeld).
 */

function esc(value) {
    const div = document.createElement('div');
    div.textContent = value ?? '';
    return div.innerHTML;
}

const root = document.getElementById('apparaten-app');

function pathId() {
    const m = window.location.pathname.match(/^\/apparaten\/(\d+)$/);
    return m ? parseInt(m[1], 10) : null;
}

function getParams() {
    return new URLSearchParams(window.location.search);
}

let selectedId = pathId();
let currentItems = [];

function renderShell() {
    root.innerHTML = `
        <div class="page-header"><div class="page-title">Apparaten</div></div>
        <section class="kpi-grid" id="dvKpis"></section>
        <div class="list-toolbar">
            <div class="search-input">
                <i class="bi bi-search"></i>
                <input type="text" id="dvZoekInput" placeholder="Zoek op naam of medewerker&hellip;">
            </div>
            <a class="btn btn-accent" href="/apparaten/create"><i class="bi bi-upload"></i> Registreer device</a>
        </div>
        <div class="kb-split" style="display:grid;grid-template-columns:minmax(0,1fr) 360px;gap:16px;align-items:start">
            <div class="card" style="padding:0;overflow:hidden">
                <div id="dvListBody"></div>
            </div>
            <div class="card" id="dvDetail" style="padding:24px"></div>
        </div>
    `;

    document.getElementById('dvZoekInput').value = getParams().get('q') || '';
    document.getElementById('dvZoekInput').addEventListener('input', debounce(() => {
        const params = getParams();
        const q = document.getElementById('dvZoekInput').value.trim();
        if (q) { params.set('q', q); } else { params.delete('q'); }
        window.history.replaceState({}, '', '/apparaten' + (params.toString() ? `?${params}` : ''));
        load();
    }, 300));
}

function debounce(fn, ms) {
    let t;
    return (...args) => { clearTimeout(t); t = setTimeout(() => fn(...args), ms); };
}

function renderKpis(kpis) {
    document.getElementById('dvKpis').innerHTML = `
        <div class="kpi-card">
            <div class="kpi-card-head"><span class="kpi-label">Apparaten</span>
                <span class="kpi-icon kpi-icon-open"><i class="bi bi-laptop"></i></span></div>
            <div class="kpi-value">${kpis.aantal}</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-card-head"><span class="kpi-label">Gekoppeld aan medewerker</span>
                <span class="kpi-icon kpi-icon-behandeling"><i class="bi bi-person-check"></i></span></div>
            <div class="kpi-value">${kpis.gekoppeld}</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-card-head"><span class="kpi-label">Ongekoppeld</span>
                <span class="kpi-icon kpi-icon-neutral"><i class="bi bi-person-dash"></i></span></div>
            <div class="kpi-value">${kpis.ongekoppeld}</div>
        </div>
    `;
}

function renderList(items) {
    currentItems = items;
    const body = document.getElementById('dvListBody');

    if (items.length === 0) {
        body.innerHTML = '<div class="empty-state">Geen apparaten gevonden.</div>';
        return;
    }

    if (selectedId === null || !items.some((d) => d.id === selectedId)) {
        selectedId = items[0].id;
    }

    body.innerHTML = `
        <div class="table-wrap"><table>
            <thead><tr><th>Naam</th><th>Medewerker</th><th>Software</th><th>Laatst geïmporteerd</th></tr></thead>
            <tbody>${items.map((d) => `
                <tr data-id="${d.id}" style="cursor:pointer${d.id === selectedId ? ';background:var(--color-background-secondary)' : ''}">
                    <td class="mono" style="font-size:12px;font-weight:500">${esc(d.naam)}</td>
                    <td style="font-size:12px">${esc(d.medewerker_naam || '—')}</td>
                    <td style="font-size:12px;color:var(--color-text-tertiary)">${d.software_aantal ?? 0}</td>
                    <td style="font-size:12px;color:var(--color-text-tertiary)">${esc(String(d.laatst_geimporteerd_op || '').slice(0, 10) || '—')}</td>
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
    const detail = document.getElementById('dvDetail');
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
    document.getElementById('dvDetail').innerHTML = `
        <div style="margin-bottom:16px">
            <span class="mono" style="font-size:14px;font-weight:600">${esc(item.naam)}</span>
            <div style="font-size:12px;color:var(--color-text-tertiary);margin-top:4px">
                ${esc(item.medewerker_naam || 'Niet gekoppeld')}
            </div>
            <div style="font-size:11px;color:var(--color-text-tertiary);margin-top:2px">
                Laatst geïmporteerd: ${esc(String(item.laatst_geimporteerd_op || '').slice(0, 16).replace('T', ' ') || '—')}
            </div>
        </div>
        <h3 style="font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.04em;color:var(--color-text-tertiary);margin:0 0 8px;border-top:0.5px solid var(--color-border-tertiary);padding-top:12px">
            Ge&iuml;nstalleerde software (${software.length})
        </h3>
        ${software.length === 0
            ? '<p style="font-size:12px;color:var(--color-text-tertiary)">Geen software geïmporteerd.</p>'
            : `<ul style="list-style:none;padding:0;margin:0;display:flex;flex-direction:column;gap:6px;max-height:320px;overflow-y:auto">
                ${software.map((s) => `
                    <li style="font-size:12px">
                        <div style="font-weight:500">${esc(s.naam)}</div>
                        <div style="color:var(--color-text-tertiary)">${esc(s.publisher || '—')}${s.versie ? ' &middot; v' + esc(s.versie) : ''}</div>
                    </li>
                `).join('')}
               </ul>`}
    `;
}

async function load() {
    if (!document.getElementById('dvListBody')) {
        renderShell();
    }
    document.getElementById('dvListBody').innerHTML = '<div class="empty-state">Laden&hellip;</div>';

    try {
        const res = await api.get('/api/v1/apparaten' + (window.location.search || ''));
        renderKpis(res.meta.kpis);
        renderList(res.data);
        if (selectedId) {
            loadDetail(selectedId);
        }
    } catch (e) {
        const message = e instanceof ApiError ? e.message : 'Kon apparaten niet laden.';
        document.getElementById('dvListBody').innerHTML =
            `<div class="empty-state" style="color:var(--color-text-danger)">${esc(message)}</div>`;
    }
}

renderShell();
load();
