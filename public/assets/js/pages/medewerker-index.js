import { api, ApiError } from '/assets/js/api/client.js';

/**
 * Medewerkers: kaarten-grid + detailpaneel (hiërarchie, uitgiften, apparaten), o.b.v.
 * src/routes/modules.medewerker.tsx (Lovable MCP read_file, project
 * 4675b36f-276e-4fc5-9606-d83a98f9d801).
 *
 * Bewuste afwijkingen t.o.v. de mockup — zie MedewerkerService voor de reden:
 * - Status kent alleen actief/inactief (geen "verlof") — de statusdot en KPI-rij volgen de echte
 *   2 waarden i.p.v. Lovable's 3.
 * - "In behandeling nu"-telling is een echte, live telling van open tickets op naam van de
 *   gekoppelde login (behandelaar_id + status in_behandeling), geen mockdata-veld.
 * - Hiërarchie (manager + team) komt uit manager_id/is_keyuser, al een bestaande, echte feature.
 * - De bestaande secties "Uitgiften"/"Apparaten & software"/"Schijfgebruik" (server-rendered
 *   show.php) zijn behouden in het detailpaneel — komen niet in de mockup voor, maar bevatten
 *   echte, al bestaande koppelingen die niet mochten vervallen bij de conversie.
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
function initialen(voornaam, achternaam) {
    return ((voornaam[0] || '') + (achternaam[0] || '')).toUpperCase();
}

const root = document.getElementById('medewerker-app');

function pathId() {
    const m = window.location.pathname.match(/^\/medewerkers\/(\d+)$/);
    return m ? parseInt(m[1], 10) : null;
}

function getParams() {
    return new URLSearchParams(window.location.search);
}

function apiQuery() {
    const qs = getParams().toString();
    return '/api/v1/medewerkers' + (qs ? `?${qs}` : '');
}

let selectedId = pathId();
let currentItems = [];

function renderShell() {
    root.innerHTML = `
        <section class="kpi-grid" id="medKpis"></section>
        <div class="list-toolbar">
            <div class="search-input">
                <i class="bi bi-search"></i>
                <input type="text" id="medZoekInput" placeholder="Zoek op naam of afdeling&hellip;">
            </div>
        </div>
        <div style="display:grid;grid-template-columns:minmax(0,1fr) 380px;gap:16px;align-items:start">
            <div class="card" style="padding:14px">
                <div id="medGrid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:10px"></div>
            </div>
            <div id="medDetail"></div>
        </div>
    `;

    document.getElementById('medZoekInput').value = getParams().get('q') || '';
    document.getElementById('medZoekInput').addEventListener('input', debounce(() => {
        const params = getParams();
        const q = document.getElementById('medZoekInput').value.trim();
        if (q) { params.set('q', q); } else { params.delete('q'); }
        window.history.replaceState({}, '', '/medewerkers' + (params.toString() ? `?${params}` : ''));
        load();
    }, 300));
}

function debounce(fn, ms) {
    let t;
    return (...args) => { clearTimeout(t); t = setTimeout(() => fn(...args), ms); };
}

function renderKpis(stats) {
    const cards = [
        { label: 'Medewerkers', value: stats.totaal, icon: 'bi-people', tone: 'behandeling' },
        { label: 'Keyusers', value: stats.keyusers, icon: 'bi-key', tone: 'open' },
        { label: 'Actief', value: stats.actief, icon: 'bi-person-check', tone: 'opgelost' },
        { label: 'Inactief', value: stats.inactief, icon: 'bi-person-dash', tone: 'wachtend' },
    ];
    document.getElementById('medKpis').innerHTML = cards.map((c) => `
        <div class="kpi-card">
            <div class="kpi-card-head"><span class="kpi-label">${esc(c.label)}</span>
                <span class="kpi-icon kpi-icon-${c.tone}"><i class="bi ${c.icon}"></i></span></div>
            <div class="kpi-value">${c.value}</div>
        </div>
    `).join('');
}

function renderGrid(items) {
    currentItems = items;
    const grid = document.getElementById('medGrid');

    if (items.length === 0) {
        grid.innerHTML = '<div class="empty-state">Geen medewerkers gevonden.</div>';
        return;
    }

    if (selectedId === null || !items.some((m) => m.id === selectedId)) {
        selectedId = items[0].id;
    }

    grid.innerHTML = items.map((m) => `
        <button type="button" data-id="${m.id}" class="med-card" style="text-align:left;border:1px solid var(--color-border-tertiary);border-radius:var(--border-radius-md);padding:10px;cursor:pointer;background:${m.id === selectedId ? 'var(--color-background-info)' : 'var(--color-background-primary)'}${m.id === selectedId ? ';border-color:var(--color-primary)' : ''}">
            <div style="display:flex;align-items:center;gap:8px">
                <span class="avatar-xs" style="width:32px;height:32px">${esc(initialen(m.voornaam, m.achternaam))}</span>
                <div style="min-width:0;flex:1">
                    <div class="text-truncate" style="font-size:13px;font-weight:500;display:flex;align-items:center;gap:4px">
                        ${esc(m.voornaam)} ${esc(m.achternaam)} ${m.is_keyuser ? '<i class="bi bi-key-fill" style="color:var(--color-primary);font-size:11px"></i>' : ''}
                    </div>
                    <div class="text-truncate" style="font-size:11px;color:var(--color-text-tertiary)">${esc(m.functie || '—')}</div>
                </div>
                <span style="width:6px;height:6px;border-radius:50%;flex-shrink:0;background:${m.status === 'actief' ? 'var(--color-status-opgelost)' : 'var(--color-status-wachtend)'}" title="${m.status === 'actief' ? 'Actief' : 'Inactief'}"></span>
            </div>
            <div style="font-size:10.5px;color:var(--color-text-tertiary);margin-top:6px">${esc(m.afdeling_naam || '—')}</div>
        </button>
    `).join('');

    grid.querySelectorAll('[data-id]').forEach((el) => {
        el.addEventListener('click', () => selectItem(parseInt(el.dataset.id, 10)));
    });
}

function selectItem(id) {
    selectedId = id;
    window.history.pushState({}, '', `/medewerkers/${id}`);
    renderGrid(currentItems);
    loadDetail(id);
}

window.addEventListener('popstate', () => {
    selectedId = pathId();
    renderGrid(currentItems);
    if (selectedId) loadDetail(selectedId);
});

async function loadDetail(id) {
    const detail = document.getElementById('medDetail');
    detail.innerHTML = '<div class="card" style="padding:20px"><div class="empty-state">Laden&hellip;</div></div>';

    try {
        const res = await api.get(`/api/v1/medewerkers/${id}`);
        renderDetail(res.data);
    } catch (e) {
        const message = e instanceof ApiError ? e.message : 'Kon medewerker niet laden.';
        detail.innerHTML = `<div class="card" style="padding:20px"><div class="empty-state" style="color:var(--color-text-danger)">${esc(message)}</div></div>`;
    }
}

function renderDetail(data) {
    const { item, team, inBehandeling, uitgiften, apparaten, schijfgebruik } = data;

    document.getElementById('medDetail').innerHTML = `
        <div class="card" style="padding:16px;margin-bottom:12px">
            <div style="display:flex;align-items:center;gap:10px">
                <span class="avatar-xs" style="width:44px;height:44px;font-size:15px">${esc(initialen(item.voornaam, item.achternaam))}</span>
                <div style="min-width:0;flex:1">
                    <div style="font-size:14px;font-weight:600;display:flex;align-items:center;gap:6px">
                        ${esc(item.voornaam)} ${esc(item.achternaam)}
                        ${item.is_keyuser ? '<span class="badge badge-keyuser" style="font-size:10px"><i class="bi bi-key-fill"></i> keyuser</span>' : ''}
                    </div>
                    <div style="font-size:11.5px;color:var(--color-text-tertiary)">${esc(item.functie || '—')}</div>
                </div>
            </div>
            <div style="margin-top:12px;font-size:12.5px;display:flex;flex-direction:column;gap:6px">
                <div><i class="bi bi-envelope" style="color:var(--color-text-tertiary);margin-right:6px"></i>${esc(item.email || '—')}</div>
                <div><i class="bi bi-telephone" style="color:var(--color-text-tertiary);margin-right:6px"></i>${esc(item.telefoon || '—')}</div>
            </div>
            <div style="margin-top:10px">
                <a class="btn btn-ghost" href="/medewerkers/${item.id}/edit" style="font-size:12px">Bewerken</a>
            </div>
        </div>

        <div class="card" style="padding:16px;margin-bottom:12px">
            <h3 class="detail-side-heading">Hiërarchie</h3>
            ${item.manager_id ? `
                <div style="display:flex;align-items:center;gap:8px;font-size:13px">
                    <span class="avatar-xs" style="width:26px;height:26px;font-size:10px">${esc((item.manager_naam || '').split(' ').map((p) => p[0]).join('').toUpperCase())}</span>
                    <div><div style="font-weight:500">${esc(item.manager_naam)}</div><div style="font-size:10.5px;color:var(--color-text-tertiary)">Manager</div></div>
                </div>` : ''}
            <div style="padding-left:12px;border-left:2px solid var(--color-primary);margin-left:12px;margin-top:8px;padding-top:4px;padding-bottom:4px">
                <div style="display:flex;align-items:center;gap:8px;font-size:13px">
                    <span class="avatar-xs" style="width:26px;height:26px;font-size:10px;background:var(--color-background-info);color:var(--color-primary)">${esc(initialen(item.voornaam, item.achternaam))}</span>
                    <div><div style="font-weight:500">${esc(item.voornaam)} ${esc(item.achternaam)}</div><div style="font-size:10.5px;color:var(--color-text-tertiary)">${esc(item.functie || '—')}</div></div>
                </div>
            </div>
            ${team.length ? `
                <div style="padding-left:22px;border-left:2px solid var(--color-border-tertiary);margin-left:12px;margin-top:6px">
                    <div style="font-size:10px;text-transform:uppercase;letter-spacing:.04em;color:var(--color-text-tertiary);margin-bottom:4px">Team (${team.length})</div>
                    ${team.map((t) => `
                        <div style="display:flex;align-items:center;gap:6px;font-size:12px;margin-bottom:3px">
                            <span class="avatar-xs" style="width:18px;height:18px;font-size:8px">${esc(initialen(t.voornaam, t.achternaam))}</span>
                            <a href="/medewerkers/${t.id}">${esc(t.voornaam)} ${esc(t.achternaam)}</a>
                            <span style="color:var(--color-text-tertiary)">— ${esc(t.functie || '—')}</span>
                        </div>
                    `).join('')}
                </div>` : ''}
        </div>

        ${inBehandeling > 0 ? `
        <div class="card" style="padding:16px;margin-bottom:12px;background:var(--color-status-behandeling-bg)">
            <h3 class="detail-side-heading" style="color:var(--color-status-behandeling)">In behandeling nu</h3>
            <div style="font-size:22px;font-weight:600">${inBehandeling} tickets</div>
            <div style="font-size:11px;color:var(--color-text-tertiary);margin-top:4px">Zichtbaar in Agenda als "in behandeling"-blokken.</div>
            <a class="btn btn-ghost" href="/agenda" style="margin-top:8px;font-size:12px">Bekijk in agenda &rarr;</a>
        </div>` : ''}

        <div class="card" style="margin-bottom:10px">
            <div class="card-header"><span class="card-title">Uitgiften</span></div>
            ${uitgiften.length === 0 ? '<div class="empty-state">Nog geen uitgiften voor deze medewerker.</div>' : `<div class="log-list">${uitgiften.map((u) => `
                <a class="log-item" href="/uitgiften/${u.id}" style="display:block;color:inherit;text-decoration:none">
                    <div class="log-meta">
                        <span class="log-user">${esc(u.type_naam || 'Item')}${u.variant ? ' (' + esc(u.variant) + ')' : ''}</span>
                        <span class="log-time">${formatDatum(u.uitgegeven_op)}</span>
                    </div>
                </a>
            `).join('')}</div>`}
        </div>

        <div class="card" style="margin-bottom:10px">
            <div class="card-header"><span class="card-title">Apparaten &amp; software</span></div>
            ${apparaten.length === 0 ? '<div class="empty-state">Nog geen apparaten gekoppeld.</div>' : `<div class="log-list">${apparaten.map((d) => `
                <a class="log-item" href="/apparaten/${d.id}" style="display:block;color:inherit;text-decoration:none">
                    <div class="log-meta"><span class="log-user">${esc(d.naam)}</span><span class="log-time">${parseInt(d.software_aantal || 0, 10)} software-item(s)</span></div>
                </a>
            `).join('')}</div>`}
        </div>

        <div class="card">
            <div class="card-header"><span class="card-title">Schijfgebruik</span></div>
            ${schijfgebruik.length === 0 ? '<div class="empty-state">Geen schijfgebruik-apparaten gekoppeld.</div>' : `<div class="log-list">${schijfgebruik.map((d) => `
                <a class="log-item" href="/schijfgebruik/${d.id}" style="display:block;color:inherit;text-decoration:none">
                    <div class="log-meta"><span class="log-user">${esc(d.naam)}</span><span class="log-time">${esc(d.laatste_login || '—')}</span></div>
                </a>
            `).join('')}</div>`}
        </div>
    `;
}

async function load() {
    if (!document.getElementById('medGrid')) {
        renderShell();
    }
    document.getElementById('medGrid').innerHTML = '<div class="empty-state">Laden&hellip;</div>';

    try {
        const res = await api.get(apiQuery());
        renderKpis(res.meta.stats);
        renderGrid(res.data);
        if (selectedId) {
            loadDetail(selectedId);
        }
    } catch (e) {
        const message = e instanceof ApiError ? e.message : 'Kon medewerkers niet laden.';
        document.getElementById('medGrid').innerHTML =
            `<div class="empty-state" style="color:var(--color-text-danger)">${esc(message)}</div>`;
    }
}

renderShell();
load();
