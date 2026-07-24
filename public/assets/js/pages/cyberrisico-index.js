import { api, ApiError } from '/assets/js/api/client.js';

/**
 * CyberRisico: lijst + detail in één split-view scherm, o.b.v. src/routes/modules.cyberrisico.tsx
 * (Lovable MCP read_file, project 4675b36f-276e-4fc5-9606-d83a98f9d801).
 *
 * Bewuste afwijking t.o.v. de mockup — zie CyberRisicoService voor de reden: het echte datamodel
 * kent geen kans×impact-risicomatrix, enkel een "prioriteit"-enum (laag/middel/hoog/kritiek). De
 * mockup's 5x5-risicomatrix (berekend uit verzonnen kans/impact-cijfers) is daarom weggelaten i.p.v.
 * nagemaakt — de KPI-rij (4 niveaus) blijft wel intact want die is de echte prioriteit-verdeling.
 * Status/opmerkingen-secties (bestonden al in de oude server-rendered show.php) zijn behouden.
 */

const STATUS_LABELS = {
    nieuw: 'Nieuw', in_onderzoek: 'In onderzoek', bevestigd: 'Bevestigd', opgelost: 'Opgelost', geaccepteerd: 'Geaccepteerd risico',
};
const PRIO_TO_RISK_TOKEN = { laag: 'laag', middel: 'gemiddeld', hoog: 'hoog', kritiek: 'kritiek' };
const PRIO_LABELS = { laag: 'Laag', middel: 'Middel', hoog: 'Hoog', kritiek: 'Kritiek' };

function esc(value) {
    const div = document.createElement('div');
    div.textContent = value ?? '';
    return div.innerHTML;
}
function nl2br(value) {
    return esc(value).replace(/\n/g, '<br>');
}
function formatDatum(value) {
    if (!value) return '—';
    const [y, m, d] = String(value).slice(0, 10).split('-');
    return `${d}-${m}-${y}`;
}
function formatDatumTijd(value) {
    if (!value) return '—';
    const [datum, tijd] = String(value).split(' ');
    return `${formatDatum(datum)} ${(tijd || '').slice(0, 5)}`;
}
function statusBadge(status) {
    return `<span class="badge badge-${esc(status)}">${esc(STATUS_LABELS[status] || status)}</span>`;
}
function prioBadge(prio) {
    const token = PRIO_TO_RISK_TOKEN[prio] || 'gemiddeld';
    return `<span class="prio" style="background:var(--color-risk-${token}-bg);color:var(--color-risk-${token})"><span class="prio-dot" style="background:var(--color-risk-${token})"></span>${esc(PRIO_LABELS[prio] || prio)}</span>`;
}

const root = document.getElementById('cyberrisico-app');

function pathId() {
    const m = window.location.pathname.match(/^\/cyberrisicos\/(\d+)$/);
    return m ? parseInt(m[1], 10) : null;
}

function getParams() {
    return new URLSearchParams(window.location.search);
}

function apiQuery() {
    const qs = getParams().toString();
    return '/api/v1/cyberrisicos' + (qs ? `?${qs}` : '');
}

let selectedId = pathId();
let currentItems = [];

function renderShell() {
    root.innerHTML = `
        <div class="page-header">
            <div class="page-title">Cyberrisico's</div>
            <a class="btn btn-accent" href="/cyberrisicos/create"><i class="bi bi-plus-lg"></i> Risico melden</a>
        </div>
        <section class="kpi-grid" id="crKpis"></section>
        <div class="list-toolbar">
            <div class="search-input">
                <i class="bi bi-search"></i>
                <input type="text" id="crZoekInput" placeholder="Zoek risico&hellip;">
            </div>
            <select id="crStatusSelect"><option value="">Alle statussen (excl. opgelost/geaccepteerd)</option></select>
        </div>
        <div style="display:grid;grid-template-columns:minmax(0,1fr) 380px;gap:16px;align-items:start">
            <div class="card" style="padding:0;overflow:hidden">
                <div class="table-wrap"><table>
                    <thead><tr><th class="col-1">#</th><th>Risico</th><th class="col-2">Eigenaar</th><th class="col-2">Prioriteit</th><th class="col-2">Status</th></tr></thead>
                    <tbody id="crListBody"></tbody>
                </table></div>
            </div>
            <div class="card" id="crDetail" style="padding:20px"></div>
        </div>
    `;

    document.getElementById('crZoekInput').value = getParams().get('q') || '';
    document.getElementById('crZoekInput').addEventListener('input', debounce(() => {
        const params = getParams();
        const q = document.getElementById('crZoekInput').value.trim();
        if (q) { params.set('q', q); } else { params.delete('q'); }
        window.history.replaceState({}, '', '/cyberrisicos' + (params.toString() ? `?${params}` : ''));
        load();
    }, 300));

    document.getElementById('crStatusSelect').addEventListener('change', (e) => {
        const params = getParams();
        if (e.target.value) { params.set('status', e.target.value); } else { params.delete('status'); }
        window.history.replaceState({}, '', '/cyberrisicos' + (params.toString() ? `?${params}` : ''));
        load();
    });
}

