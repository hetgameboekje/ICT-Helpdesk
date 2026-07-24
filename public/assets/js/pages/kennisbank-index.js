import { api, ApiError } from '/assets/js/api/client.js';

/**
 * Kennisbank: lijst + artikel in één split-view scherm, zoals in het Lovable-ontwerp
 * (src/routes/modules.kennisbank.tsx, opgehaald via de Lovable MCP — het gelijknamige bestand
 * onder docs/design/ staat NIET synchroon, dat bevat per abuis de MailMind-pagina; gebruik voor
 * volgende module-conversies altijd de Lovable MCP read_file-tool, niet docs/design/*.tsx).
 * Vervangt de eerdere server-rendered index.php/show.php-inhoud. /kennisbank en /kennisbank/{id}
 * renderen dezelfde shell (zie Views/KennisbankView/index.php en show.php) — dit bestand bepaalt
 * het geselecteerde artikel uit het URL-pad, er is geen aparte serverroute-render per artikel.
 *
 * Bewuste afwijkingen t.o.v. de Lovable-mockup (zie ook CLAUDE.md > "Nog te herdesignen"):
 * - Geen "alle/gepubliceerd/concept"-filtertabs: kennisbank_artikelen bevat alleen gepubliceerde
 *   artikelen, AI-conceptartikelen leven in een aparte reviewwachtrij (App\Modules\EmailVerwerking,
 *   /email-verwerking) — er is geen echte "concept"-status op een artikel in deze lijst.
 * - Geen inline "Goedkeuren/Afwijzen" op het artikel: die acties horen bij die reviewwachtrij, niet
 *   bij een gepubliceerd artikel hier. De "AI-concepten in review"-KPI linkt ernaartoe.
 * - Categoriefilter is een dropdown i.p.v. Lovable's linker boomstructuur (die in de mockup zelf
 *   niet voorkomt) — wel echte data (KennisbankService::list() > filterOptions.categorieBoom).
 * - KPI's "Views deze week"/"Verouderd (>6 mnd)" zijn vervangen door "Categorieën" en
 *   "AI-concepten in review" (geen view-telling/veroudering-drempel in het datamodel).
 */

function esc(value) {
    const div = document.createElement('div');
    div.textContent = value ?? '';
    return div.innerHTML;
}

function nl2br(value) {
    return esc(value).replace(/\n/g, '<br>');
}

const root = document.getElementById('kennisbank-app');

function pathArtikelId() {
    const m = window.location.pathname.match(/^\/kennisbank\/(\d+)$/);
    return m ? parseInt(m[1], 10) : null;
}

function getParams() {
    return new URLSearchParams(window.location.search);
}

function apiQuery() {
    const params = getParams();
    params.delete('open');
    const qs = params.toString();
    return '/api/v1/kennisbank' + (qs ? `?${qs}` : '');
}

let selectedId = pathArtikelId();
let currentItems = [];

function renderShell() {
    root.innerHTML = `
        <div class="page-header"><div class="page-title">Kennisbank</div></div>
        <section class="kpi-grid" id="kbKpis"></section>
        <div class="list-toolbar">
            <div class="search-input">
                <i class="bi bi-search"></i>
                <input type="text" id="kbZoekInput" placeholder="Zoek in titels, categorie&euml;n&hellip;">
            </div>
            <select id="kbCategorieSelect"><option value="">Alle categorie&euml;n</option></select>
            <a class="btn btn-accent" href="/kennisbank/create"><i class="bi bi-plus-lg"></i> Nieuw artikel</a>
        </div>
        <div class="kb-split" style="display:grid;grid-template-columns:minmax(0,420px) 1fr;gap:16px;align-items:start">
            <div class="card" style="padding:0;overflow:hidden">
                <div class="table-wrap"><table>
                    <thead><tr><th>Artikel</th></tr></thead>
                    <tbody id="kbListBody"></tbody>
                </table></div>
            </div>
            <div class="card" id="kbDetail" style="padding:24px"></div>
        </div>
    `;

    document.getElementById('kbZoekInput').value = getParams().get('q') || '';
    document.getElementById('kbZoekInput').addEventListener('input', debounce(() => {
        const params = getParams();
        const q = document.getElementById('kbZoekInput').value.trim();
        if (q) { params.set('q', q); } else { params.delete('q'); }
        window.history.replaceState({}, '', '/kennisbank' + (params.toString() ? `?${params}` : ''));
        load();
    }, 300));

    document.getElementById('kbCategorieSelect').addEventListener('change', (e) => {
        const params = getParams();
        if (e.target.value) { params.set('categorie', e.target.value); } else { params.delete('categorie'); }
        window.history.replaceState({}, '', '/kennisbank' + (params.toString() ? `?${params}` : ''));
        load();
    });
}

