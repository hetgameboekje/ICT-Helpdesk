import { api, ApiError } from '/assets/js/api/client.js';

/**
 * Printers: lijst + detail in één split-view scherm, o.b.v. src/routes/modules.printer.tsx
 * (Lovable MCP read_file, project 4675b36f-276e-4fc5-9606-d83a98f9d801).
 *
 * Bewuste afwijkingen t.o.v. de mockup — zie PrinterService voor de reden: het echte datamodel is
 * een driver/netwerk-registratie (naam, print-server, driver, ip-adres, gegenereerd
 * installatiecommando), geen live monitoring:
 * - Geen online/offline-status, tonerniveaus of printjobs (bestaan niet) — vervangen door de
 *   echte kolommen (server, type, driver, ip-adres) en het bestaande installatiecommando met
 *   kopieerknop (herbruikt de globale .js-copy-btn-handler uit app.js).
 * - KPI's tonen aantal printers / unieke servers / unieke typen / printers zonder ip-adres i.p.v.
 *   verzonnen toner-/printjob-tellingen.
 */

function esc(value) {
    const div = document.createElement('div');
    div.textContent = value ?? '';
    return div.innerHTML;
}

const root = document.getElementById('printer-app');

function pathId() {
    const m = window.location.pathname.match(/^\/printers\/(\d+)$/);
    return m ? parseInt(m[1], 10) : null;
}

function getParams() {
    return new URLSearchParams(window.location.search);
}

function apiQuery() {
    const qs = getParams().toString();
    return '/api/v1/printers' + (qs ? `?${qs}` : '');
}

let selectedId = pathId();
let currentItems = [];

function renderShell() {
    root.innerHTML = `
        <div class="page-header">
            <div class="page-title">Printers</div>
            <a class="btn btn-accent" href="/printers/create"><i class="bi bi-plus-lg"></i> Nieuwe printer</a>
        </div>
        <section class="kpi-grid" id="prnKpis"></section>
        <div class="list-toolbar">
            <div class="search-input">
                <i class="bi bi-search"></i>
                <input type="text" id="prnZoekInput" placeholder="Zoek op naam&hellip;">
            </div>
        </div>
        <div style="display:grid;grid-template-columns:minmax(0,380px) 1fr;gap:16px;align-items:start">
            <div class="card" style="padding:0;overflow:hidden">
                <div id="prnListBody"></div>
            </div>
            <div class="card" id="prnDetail" style="padding:24px"></div>
        </div>
    `;

    document.getElementById('prnZoekInput').value = getParams().get('q') || '';
    document.getElementById('prnZoekInput').addEventListener('input', debounce(() => {
        const params = getParams();
        const q = document.getElementById('prnZoekInput').value.trim();
        if (q) { params.set('q', q); } else { params.delete('q'); }
        window.history.replaceState({}, '', '/printers' + (params.toString() ? `?${params}` : ''));
        load();
    }, 300));
}

function debounce(fn, ms) {
    let t;
    return (...args) => { clearTimeout(t); t = setTimeout(() => fn(...args), ms); };
}

function renderKpis(stats) {
    const cards = [
        { label: 'Printers', value: stats.totaal, icon: 'bi-printer', tone: 'behandeling' },
        { label: 'Print-servers', value: stats.servers, icon: 'bi-hdd-network', tone: 'open' },
        { label: 'Typen', value: stats.types, icon: 'bi-diagram-3', tone: 'opgelost' },
        { label: 'Zonder IP-adres', value: stats.zonderIp, icon: 'bi-exclamation-triangle', tone: 'wachtend' },
    ];
    document.getElementById('prnKpis').innerHTML = cards.map((c) => `
        <div class="kpi-card">
            <div class="kpi-card-head"><span class="kpi-label">${esc(c.label)}</span>
                <span class="kpi-icon kpi-icon-${c.tone}"><i class="bi ${c.icon}"></i></span></div>
            <div class="kpi-value">${c.value}</div>
        </div>
    `).join('');
}