function debounce(fn, ms) {
    let t;
    return (...args) => { clearTimeout(t); t = setTimeout(() => fn(...args), ms); };
}

function renderKpis(prioriteitCounts) {
    const cards = [
        { key: 'kritiek', icon: 'bi-shield-x' },
        { key: 'hoog', icon: 'bi-shield-exclamation' },
        { key: 'middel', icon: 'bi-shield-exclamation' },
        { key: 'laag', icon: 'bi-shield-check' },
    ];
    document.getElementById('crKpis').innerHTML = cards.map((c) => {
        const token = PRIO_TO_RISK_TOKEN[c.key];
        return `
        <div class="kpi-card">
            <div class="kpi-card-head"><span class="kpi-label">${esc(PRIO_LABELS[c.key])}</span>
                <span class="kpi-icon" style="background:var(--color-risk-${token}-bg);color:var(--color-risk-${token})"><i class="bi ${c.icon}"></i></span></div>
            <div class="kpi-value">${prioriteitCounts[c.key] ?? 0}</div>
        </div>`;
    }).join('');
}

function renderStatusOptions(filterOptions) {
    const select = document.getElementById('crStatusSelect');
    const current = getParams().get('status') || '';
    const opts = Object.entries(filterOptions.status).map(([val, label]) =>
        `<option value="${esc(val)}"${current === val ? ' selected' : ''}>${esc(label)}</option>`).join('');
    select.innerHTML = `<option value="">Alle statussen (excl. opgelost/geaccepteerd)</option>${opts}`;
}

function renderList(items) {
    currentItems = items;
    const body = document.getElementById('crListBody');

    if (items.length === 0) {
        body.innerHTML = '<tr><td colspan="5" class="empty-state">Geen risico\'s gevonden.</td></tr>';
        return;
    }

    if (selectedId === null || !items.some((r) => r.id === selectedId)) {
        selectedId = items[0].id;
    }

    body.innerHTML = items.map((r) => `
        <tr data-id="${r.id}" style="cursor:pointer${r.id === selectedId ? ';background:var(--color-background-secondary)' : ''}">
            <td class="col-1"><span class="mono" style="color:var(--color-text-tertiary)">#${r.id}</span></td>
            <td>
                <span class="text-truncate d-block" style="font-weight:500">${esc(r.titel)}${r.is_gevoelig ? ' <span class="badge" style="background:#FBEAEA;color:#b3261e">Gevoelig</span>' : ''}</span>
            </td>
            <td class="col-2" style="color:var(--color-text-tertiary)">${esc(r.eigenaar_naam || '—')}</td>
            <td class="col-2">${prioBadge(r.prioriteit)}</td>
            <td class="col-2">${statusBadge(r.status)}</td>
        </tr>
    `).join('');

    body.querySelectorAll('tr[data-id]').forEach((tr) => {
        tr.addEventListener('click', () => selectItem(parseInt(tr.dataset.id, 10)));
    });
}

function selectItem(id) {
    selectedId = id;
    window.history.pushState({}, '', `/cyberrisicos/${id}`);
    renderList(currentItems);
    loadDetail(id);
}

window.addEventListener('popstate', () => {
    selectedId = pathId();
    renderList(currentItems);
    if (selectedId) loadDetail(selectedId);
});

function flash(message) {
    const detail = document.getElementById('crDetail');
    const el = document.createElement('div');
    el.className = 'alert alert-error';
    el.textContent = message;
    detail.prepend(el);
    window.setTimeout(() => el.remove(), 4000);
}

let detailState = null;

async function loadDetail(id) {
    const detail = document.getElementById('crDetail');
    detail.innerHTML = '<div class="empty-state">Laden&hellip;</div>';

    try {
        const res = await api.get(`/api/v1/cyberrisicos/${id}`);
        detailState = res.data;
        renderDetail();
    } catch (e) {
        const message = e instanceof ApiError ? e.message : 'Kon risico niet laden.';
        detail.innerHTML = `<div class="empty-state" style="color:var(--color-text-danger)">${esc(message)}</div>`;
    }
}