function debounce(fn, ms) {
    let t;
    return (...args) => { clearTimeout(t); t = setTimeout(() => fn(...args), ms); };
}

function renderKpis(kpis) {
    document.getElementById('kbKpis').innerHTML = `
        <div class="kpi-card">
            <div class="kpi-card-head"><span class="kpi-label">Artikelen</span>
                <span class="kpi-icon kpi-icon-opgelost"><i class="bi bi-journal-bookmark"></i></span></div>
            <div class="kpi-value">${kpis.aantalArtikelen}</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-card-head"><span class="kpi-label">Categorie&euml;n</span>
                <span class="kpi-icon kpi-icon-neutral"><i class="bi bi-tags"></i></span></div>
            <div class="kpi-value">${kpis.aantalCategorieen}</div>
        </div>
        <a class="kpi-card" href="/email-verwerking">
            <div class="kpi-card-head"><span class="kpi-label">AI-concepten in review</span>
                <span class="kpi-icon kpi-icon-behandeling"><i class="bi bi-stars"></i></span></div>
            <div class="kpi-value">${kpis.conceptenInReview}</div>
        </a>
    `;
}

function renderCategorieOptions(categorieBoom) {
    const select = document.getElementById('kbCategorieSelect');
    const current = getParams().get('categorie') || '';
    const opts = categorieBoom.map((c) =>
        `<option value="${esc(c.naam)}"${current === c.naam ? ' selected' : ''}>${esc(c.naam)} (${c.aantal})</option>`
    ).join('');
    select.innerHTML = `<option value="">Alle categorie&euml;n</option>${opts}`;
}

function renderList(items) {
    currentItems = items;
    const body = document.getElementById('kbListBody');

    if (items.length === 0) {
        body.innerHTML = '<tr><td class="empty-state">Geen artikelen gevonden.</td></tr>';
        return;
    }

    if (selectedId === null || !items.some((a) => a.id === selectedId)) {
        selectedId = items[0].id;
    }

    body.innerHTML = items.map((a) => `
        <tr data-id="${a.id}" class="${a.id === selectedId ? 'kb-row-active' : ''}" style="cursor:pointer${a.id === selectedId ? ';background:var(--color-background-secondary)' : ''}">
            <td>
                <div style="display:flex;align-items:center;gap:8px">
                    <span class="mono" style="font-size:10px;color:var(--color-text-tertiary)">#${a.id}</span>
                </div>
                <div class="text-truncate" style="font-weight:500;margin-top:2px">${esc(a.titel)}</div>
                <div style="font-size:11px;color:var(--color-text-tertiary);margin-top:2px">
                    ${esc(a.categorie)}${a.subcategorie ? ' &middot; ' + esc(a.subcategorie) : ''} &middot; ${esc(a.auteur_naam || '—')}
                </div>
            </td>
        </tr>
    `).join('');

    body.querySelectorAll('tr[data-id]').forEach((tr) => {
        tr.addEventListener('click', () => selectArtikel(parseInt(tr.dataset.id, 10)));
    });
}

function selectArtikel(id) {
    selectedId = id;
    window.history.pushState({}, '', `/kennisbank/${id}`);
    renderList(currentItems);
    loadDetail(id);
}

