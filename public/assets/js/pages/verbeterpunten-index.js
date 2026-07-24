import { api, ApiError } from '/assets/js/api/client.js';

/**
 * Verbeterpunten: lijst + item in één split-view scherm, zoals in het Lovable-ontwerp
 * (src/routes/modules.verbeterpunt.tsx, via de Lovable MCP — zie CLAUDE.md over docs/design/*.tsx
 * niet betrouwbaar zijn). Vervangt de eerdere server-rendered index.php/show.php-inhoud.
 *
 * Bewuste afwijkingen t.o.v. de mockup:
 * - Onze status-flow heeft 5 stappen (nieuw/in_overweging/goedgekeurd/afgewezen/uitgevoerd), niet
 *   Lovable's 3 (voorgesteld/in-uitvoering/afgerond) — de 3-staps voortgangsbalk is daarom vervangen
 *   door een statusbadge + een echt select+opmerking-formulier (zelfde flow als Tickets se log).
 * - Geen "Eigenaar"/"Impact"-velden: die kolommen bestaan niet op verbeterpunten, weggelaten i.p.v.
 *   verzonnen. "Gem. doorlooptijd"-KPI idem (geen doorlooptijd-berekening in het datamodel).
 * - Logboek toont de echte verbeterpunt_logs (opmerkingen + statuswijzigingen), niet Lovable's
 *   drie hardcoded voorbeeldregels.
 * - Tijdregistratie bestaat in de backend maar niet in de Lovable-mockup — niet meegenomen hier.
 */

function esc(value) {
    const div = document.createElement('div');
    div.textContent = value ?? '';
    return div.innerHTML;
}

function nl2br(value) {
    return esc(value).replace(/\n/g, '<br>');
}

const STATUS_LABELS = {
    nieuw: 'Nieuw', in_overweging: 'In overweging', goedgekeurd: 'Goedgekeurd', afgewezen: 'Afgewezen', uitgevoerd: 'Uitgevoerd',
};
const KPI_STATUSSEN = ['nieuw', 'in_overweging', 'goedgekeurd', 'uitgevoerd'];

const root = document.getElementById('verbeterpunten-app');

function pathId() {
    const m = window.location.pathname.match(/^\/verbeterpunten\/(\d+)$/);
    return m ? parseInt(m[1], 10) : null;
}

function getParams() {
    return new URLSearchParams(window.location.search);
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
            <a class="btn btn-accent" href="/verbeterpunten/create"><i class="bi bi-plus-lg"></i> Nieuw</a>
        </div>
        <div class="kb-split" style="display:grid;grid-template-columns:minmax(0,420px) 1fr;gap:16px;align-items:start">
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
}

function debounce(fn, ms) {
    let t;
    return (...args) => { clearTimeout(t); t = setTimeout(() => fn(...args), ms); };
}

function renderKpis(statusCounts) {
    const icons = { nieuw: 'bi-lightbulb', in_overweging: 'bi-hourglass-split', goedgekeurd: 'bi-check-circle', uitgevoerd: 'bi-flag' };
    const tones = { nieuw: 'open', in_overweging: 'behandeling', goedgekeurd: 'opgelost', uitgevoerd: 'opgelost' };

    document.getElementById('vpKpis').innerHTML = KPI_STATUSSEN.map((s) => `
        <div class="kpi-card">
            <div class="kpi-card-head"><span class="kpi-label">${esc(STATUS_LABELS[s])}</span>
                <span class="kpi-icon kpi-icon-${tones[s]}"><i class="bi ${icons[s]}"></i></span></div>
            <div class="kpi-value">${statusCounts[s] ?? 0}</div>
        </div>
    `).join('');
}

