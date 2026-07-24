import { api, ApiError } from '/assets/js/api/client.js';

/**
 * Printers: lijst + item in één split-view scherm, zoals in het Lovable-ontwerp
 * (src/routes/modules.printer.tsx, via de Lovable MCP). Vervangt de eerdere server-rendered
 * index.php/show.php-inhoud.
 *
 * Grote afwijking t.o.v. de mockup: Lovable's scherm gaat uit van live printermonitoring
 * (online/offline, toner-percentages per kleur, recente printjobs, herstart/testpagina-acties) —
 * daar bestaat in onze backend niets van (`printers`-tabel heeft alleen naam/computernaam/type/
 * driver/ip/opmerking, geen telemetrie). Al die widgets zijn weggelaten i.p.v. met verzonnen data
 * nagemaakt. Wat overblijft: een echte lijst + detail met de bestaande velden, plus de echte
 * (bestaande) rundll32-installcommando-generator (`PrinterModel::buildInstallCommand()`), die in de
 * mockup niet voorkomt maar wel een bruikbare, werkende functie is — met kopieer-knop toegevoegd.
 * KPI's zijn vervangen door echte tellingen (aantal printers/typen/print-servers) i.p.v. offline-
 * count/toner-alerts/paginatotalen.
 */

function esc(value) {
    const div = document.createElement('div');
    div.textContent = value ?? '';
    return div.innerHTML;
}

const root = document.getElementById('printers-app');

function pathId() {
    const m = window.location.pathname.match(/^\/printers\/(\d+)$/);
    return m ? parseInt(m[1], 10) : null;
}

function getParams() {
    return new URLSearchParams(window.location.search);
}

let selectedId = pathId();
let currentItems = [];

function renderShell() {
    root.innerHTML = `
        <div class="page-header"><div class="page-title">Printers</div></div>
        <section class="kpi-grid" id="prKpis"></section>
        <div class="list-toolbar">
            <div class="search-input">
                <i class="bi bi-search"></i>
                <input type="text" id="prZoekInput" placeholder="Zoek printer&hellip;">
            </div>
            <a class="btn btn-accent" href="/printers/create"><i class="bi bi-plus-lg"></i> Nieuwe printer</a>
        </div>
        <div class="kb-split" style="display:grid;grid-template-columns:minmax(0,380px) 1fr;gap:16px;align-items:start">
            <div class="card" style="padding:0;overflow:hidden">
                <div id="prListBody"></div>
            </div>
            <div class="card" id="prDetail" style="padding:24px"></div>
        </div>
    `;

    document.getElementById('prZoekInput').value = getParams().get('q') || '';
    document.getElementById('prZoekInput').addEventListener('input', debounce(() => {
        const params = getParams();
        const q = document.getElementById('prZoekInput').value.trim();
        if (q) { params.set('q', q); } else { params.delete('q'); }
        window.history.replaceState({}, '', '/printers' + (params.toString() ? `?${params}` : ''));
        load();
    }, 300));
}

function debounce(fn, ms) {
    let t;
    return (...args) => { clearTimeout(t); t = setTimeout(() => fn(...args), ms); };
}

function renderKpis(kpis) {
    document.getElementById('prKpis').innerHTML = `
        <div class="kpi-card">
            <div class="kpi-card-head"><span class="kpi-label">Printers</span>
                <span class="kpi-icon kpi-icon-open"><i class="bi bi-printer"></i></span></div>
            <div class="kpi-value">${kpis.aantal}</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-card-head"><span class="kpi-label">Typen</span>
                <span class="kpi-icon kpi-icon-neutral"><i class="bi bi-tags"></i></span></div>
            <div class="kpi-value">${kpis.aantalTypen}</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-card-head"><span class="kpi-label">Print-servers</span>
                <span class="kpi-icon kpi-icon-neutral"><i class="bi bi-hdd-network"></i></span></div>
            <div class="kpi-value">${kpis.aantalServers}</div>
        </div>
    `;
}