function renderDetail() {
    const { item, logs } = detailState;

    document.getElementById('crDetail').innerHTML = `
        <div style="display:flex;align-items:center;gap:8px">
            <span class="mono" style="font-size:11px;color:var(--color-text-tertiary)">#${item.id}</span>
            ${prioBadge(item.prioriteit)}
            ${statusBadge(item.status)}
        </div>
        <h2 style="font-size:17px;font-weight:600;margin:6px 0 0">${esc(item.titel)}</h2>
        <p style="font-size:13px;color:var(--color-text-secondary);line-height:1.6;margin-top:8px">${nl2br(item.omschrijving)}</p>
        ${item.oplossingsadvies ? `<div style="margin-top:10px"><div class="detail-side-heading">Oplossingsadvies</div><p style="font-size:12.5px;color:var(--color-text-secondary);line-height:1.6">${nl2br(item.oplossingsadvies)}</p></div>` : ''}

        <div style="margin-top:14px;border-top:0.5px solid var(--color-border-tertiary);padding-top:12px">
            <div class="meta-row"><span class="meta-key">Eigenaar</span><span>${esc(item.eigenaar_naam || '—')}</span></div>
            <div class="meta-row"><span class="meta-key">Afdeling</span><span>${esc(item.afdeling_naam || '—')}</span></div>
            <div class="meta-row"><span class="meta-key">Locatie</span><span>${esc(item.locatie || '—')}</span></div>
            <div class="meta-row"><span class="meta-key">Gemeld door</span><span>${esc(item.gemeld_door || '—')}</span></div>
            <div class="meta-row"><span class="meta-key">Datum gemeld</span><span>${formatDatum(item.datum_gemeld)}</span></div>
        </div>

        <div style="margin-top:14px;border-top:0.5px solid var(--color-border-tertiary);padding-top:12px">
            <h3 class="detail-side-heading">Status wijzigen</h3>
            <div class="status-picker">
                ${Object.entries(STATUS_LABELS).map(([val, label]) => `
                    <button type="button" data-status="${val}" class="${item.status === val ? 'active' : ''}"
                        style="background:var(--color-background-secondary)">${esc(label)}</button>`).join('')}
            </div>
        </div>

        <div style="margin-top:14px;border-top:0.5px solid var(--color-border-tertiary);padding-top:12px">
            <h3 class="detail-side-heading">Opmerkingen</h3>
            <div style="margin-bottom:10px">
                <input type="text" id="crLogTitel" placeholder="Korte titel" style="margin-bottom:6px">
                <textarea id="crLogTekst" placeholder="Opmerking&hellip;" style="min-height:60px"></textarea>
                <button class="btn" type="button" id="crLogOpslaanBtn" style="margin-top:6px">Opslaan</button>
            </div>
            ${logs.length === 0 ? '<div class="empty-state">Nog geen opmerkingen.</div>' : logs.map((l) => `
                <div class="log-item">
                    <div class="log-meta"><span class="log-user">${esc(l.user_naam || 'Onbekend')}</span><span class="log-time">${formatDatumTijd(l.created_at)}</span></div>
                    <div class="log-title" style="font-weight:600;margin-bottom:2px">${esc(l.titel)}</div>
                    <div class="log-text">${nl2br(l.omschrijving)}</div>
                </div>
            `).join('')}
        </div>

        <div style="margin-top:14px">
            <a class="btn" href="/cyberrisicos/${item.id}/edit">Bewerken</a>
        </div>
    `;

    document.querySelectorAll('.status-picker button[data-status]').forEach((btn) => {
        btn.addEventListener('click', async () => {
            try {
                const res = await api.put(`/api/v1/cyberrisicos/${item.id}/status`, { status: btn.dataset.status });
                detailState = res.data;
                renderDetail();
                renderList(currentItems.map((r) => (r.id === detailState.item.id ? { ...r, status: detailState.item.status } : r)));
            } catch (e) {
                flash(e instanceof ApiError ? e.message : 'Status wijzigen is mislukt.');
            }
        });
    });

    document.getElementById('crLogOpslaanBtn').addEventListener('click', async () => {
        const titel = document.getElementById('crLogTitel').value.trim();
        const omschrijving = document.getElementById('crLogTekst').value.trim();
        if (titel === '' || omschrijving === '') {
            flash('Vul zowel een titel als een omschrijving in.');
            return;
        }
        try {
            const res = await api.post(`/api/v1/cyberrisicos/${item.id}/log`, { titel, omschrijving });
            detailState = res.data;
            renderDetail();
        } catch (e) {
            flash(e instanceof ApiError ? e.message : 'Opslaan is mislukt.');
        }
    });
}

async function load() {
    if (!document.getElementById('crListBody')) {
        renderShell();
    }
    document.getElementById('crListBody').innerHTML = '<tr><td colspan="5" class="empty-state">Laden&hellip;</td></tr>';

    try {
        const res = await api.get(apiQuery());
        renderKpis(res.meta.prioriteitCounts);
        renderStatusOptions(res.meta.filterOptions);
        renderList(res.data);
        if (selectedId) {
            loadDetail(selectedId);
        }
    } catch (e) {
        const message = e instanceof ApiError ? e.message : "Kon cyberrisico's niet laden.";
        document.getElementById('crListBody').innerHTML =
            `<tr><td colspan="5" class="empty-state" style="color:var(--color-text-danger)">${esc(message)}</td></tr>`;
    }
}

renderShell();
load();
