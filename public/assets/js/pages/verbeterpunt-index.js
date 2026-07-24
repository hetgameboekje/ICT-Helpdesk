import { api, ApiError } from '/assets/js/api/client.js';

/**
 * Verbeterpunten: lijst + detail in één split-view scherm, zelfde opzet als kennisbank-index.js,
 * o.b.v. src/routes/modules.verbeterpunt.tsx (opgehaald via de Lovable MCP read_file-tool tegen
 * project 4675b36f-276e-4fc5-9606-d83a98f9d801 — niet de gecommitte kopie onder docs/design/, die
 * staat niet betrouwbaar synchroon, zie CLAUDE.md).
 *
 * Bewuste afwijkingen t.o.v. de mockup:
 * - Echte statusflow is nieuw → in_overweging/goedgekeurd/afgewezen → uitgevoerd (5 waarden,
 *   verbeterpunten-tabel), niet Lovable's mockdata-flow voorgesteld/in-uitvoering/afgerond (3
 *   waarden) — de KPI's en status-badges volgen daarom de echte kolomwaarden.
 * - "Gem. doorlooptijd"-KPI (verzonnen mock-getal) vervangen door een 4e echte statustelling.
 * - Tijdregistratie- en logboeksecties in het detailpaneel komen niet in de mockup voor (die kent
 *   geen tijd/log-concept) maar bestonden al in de oude server-rendered show.php — hier
 *   overgenomen zodat er geen functionaliteit verloren gaat bij de conversie.
 */

const STATUS_LABELS = {
    nieuw: 'Nieuw', in_overweging: 'In overweging', goedgekeurd: 'Goedgekeurd', afgewezen: 'Afgewezen', uitgevoerd: 'Uitgevoerd',
};

function esc(value) {
    const div = document.createElement('div');
    div.textContent = value ?? '';
    return div.innerHTML;
}
function nl2br(value) {
    return esc(value).replace(/\n/g, '<br>');
}
function formatDatumTijd(value) {
    if (!value) return '—';
    const [datum, tijd] = String(value).split(' ');
    const [y, m, d] = String(datum).slice(0, 10).split('-');
    return `${d}-${m}-${y} ${(tijd || '').slice(0, 5)}`;
}
function statusBadge(status) {
    return `<span class="badge badge-${esc(status)}">${esc(STATUS_LABELS[status] || status)}</span>`;
}

const root = document.getElementById('verbeterpunt-app');

function pathId() {
    const m = window.location.pathname.match(/^\/verbeterpunten\/(\d+)$/);
    return m ? parseInt(m[1], 10) : null;
}

function getParams() {
    return new URLSearchParams(window.location.search);
}

function apiQuery() {
    const qs = getParams().toString();
    return '/api/v1/verbeterpunten' + (qs ? `?${qs}` : '');
}

let selectedId = pathId();
let currentItems = [];

function renderShell() {
    root.innerHTML = `
        <div class="page-header"><div class="page-title">Verbeterpunten</div></div>
        <section class="kpi-grid" id="vpKpis"></section>
        <div class="list-toolbar">
            <div class="search-input">
                <i class="bi bi-search"></i>
                <input type="text" id="vpZoekInput" placeholder="Zoek verbeterpunt&hellip;">
            </div>
            <select id="vpStatusSelect"><option value="">Alle statussen</option></select>
            <a class="btn btn-accent" href="/verbeterpunten/create"><i class="bi bi-plus-lg"></i> Nieuw</a>
        </div>
        <div style="display:grid;grid-template-columns:minmax(0,420px) 1fr;gap:16px;align-items:start">
            <div class="card" style="padding:0;overflow:hidden">
                <div id="vpListBody"></div>
            </div>
            <div class="card" id="vpDetail" style="padding:24px"></div>
        </div>
    `;

    document.getElementById('vpZoekInput').value = getParams().get('q') || '';
    document.getElementById('vpZoekInput').addEventListener('input', debounce(() => {
        const params = getParams();
        const q = document.getElementById('vpZoekInput').value.trim();
        if (q) { params.set('q', q); } else { params.delete('q'); }
        window.history.replaceState({}, '', '/verbeterpunten' + (params.toString() ? `?${params}` : ''));
        load();
    }, 300));

    document.getElementById('vpStatusSelect').addEventListener('change', (e) => {
        const params = getParams();
        if (e.target.value) { params.set('status', e.target.value); } else { params.delete('status'); }
        window.history.replaceState({}, '', '/verbeterpunten' + (params.toString() ? `?${params}` : ''));
        load();
    });
}