function renderList(items) {
    currentItems = items;
    const body = document.getElementById('prnListBody');

    if (items.length === 0) {
        body.innerHTML = '<div class="empty-state">Geen printers gevonden.</div>';
        return;
    }

    if (selectedId === null || !items.some((p) => p.id === selectedId)) {
        selectedId = items[0].id;
    }

    body.innerHTML = `<div class="list-group list-group-flush">${items.map((p) => `
        <div class="list-group-item" data-id="${p.id}" style="cursor:pointer${p.id === selectedId ? ';background:var(--color-background-secondary)' : ''}">
            <div style="display:flex;align-items:center;justify-content:space-between;gap:8px">
                <span style="font-weight:500;font-size:13.5px">${esc(p.naam)}</span>
                <span style="font-size:11px;color:var(--color-text-tertiary)">${esc(p.computer_naam || '—')}</span>
            </div>
            <div style="font-size:11px;color:var(--color-text-tertiary);margin-top:2px">${esc(p.type || '—')} &middot; ${esc(p.ip_adres || 'geen IP')}</div>
        </div>
    `).join('')}</div>`;

    body.querySelectorAll('[data-id]').forEach((el) => {
        el.addEventListener('click', () => selectItem(parseInt(el.dataset.id, 10)));
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
    const detail = document.getElementById('prnDetail');
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
    document.getElementById('prnDetail').innerHTML = `
        <div style="display:flex;align-items:start;justify-content:space-between;gap:16px">
            <div>
                <h2 style="font-size:18px;font-weight:600;margin:0">${esc(item.naam)}</h2>
                <div style="font-size:12px;color:var(--color-text-tertiary);margin-top:4px">${esc(item.type)} &middot; ${esc(item.computer_naam || 'lokaal')}</div>
            </div>
            <a class="btn btn-ghost" href="/printers/${item.id}/edit">Bewerken</a>
        </div>

        <div style="margin-top:16px;border-top:0.5px solid var(--color-border-tertiary);padding-top:12px">
            <div class="meta-row"><span class="meta-key">Server</span><span>${esc(item.computer_naam || '—')}</span></div>
            <div class="meta-row"><span class="meta-key">Driver</span><span>${esc(item.driver_naam || '—')}</span></div>
            <div class="meta-row"><span class="meta-key">IP-adres / poort</span><span>${esc(item.ip_adres || '—')}</span></div>
            <div class="meta-row"><span class="meta-key">Opmerking</span><span>${esc(item.opmerking || '—')}</span></div>
            <div class="meta-row"><span class="meta-key">Toegevoegd door</span><span>${esc(item.aangemaakt_door_naam || '—')}</span></div>
        </div>

        <div style="margin-top:16px;border-top:0.5px solid var(--color-border-tertiary);padding-top:12px">
            <h3 class="detail-side-heading">Installatiecommando</h3>
            <code style="display:block;background:var(--color-background-secondary);padding:10px 12px;border-radius:var(--border-radius-md);font-size:12.5px;word-break:break-all;margin-bottom:10px">${esc(installCommand)}</code>
            <button type="button" class="btn btn-accent js-copy-btn" data-command="${esc(installCommand)}"><i class="bi bi-copy"></i> Kopieer commando</button>
        </div>
    `;
}

async function load() {
    if (!document.getElementById('prnListBody')) {
        renderShell();
    }
    document.getElementById('prnListBody').innerHTML = '<div class="empty-state">Laden&hellip;</div>';

    try {
        const res = await api.get(apiQuery());
        renderKpis(res.meta.stats);
        renderList(res.data);
        if (selectedId) {
            loadDetail(selectedId);
        }
    } catch (e) {
        const message = e instanceof ApiError ? e.message : 'Kon printers niet laden.';
        document.getElementById('prnListBody').innerHTML =
            `<div class="empty-state" style="color:var(--color-text-danger)">${esc(message)}</div>`;
    }
}

renderShell();
load();
