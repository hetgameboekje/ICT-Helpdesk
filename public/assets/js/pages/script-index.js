import { api, ApiError } from '/assets/js/api/client.js';

/**
 * Scripts: lijst + terminal-stijl detail in één split-view scherm, o.b.v.
 * src/routes/modules.script.tsx (Lovable MCP read_file, project 4675b36f-276e-4fc5-9606-d83a98f9d801).
 *
 * Bewuste afwijking t.o.v. de mockup — zie ScriptService voor de reden: scripts zijn een
 * kopieer-en-plak-bibliotheek, geen remote-executie. Geen "laatst uitgevoerd"/status ok-fout-nooit
 * en geen "Uitvoeren"-knop (zou niets doen) — vervangen door een echte verdeling per script-type
 * in de KPI-rij en de bestaande "Kopiëren"-actie (behouden uit de oude server-rendered show.php).
 * Echte types zijn powershell/batch/bash/overig (geen python/sql zoals in de mockdata).
 */

const TYPE_LABELS = { powershell: 'PowerShell', batch: 'Batch', bash: 'Bash', overig: 'Overig' };
const TYPE_TONE = { powershell: 'open', batch: 'behandeling', bash: 'opgelost', overig: 'wachtend' };

function esc(value) {
    const div = document.createElement('div');
    div.textContent = value ?? '';
    return div.innerHTML;
}
function typeBadge(type) {
    const tone = TYPE_TONE[type] || 'wachtend';
    return `<span style="font-size:10px;font-weight:500;padding:2px 6px;border-radius:4px;background:var(--color-status-${tone}-bg);color:var(--color-status-${tone})">${esc(TYPE_LABELS[type] || type)}</span>`;
}
function formatDatum(value) {
    if (!value) return '—';
    const [y, m, d] = String(value).slice(0, 10).split('-');
    return `${d}-${m}-${y}`;
}

const root = document.getElementById('script-app');

function pathId() {
    const m = window.location.pathname.match(/^\/scripts\/(\d+)$/);
    return m ? parseInt(m[1], 10) : null;
}

function getParams() {
    return new URLSearchParams(window.location.search);
}

function apiQuery() {
    const qs = getParams().toString();
    return '/api/v1/scripts' + (qs ? `?${qs}` : '');
}

let selectedId = pathId();
let currentItems = [];

function renderShell() {
    root.innerHTML = `
        <div class="page-header">
            <div class="page-title">Scripts</div>
            <a class="btn btn-accent" href="/scripts/create"><i class="bi bi-plus-lg"></i> Nieuw script</a>
        </div>
        <section class="kpi-grid" id="scrKpis"></section>
        <div class="list-toolbar">
            <div class="search-input">
                <i class="bi bi-search"></i>
                <input type="text" id="scrZoekInput" placeholder="Zoek op titel&hellip;">
            </div>
        </div>
        <div style="display:grid;grid-template-columns:minmax(0,380px) 1fr;gap:16px;align-items:start">
            <div class="card" style="padding:0;overflow:hidden">
                <div id="scrListBody"></div>
            </div>
            <div class="card" id="scrDetail" style="padding:0;overflow:hidden"></div>
        </div>
    `;

    document.getElementById('scrZoekInput').value = getParams().get('q') || '';
    document.getElementById('scrZoekInput').addEventListener('input', debounce(() => {
        const params = getParams();
        const q = document.getElementById('scrZoekInput').value.trim();
        if (q) { params.set('q', q); } else { params.delete('q'); }
        window.history.replaceState({}, '', '/scripts' + (params.toString() ? `?${params}` : ''));
        load();
    }, 300));
}

function debounce(fn, ms) {
    let t;
    return (...args) => { clearTimeout(t); t = setTimeout(() => fn(...args), ms); };
}

function renderKpis(typeCounts) {
    const cards = [
        { label: 'Scripts', value: typeCounts.alle, icon: 'bi-code-slash', tone: 'behandeling' },
        { label: 'PowerShell', value: typeCounts.powershell, icon: 'bi-terminal', tone: 'open' },
        { label: 'Bash', value: typeCounts.bash, icon: 'bi-terminal', tone: 'opgelost' },
        { label: 'Batch / overig', value: typeCounts.batch + typeCounts.overig, icon: 'bi-file-code', tone: 'wachtend' },
    ];
    document.getElementById('scrKpis').innerHTML = cards.map((c) => `
        <div class="kpi-card">
            <div class="kpi-card-head"><span class="kpi-label">${esc(c.label)}</span>
                <span class="kpi-icon kpi-icon-${c.tone}"><i class="bi ${c.icon}"></i></span></div>
            <div class="kpi-value">${c.value ?? 0}</div>
        </div>
    `).join('');
}