function debounce(fn, ms) {
    let t;
    return (...args) => { clearTimeout(t); t = setTimeout(() => fn(...args), ms); };
}

function renderKpis(statusCounts) {
    const cards = [
        { label: 'Nieuw', key: 'nieuw', icon: 'bi-lightbulb', tone: 'behandeling' },
        { label: 'In overweging', key: 'in_overweging', icon: 'bi-hourglass-split', tone: 'wachtend' },
        { label: 'Goedgekeurd', key: 'goedgekeurd', icon: 'bi-check2-circle', tone: 'opgelost' },
        { label: 'Uitgevoerd', key: 'uitgevoerd', icon: 'bi-trophy', tone: 'opgelost' },
    ];
    document.getElementById('vpKpis').innerHTML = cards.map((c) => `
        <div class="kpi-card">
            <div class="kpi-card-head"><span class="kpi-label">${esc(c.label)}</span>
                <span class="kpi-icon kpi-icon-${c.tone}"><i class="bi ${c.icon}"></i></span></div>
            <div class="kpi-value">${statusCounts[c.key] ?? 0}</div>
        </div>
    `).join('');
}

function renderStatusOptions(filterOptions) {
    const select = document.getElementById('vpStatusSelect');
    const current = getParams().get('status') || '';
    const opts = Object.entries(filterOptions.status).map(([val, label]) =>
        `<option value="${esc(val)}"${current === val ? ' selected' : ''}>${esc(label)}</option>`).join('');
    select.innerHTML = `<option value="">Alle statussen</option>${opts}`;
}

function renderList(items) {
    currentItems = items;
    const body = document.getElementById('vpListBody');

    if (items.length === 0) {
        body.innerHTML = '<div class="empty-state">Geen verbeterpunten gevonden.</div>';
        return;
    }

    if (selectedId === null || !items.some((v) => v.id === selectedId)) {
        selectedId = items[0].id;
    }

    body.innerHTML = `<div class="list-group list-group-flush">${items.map((v) => `
        <div class="list-group-item" data-id="${v.id}" style="cursor:pointer${v.id === selectedId ? ';background:var(--color-background-secondary)' : ''}">
            <div style="display:flex;align-items:center;gap:8px">
                <span class="mono" style="font-size:10px;color:var(--color-text-tertiary)">#${v.id}</span>
                ${statusBadge(v.status)}
            </div>
            <div class="text-truncate" style="font-weight:500;margin-top:4px">${esc(v.titel)}</div>
            <div style="font-size:11px;color:var(--color-text-tertiary);margin-top:2px">
                ${esc(v.ingediend_door_naam || '—')} &middot; ${esc(v.afdeling_naam || '—')}
            </div>
        </div>
    `).join('')}</div>`;

    body.querySelectorAll('[data-id]').forEach((el) => {
        el.addEventListener('click', () => selectItem(parseInt(el.dataset.id, 10)));
    });
}

function selectItem(id) {
    selectedId = id;
    window.history.pushState({}, '', `/verbeterpunten/${id}`);
    renderList(currentItems);
    loadDetail(id);
}

window.addEventListener('popstate', () => {
    selectedId = pathId();
    renderList(currentItems);
    if (selectedId) loadDetail(selectedId);
});

let detailState = null;

function flash(message) {
    const detail = document.getElementById('vpDetail');
    const el = document.createElement('div');
    el.className = 'alert alert-error';
    el.textContent = message;
    detail.prepend(el);
    window.setTimeout(() => el.remove(), 5000);
}