window.addEventListener('popstate', () => {
    selectedId = pathArtikelId();
    renderList(currentItems);
    if (selectedId) loadDetail(selectedId);
});

async function loadDetail(id) {
    const detail = document.getElementById('kbDetail');
    detail.innerHTML = '<div class="empty-state">Laden&hellip;</div>';

    try {
        const res = await api.get(`/api/v1/kennisbank/${id}`);
        renderDetail(res.data.item, res.data.gekoppeldeTickets);
    } catch (e) {
        const message = e instanceof ApiError ? e.message : 'Kon artikel niet laden.';
        detail.innerHTML = `<div class="empty-state" style="color:var(--color-text-danger)">${esc(message)}</div>`;
    }
}

function renderDetail(item, gekoppeldeTickets) {
    const tags = (item.tags || '').split(',').map((t) => t.trim()).filter(Boolean);

    document.getElementById('kbDetail').innerHTML = `
        <div style="display:flex;align-items:start;justify-content:space-between;gap:16px;margin-bottom:12px">
            <div style="min-width:0">
                <div style="font-size:11px;color:var(--color-text-tertiary);margin-bottom:4px">
                    <span class="mono">#${item.id}</span> &middot; ${esc(item.categorie)}${item.subcategorie ? ' &middot; ' + esc(item.subcategorie) : ''}
                </div>
                <h2 style="font-size:20px;font-weight:600;margin:0">${esc(item.titel)}</h2>
                <div style="font-size:11px;color:var(--color-text-tertiary);margin-top:6px;display:flex;gap:12px">
                    <span><i class="bi bi-person"></i> ${esc(item.auteur_naam || '—')}</span>
                    <span><i class="bi bi-clock"></i> ${esc(String(item.updated_at || '').slice(0, 10))}</span>
                </div>
            </div>
            <a class="btn btn-ghost" href="/kennisbank/${item.id}/edit">Bewerken</a>
        </div>
        ${tags.length ? `<div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:12px">${tags.map((t) => `<span class="filter-chip">${esc(t)}</span>`).join('')}</div>` : ''}
        <div style="font-size:13px;line-height:1.7;border-top:0.5px solid var(--color-border-tertiary);padding-top:16px">
            ${nl2br(item.inhoud)}
        </div>
        <div style="border-top:0.5px solid var(--color-border-tertiary);margin-top:20px;padding-top:16px">
            <h3 style="font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.04em;color:var(--color-text-tertiary);margin:0 0 8px">
                Gekoppelde tickets
            </h3>
            ${gekoppeldeTickets.length === 0
                ? '<p style="font-size:12px;color:var(--color-text-tertiary)">Nog geen tickets aan dit artikel gekoppeld.</p>'
                : `<ul style="list-style:none;padding:0;margin:0;display:flex;flex-direction:column;gap:6px">
                    ${gekoppeldeTickets.map((t) => `
                        <li><a href="/tickets/${t.id}" style="display:flex;align-items:center;gap:8px;font-size:13px">
                            <span class="mono" style="font-size:11px;color:var(--color-text-tertiary)">#${t.id}</span>
                            <span class="text-truncate">${esc(t.titel)}</span>
                        </a></li>
                    `).join('')}
                   </ul>`}
        </div>
    `;
}

async function load() {
    if (!document.getElementById('kbListBody')) {
        renderShell();
    }
    document.getElementById('kbListBody').innerHTML = '<tr><td class="empty-state">Laden&hellip;</td></tr>';

    try {
        const res = await api.get(apiQuery());
        renderKpis(res.meta.kpis);
        renderCategorieOptions(res.meta.filterOptions.categorieBoom);
        renderList(res.data);
        if (selectedId) {
            loadDetail(selectedId);
        }
    } catch (e) {
        const message = e instanceof ApiError ? e.message : 'Kon kennisbank niet laden.';
        document.getElementById('kbListBody').innerHTML =
            `<tr><td class="empty-state" style="color:var(--color-text-danger)">${esc(message)}</td></tr>`;
    }
}

renderShell();
load();