function renderList(items) {
    currentItems = items;
    const body = document.getElementById('scrListBody');

    if (items.length === 0) {
        body.innerHTML = '<div class="empty-state">Geen scripts gevonden.</div>';
        return;
    }

    if (selectedId === null || !items.some((s) => s.id === selectedId)) {
        selectedId = items[0].id;
    }

    body.innerHTML = `<div class="list-group list-group-flush">${items.map((s) => `
        <div class="list-group-item" data-id="${s.id}" style="cursor:pointer${s.id === selectedId ? ';background:var(--color-background-secondary)' : ''}">
            <div style="display:flex;align-items:center;gap:6px">
                ${typeBadge(s.type || 'overig')}
                <span style="font-size:10px;color:var(--color-text-tertiary);margin-left:auto" class="mono">#${s.id}</span>
            </div>
            <div class="mono" style="font-size:13px;font-weight:500;margin-top:4px">${esc(s.titel)}</div>
            <div style="font-size:11px;color:var(--color-text-tertiary);margin-top:2px">${esc(s.auteur_naam || '—')} &middot; ${formatDatum(s.created_at)}</div>
        </div>
    `).join('')}</div>`;

    body.querySelectorAll('[data-id]').forEach((el) => {
        el.addEventListener('click', () => selectItem(parseInt(el.dataset.id, 10)));
    });
}

function selectItem(id) {
    selectedId = id;
    window.history.pushState({}, '', `/scripts/${id}`);
    renderList(currentItems);
    loadDetail(id);
}

window.addEventListener('popstate', () => {
    selectedId = pathId();
    renderList(currentItems);
    if (selectedId) loadDetail(selectedId);
});

async function loadDetail(id) {
    const detail = document.getElementById('scrDetail');
    detail.innerHTML = '<div class="empty-state">Laden&hellip;</div>';

    try {
        const res = await api.get(`/api/v1/scripts/${id}`);
        renderDetail(res.data.item);
    } catch (e) {
        const message = e instanceof ApiError ? e.message : 'Kon script niet laden.';
        detail.innerHTML = `<div class="empty-state" style="color:var(--color-text-danger)">${esc(message)}</div>`;
    }
}

function renderDetail(item) {
    document.getElementById('scrDetail').innerHTML = `
        <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;padding:14px 20px;border-bottom:0.5px solid var(--color-border-tertiary)">
            <div>
                <div style="display:flex;align-items:center;gap:8px">
                    <span class="mono" style="font-size:14px;font-weight:600">${esc(item.titel)}</span>
                    ${typeBadge(item.type || 'overig')}
                </div>
                ${item.omschrijving ? `<div style="font-size:12px;color:var(--color-text-tertiary);margin-top:2px">${esc(item.omschrijving)}</div>` : ''}
            </div>
            <div style="display:flex;gap:6px;flex-shrink:0">
                <a class="btn btn-ghost" href="/scripts/${item.id}/edit" style="font-size:12px">Bewerken</a>
            </div>
        </div>
        <div style="display:flex;align-items:center;gap:8px;padding:8px 20px;background:var(--color-background-secondary);font-size:11px;color:var(--color-text-tertiary);border-bottom:0.5px solid var(--color-border-tertiary)">
            <i class="bi bi-terminal"></i>
            <span>Auteur</span><span style="font-weight:500;color:var(--color-text-primary)">${esc(item.auteur_naam || '—')}</span>
            <span style="opacity:.4;margin:0 2px">&middot;</span>
            <span>Aangemaakt</span><span style="font-weight:500;color:var(--color-text-primary)">${formatDatum(item.created_at)}</span>
        </div>
        <div style="position:relative">
            <pre id="scrInhoud" style="background:oklch(0.18 0.015 250);color:oklch(0.9 0.01 250);font-family:var(--font-mono);font-size:12px;line-height:1.6;padding:20px;overflow-x:auto;margin:0"><code>${esc(item.inhoud)}</code></pre>
            <button type="button" class="btn" id="scrKopieerBtn" style="position:absolute;top:12px;right:12px">Kopiëren</button>
        </div>
    `;

    document.getElementById('scrKopieerBtn').addEventListener('click', (e) => {
        navigator.clipboard.writeText(item.inhoud);
        e.target.textContent = 'Gekopieerd!';
        window.setTimeout(() => { e.target.textContent = 'Kopiëren'; }, 1500);
    });
}

async function load() {
    if (!document.getElementById('scrListBody')) {
        renderShell();
    }
    document.getElementById('scrListBody').innerHTML = '<div class="empty-state">Laden&hellip;</div>';

    try {
        const res = await api.get(apiQuery());
        renderKpis(res.meta.typeCounts);
        renderList(res.data);
        if (selectedId) {
            loadDetail(selectedId);
        }
    } catch (e) {
        const message = e instanceof ApiError ? e.message : 'Kon scripts niet laden.';
        document.getElementById('scrListBody').innerHTML =
            `<div class="empty-state" style="color:var(--color-text-danger)">${esc(message)}</div>`;
    }
}

renderShell();
load();