async function loadDetail(id) {
    const detail = document.getElementById('vpDetail');
    detail.innerHTML = '<div class="empty-state">Laden&hellip;</div>';

    try {
        const res = await api.get(`/api/v1/verbeterpunten/${id}`);
        detailState = res.data;
        renderDetail();
    } catch (e) {
        const message = e instanceof ApiError ? e.message : 'Kon verbeterpunt niet laden.';
        detail.innerHTML = `<div class="empty-state" style="color:var(--color-text-danger)">${esc(message)}</div>`;
    }
}

function renderDetail() {
    const { item, logs, tijdregistraties, tijdTotaal } = detailState;
    const opmerkingen = logs.filter((l) => (l.titel || '').trim() !== '');
    const statusLogs = logs.filter((l) => l.status_naar !== null);

    document.getElementById('vpDetail').innerHTML = `
        <div style="display:flex;align-items:start;justify-content:space-between;gap:16px;margin-bottom:4px">
            <div style="min-width:0">
                <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px">
                    <span class="mono" style="font-size:11px;color:var(--color-text-tertiary)">#${item.id}</span>
                    ${statusBadge(item.status)}
                </div>
                <h2 style="font-size:20px;font-weight:600;margin:0">${esc(item.titel)}</h2>
            </div>
            <div style="display:flex;gap:6px;flex-shrink:0">
                <a class="btn btn-ghost" href="/verbeterpunten/${item.id}/edit">Bewerken</a>
            </div>
        </div>
        <p style="font-size:13px;color:var(--color-text-secondary);line-height:1.7;margin-top:8px">${nl2br(item.omschrijving)}</p>

        <div class="detail-layout" style="margin-top:16px;grid-template-columns:1fr 280px">
            <div>
                <div class="card" style="margin-bottom:16px">
                    <div class="card-header"><span class="card-title">Opmerkingen</span></div>
                    <div style="padding:16px;border-bottom:0.5px solid var(--color-border-tertiary)">
                        <div class="form-group" style="margin-bottom:8px">
                            <label class="form-label">Titel</label>
                            <input type="text" id="vpLogTitel" placeholder="Korte titel voor deze opmerking">
                        </div>
                        <textarea id="vpLogTekst" placeholder="Beschrijf de voortgang of vraag om meer informatie&hellip;" style="min-height:80px"></textarea>
                        <button class="btn btn-primary" type="button" id="vpLogOpslaanBtn" style="margin-top:8px">Opslaan</button>
                    </div>
                    ${opmerkingen.length === 0 ? '<div class="empty-state">Nog geen opmerkingen.</div>' : opmerkingen.map((l) => `
                        <div class="log-item">
                            <div class="log-meta"><span class="log-user">${esc(l.user_naam || 'Onbekend')}</span><span class="log-time">${formatDatumTijd(l.created_at)}</span></div>
                            <div class="log-title" style="font-weight:600;margin-bottom:2px">${esc(l.titel)}</div>
                            ${l.opmerking ? `<div class="log-text">${nl2br(l.opmerking)}</div>` : ''}
                        </div>
                    `).join('')}
                </div>
            </div>
            <div>
                <div class="card" style="margin-bottom:16px;padding:16px">
                    <h3 class="detail-side-heading">Details</h3>
                    <div class="meta-row"><span class="meta-key">Afdeling</span><span>${esc(item.afdeling_naam || '—')}</span></div>
                    <div class="meta-row"><span class="meta-key">Categorie</span><span>${esc(item.categorie || 'Algemeen')}</span></div>
                    <div class="meta-row"><span class="meta-key">Ingediend door</span><span>${esc(item.ingediend_door_naam || '—')}</span></div>
                </div>

                <div class="card" style="margin-bottom:16px;padding:16px">
                    <h3 class="detail-side-heading">Status wijzigen</h3>
                    <div class="status-picker">
                        ${Object.entries(STATUS_LABELS).map(([val, label]) => `
                            <button type="button" data-status="${val}" class="${item.status === val ? 'active' : ''}"
                                style="background:var(--color-background-secondary)">${esc(label)}</button>`).join('')}
                    </div>
                </div>

                <div class="card" style="margin-bottom:16px;padding:16px">
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px">
                        <h3 class="detail-side-heading" style="margin-bottom:0">Tijdregistratie</h3>
                        <span style="font-size:11px;color:var(--color-text-tertiary)">Totaal: ${tijdTotaal} min</span>
                    </div>
                    <div style="display:flex;gap:6px;flex-wrap:wrap">
                        ${[5, 10, 15, 30, 45, 60].map((m) => `<button class="btn" type="button" data-minuten="${m}" style="padding:4px 9px;font-size:11.5px">${m} min</button>`).join('')}
                    </div>
                    ${tijdregistraties.length ? `<div class="log-list" style="max-height:150px;margin:10px -16px -16px;border-top:0.5px solid var(--color-border-tertiary)">
                        ${tijdregistraties.map((t) => `
                            <div class="log-item">
                                <div class="log-meta"><span class="log-user">${esc(t.user_naam || 'Onbekend')}</span><span class="log-time">${formatDatumTijd(t.created_at)}</span></div>
                                <div class="log-text">${t.minuten} min</div>
                            </div>`).join('')}
                    </div>` : ''}
                </div>

                <div class="card" style="padding:16px">
                    <h3 class="detail-side-heading">Statuslogboek</h3>
                    ${statusLogs.length === 0 ? '<div class="empty-state">Nog geen statuswijzigingen.</div>' : statusLogs.map((l) => `
                        <div style="font-size:11.5px;color:var(--color-text-tertiary);padding:6px 0;border-top:0.5px solid var(--color-border-tertiary)">
                            ${esc(l.user_naam || 'Onbekend')} &middot; ${formatDatumTijd(l.created_at)}<br>
                            ${statusBadge(l.status_van)} &rarr; ${statusBadge(l.status_naar)}
                        </div>`).join('')}
                </div>
            </div>
        </div>
    `;

    wireDetailEvents();
}