function renderList(items) {
    currentItems = items;
    const body = document.getElementById('prListBody');

    if (items.length === 0) {
        body.innerHTML = '<div class="empty-state">Geen printers gevonden.</div>';
        return;
    }

    if (selectedId === null || !items.some((p) => p.id === selectedId)) {
        selectedId = items[0].id;
    }

    body.innerHTML = `<div class="divide-list">${items.map((p) => `
        <div data-id="${p.id}" style="cursor:pointer;padding:12px 16px;border-bottom:0.5px solid var(--color-border-tertiary)${p.id === selectedId ? ';background:var(--color-background-secondary)' : ''}">
            <div style="display:flex;align-items:center;justify-content:between;gap:8px">
                <span style="font-weight:500">${esc(p.naam)}</span>
                <span style="font-size:11px;color:var(--color-text-tertiary);margin-left:auto">${esc(p.computer_naam || '—')}</span>
            </div>
            <div style="font-size:11px;color:var(--color-text-tertiary);margin-top:2px">${esc(p.type)}</div>
        </div>
    `).join('')}</div>`;

    body.querySelectorAll('[data-id]').forEach((row) => {
        row.addEventListener('click', () => selectItem(parseInt(row.dataset.id, 10)));
    });
}

function selectItem(id) {
    selectedId = id;
    window.history.pushState({}, '', `/printers/${id}`);
    renderList(currentItems);
    loadDetail(id);
}

window.addEventListener('popstate', () => {
    selectedId = pathId();
    renderList(currentItems);
    if (selectedId) loadDetail(selectedId);
});

async function loadDetail(id) {
    const detail = document.getElementById('prDetail');
    detail.innerHTML = '<div class="empty-state">Laden&hellip;</div>';

    try {
        const res = await api.get(`/api/v1/printers/${id}`);
        renderDetail(res.data.item, res.data.installCommand);
    } catch (e) {
        const message = e instanceof ApiError ? e.message : 'Kon printer niet laden.';
        detail.innerHTML = `<div class="empty-state" style="color:var(--color-text-danger)">${esc(message)}</div>`;
    }
}

function renderDetail(item, installCommand) {
    document.getElementById('prDetail').innerHTML = `
        <div style="display:flex;align-items:start;justify-content:space-between;gap:16px;margin-bottom:16px">
            <div>
                <h2 style="font-size:20px;font-weight:600;margin:0">${esc(item.naam)}</h2>
                <div style="font-size:12px;color:var(--color-text-tertiary);margin-top:4px">${esc(item.type)}${item.computer_naam ? ' &middot; ' + esc(item.computer_naam) : ''}</div>
            </div>
            <a class="btn btn-ghost" href="/printers/${item.id}/edit">Bewerken</a>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;font-size:13px;border-top:0.5px solid var(--color-border-tertiary);padding-top:12px">
            <div><div style="font-size:11px;text-transform:uppercase;color:var(--color-text-tertiary)">IP-adres</div><div style="margin-top:2px">${esc(item.ip_adres || '—')}</div></div>
            <div><div style="font-size:11px;text-transform:uppercase;color:var(--color-text-tertiary)">Driver</div><div style="margin-top:2px">${esc(item.driver_naam || '—')}</div></div>
            <div style="grid-column:span 2"><div style="font-size:11px;text-transform:uppercase;color:var(--color-text-tertiary)">Opmerking</div><div style="margin-top:2px">${esc(item.opmerking || '—')}</div></div>
        </div>
        <div style="border-top:0.5px solid var(--color-border-tertiary);margin-top:16px;padding-top:16px">
            <h3 style="font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.04em;color:var(--color-text-tertiary);margin:0 0 8px">Installatiecommando</h3>
            <div style="display:flex;gap:8px;align-items:center">
                <code class="mono" style="flex:1;font-size:11.5px;background:var(--color-background-secondary);padding:8px 10px;border-radius:6px;overflow-x:auto;white-space:nowrap">${esc(installCommand)}</code>
                <button class="btn btn-ghost" type="button" id="prCopyBtn">Kopieer</button>
            </div>
        </div>
    `;

    document.getElementById('prCopyBtn').addEventListener('click', () => {
        navigator.clipboard.writeText(installCommand);
    });
}

async function load() {
    if (!document.getElementById('prListBody')) {
        renderShell();
    }
    document.getElementById('prListBody').innerHTML = '<div class="empty-state">Laden&hellip;</div>';

    try {
        const res = await api.get('/api/v1/printers' + (window.location.search || ''));
        renderKpis(res.meta.kpis);
        renderList(res.data);
        if (selectedId) {
            loadDetail(selectedId);
        }
    } catch (e) {
        const message = e instanceof ApiError ? e.message : 'Kon printers niet laden.';
        document.getElementById('prListBody').innerHTML =
            `<div class="empty-state" style="color:var(--color-text-danger)">${esc(message)}</div>`;
    }
}

renderShell();
load();