function statusBadge(status) {
    return `<span class="badge badge-${esc(status)}">${esc(STATUS_LABELS[status] || status)}</span>`;
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

    body.innerHTML = `<div class="divide-list">${items.map((v) => `
        <div data-id="${v.id}" style="cursor:pointer;padding:12px 16px;border-bottom:0.5px solid var(--color-border-tertiary)${v.id === selectedId ? ';background:var(--color-background-secondary)' : ''}">
            <div style="display:flex;align-items:center;gap:8px">
                <span class="mono" style="font-size:10px;color:var(--color-text-tertiary)">#${v.id}</span>
                ${statusBadge(v.status)}
            </div>
            <div class="text-truncate" style="font-weight:500;margin-top:4px">${esc(v.titel)}</div>
            <div style="font-size:11px;color:var(--color-text-tertiary);margin-top:2px">
                ${esc(v.ingediend_door_naam || '—')} &middot; ${esc(v.categorie)} &middot; ${esc(String(v.created_at || '').slice(0, 10))}
            </div>
        </div>
    `).join('')}</div>`;

    body.querySelectorAll('[data-id]').forEach((row) => {
        row.addEventListener('click', () => selectItem(parseInt(row.dataset.id, 10)));
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

async function loadDetail(id) {
    const detail = document.getElementById('vpDetail');
    detail.innerHTML = '<div class="empty-state">Laden&hellip;</div>';

    try {
        const res = await api.get(`/api/v1/verbeterpunten/${id}`);
        renderDetail(res.data.item, res.data.logs);
    } catch (e) {
        const message = e instanceof ApiError ? e.message : 'Kon verbeterpunt niet laden.';
        detail.innerHTML = `<div class="empty-state" style="color:var(--color-text-danger)">${esc(message)}</div>`;
    }
}

function renderDetail(item, logs) {
    const statusOptions = Object.entries(STATUS_LABELS)
        .map(([val, label]) => `<option value="${val}"${item.status === val ? ' selected' : ''}>${esc(label)}</option>`)
        .join('');

    document.getElementById('vpDetail').innerHTML = `
        <div style="margin-bottom:12px">
            <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px">
                <span class="mono" style="font-size:11px;color:var(--color-text-tertiary)">#${item.id}</span>
                ${statusBadge(item.status)}
            </div>
            <h2 style="font-size:20px;font-weight:600;margin:0">${esc(item.titel)}</h2>
            <p style="font-size:13px;color:var(--color-text-secondary);margin-top:8px;line-height:1.6">${nl2br(item.omschrijving)}</p>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;font-size:13px;border-top:0.5px solid var(--color-border-tertiary);padding-top:12px">
            <div><div style="font-size:11px;text-transform:uppercase;color:var(--color-text-tertiary)">Indiener</div><div style="margin-top:2px">${esc(item.ingediend_door_naam || '—')}</div></div>
            <div><div style="font-size:11px;text-transform:uppercase;color:var(--color-text-tertiary)">Categorie</div><div style="margin-top:2px">${esc(item.categorie)}</div></div>
            <div><div style="font-size:11px;text-transform:uppercase;color:var(--color-text-tertiary)">Afdeling</div><div style="margin-top:2px">${esc(item.afdeling_naam || '—')}</div></div>
            <div><div style="font-size:11px;text-transform:uppercase;color:var(--color-text-tertiary)">Aangemaakt</div><div style="margin-top:2px">${esc(String(item.created_at || '').slice(0, 10))}</div></div>
        </div>
        <div style="border-top:0.5px solid var(--color-border-tertiary);margin-top:16px;padding-top:16px">
            <h3 style="font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.04em;color:var(--color-text-tertiary);margin:0 0 8px">Logboek</h3>
            <div id="vpLogList" style="display:flex;flex-direction:column;gap:8px;margin-bottom:12px">
                ${logs.length === 0 ? '<p style="font-size:12px;color:var(--color-text-tertiary)">Nog geen logregels.</p>' : logs.map((l) => `
                    <div style="font-size:12.5px">
                        <span class="mono" style="font-size:10px;color:var(--color-text-tertiary)">${esc(String(l.created_at || '').slice(0, 16).replace('T', ' '))}</span>
                        ${l.status_naar ? ` <span style="color:var(--color-text-tertiary)">status &rarr; ${esc(STATUS_LABELS[l.status_naar] || l.status_naar)}</span>` : ''}
                        ${l.titel ? `<div style="font-weight:500;margin-top:2px">${esc(l.titel)}</div>` : ''}
                        ${l.opmerking && l.titel ? `<div style="color:var(--color-text-secondary)">${esc(l.opmerking)}</div>` : ''}
                    </div>
                `).join('')}
            </div>
            <form id="vpLogForm" style="display:flex;flex-direction:column;gap:8px">
                <div style="display:flex;gap:8px">
                    <input type="text" name="titel" placeholder="Titel (voor een opmerking)" style="flex:1">
                    <select name="status">${statusOptions}</select>
                </div>
                <textarea name="opmerking" rows="2" placeholder="Voeg een notitie toe&hellip;"></textarea>
                <button class="btn btn-accent" type="submit" style="align-self:flex-end">Opslaan</button>
            </form>
        </div>
    `;

    document.getElementById('vpLogForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const form = e.target;
        const body = {
            titel: form.titel.value.trim(),
            opmerking: form.opmerking.value.trim(),
            status: form.status.value,
        };
        try {
            const res = await api.post(`/api/v1/verbeterpunten/${item.id}/log`, body);
            renderDetail(res.data.item, res.data.logs);
            renderList(currentItems.map((v) => (v.id === item.id ? { ...v, status: res.data.item.status } : v)));
        } catch (err) {
            const message = err instanceof ApiError ? err.message : 'Opslaan mislukt.';
            alert(message);
        }
    });
}

async function load() {
    if (!document.getElementById('vpListBody')) {
        renderShell();
    }
    document.getElementById('vpListBody').innerHTML = '<div class="empty-state">Laden&hellip;</div>';

    try {
        const res = await api.get('/api/v1/verbeterpunten' + (window.location.search || ''));
        renderKpis(res.meta.statusCounts);
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