function wireDetailEvents() {
    document.getElementById('vpLogOpslaanBtn').addEventListener('click', () => {
        const titel = document.getElementById('vpLogTitel').value.trim();
        const opmerking = document.getElementById('vpLogTekst').value.trim();
        if (opmerking !== '' && titel === '') {
            flash('Vul een titel in om deze opmerking op te slaan.');
            document.getElementById('vpLogTitel').focus();
            return;
        }
        saveLog({ titel, opmerking });
    });

    document.querySelectorAll('.status-picker button[data-status]').forEach((btn) => {
        btn.addEventListener('click', () => saveLog({ status: btn.dataset.status }));
    });

    document.querySelectorAll('button[data-minuten]').forEach((btn) => {
        btn.addEventListener('click', async () => {
            try {
                const res = await api.post(`/api/v1/verbeterpunten/${detailState.item.id}/tijd`, { minuten: parseInt(btn.dataset.minuten, 10) });
                Object.assign(detailState, res.data);
                renderDetail();
            } catch (e) {
                flash(e instanceof ApiError ? e.message : 'Tijd registreren is mislukt.');
            }
        });
    });
}

async function saveLog(fields) {
    try {
        const res = await api.post(`/api/v1/verbeterpunten/${detailState.item.id}/log`, fields);
        detailState = res.data;
        renderDetail();
        renderList(currentItems.map((v) => (v.id === detailState.item.id ? { ...v, status: detailState.item.status } : v)));
    } catch (e) {
        flash(e instanceof ApiError ? e.message : 'Opslaan is mislukt.');
    }
}

async function load() {
    if (!document.getElementById('vpListBody')) {
        renderShell();
    }
    document.getElementById('vpListBody').innerHTML = '<div class="empty-state">Laden&hellip;</div>';

    try {
        const res = await api.get(apiQuery());
        renderKpis(res.meta.statusCounts);
        renderStatusOptions(res.meta.filterOptions);
        renderList(res.data);
        if (selectedId) {
            loadDetail(selectedId);
        }
    } catch (e) {
        const message = e instanceof ApiError ? e.message : 'Kon verbeterpunten niet laden.';
        document.getElementById('vpListBody').innerHTML =
            `<div class="empty-state" style="color:var(--color-text-danger)">${esc(message)}</div>`;
    }
}

renderShell();
load();
