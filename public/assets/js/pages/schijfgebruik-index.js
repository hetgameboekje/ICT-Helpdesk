import { api, ApiError } from '/assets/js/api/client.js';

/**
 * Schijfgebruik: lijst met ring-gauges per schijf, meest vol eerst, o.b.v.
 * src/routes/modules.schijfgebruik.tsx (Lovable MCP read_file, project
 * 4675b36f-276e-4fc5-9606-d83a98f9d801). Rijen linken door naar de bestaande, server-rendered
 * apparaatpagina (/schijfgebruik/{device_id}, met medewerker-koppeling) — die blijft ongewijzigd.
 *
 * Uitbreiding t.o.v. de mockup (echte, extra data i.p.v. weggelaten): online/offline-status en
 * "herstart aanbevolen"/"schijf bijna vol"-waarschuwingen komen uit de bestaande
 * SchijfgebruikHealth::evaluate() en zijn toegevoegd naast de ring-gauge, i.p.v. de mockup's
 * kalere per-volume-rij. CSV-import blijft een gewoon multipart-formulier in de shell-view.
 */

function esc(value) {
    const div = document.createElement('div');
    div.textContent = value ?? '';
    return div.innerHTML;
}
function formatDatumTijd(value) {
    if (!value) return '—';
    const [datum, tijd] = String(value).split(' ');
    const [y, m, d] = String(datum || '').slice(0, 10).split('-');
    return y ? `${d}-${m}-${y} ${(tijd || '').slice(0, 5)}` : '—';
}
function colorFor(pct) {
    if (pct >= 90) return { fg: 'var(--color-stock-low)', bg: 'var(--color-stock-low-bg)' };
    if (pct >= 75) return { fg: 'var(--color-status-wachtend)', bg: 'var(--color-status-wachtend-bg)' };
    return { fg: 'var(--color-stock-ok)', bg: 'var(--color-stock-ok-bg)' };
}
function ringGauge(pct) {
    const c = colorFor(pct);
    const r = 22;
    const dash = 2 * Math.PI * r;
    const offset = dash * (1 - pct / 100);
    return `
        <div style="position:relative;height:56px;width:56px;flex-shrink:0">
            <svg viewBox="0 0 50 50" style="height:56px;width:56px;transform:rotate(-90deg)">
                <circle cx="25" cy="25" r="${r}" fill="none" stroke="var(--color-background-tertiary)" stroke-width="4"></circle>
                <circle cx="25" cy="25" r="${r}" fill="none" stroke="${c.fg}" stroke-width="4" stroke-linecap="round"
                    stroke-dasharray="${dash}" stroke-dashoffset="${offset}"></circle>
            </svg>
            <div style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:600;color:${c.fg}">${pct}%</div>
        </div>
    `;
}

const root = document.getElementById('schijfgebruik-app');

function getParams() {
    return new URLSearchParams(window.location.search);
}

function apiQuery() {
    const qs = getParams().toString();
    return '/api/v1/schijfgebruik' + (qs ? `?${qs}` : '');
}

function renderShell() {
    root.innerHTML = `
        <section class="kpi-grid" id="sgKpis"></section>
        <form class="filters" id="sgFilters" style="margin-bottom:14px">
            <input type="text" name="q" id="sgZoekInput" placeholder="Zoeken op apparaat of gebruiker...">
            <select name="organisatie" id="sgOrgSelect"><option value="">Alle organisaties</option></select>
            <select name="locatie" id="sgLocatieSelect"><option value="">Alle locaties</option></select>
            <select name="letter" id="sgLetterSelect"><option value="">Alle schijven</option></select>
            <input type="number" name="min_gebruik" id="sgMinGebruik" min="0" max="100" placeholder="Min. gebruik %" style="width:140px">
            <label style="display:flex;align-items:center;gap:6px;font-weight:normal;font-size:13px">
                <input type="checkbox" name="alleen_waarschuwingen" id="sgAlleenWaarschuwingen" value="1"> Alleen apparaten met waarschuwingen
            </label>
            <button class="btn btn-primary" type="submit">Zoeken</button>
        </form>
        <div class="card" style="padding:0;overflow:hidden">
            <div id="sgList" class="list-group list-group-flush"></div>
        </div>
    `;

    const params = getParams();
    document.getElementById('sgZoekInput').value = params.get('q') || '';
    document.getElementById('sgMinGebruik').value = params.get('min_gebruik') || '';
    document.getElementById('sgAlleenWaarschuwingen').checked = params.get('alleen_waarschuwingen') === '1';

    document.getElementById('sgFilters').addEventListener('submit', (e) => {
        e.preventDefault();
        const form = new FormData(e.target);
        const next = new URLSearchParams();
        for (const [key, value] of form.entries()) {
            if (value !== '') next.set(key, value);
        }
        window.history.replaceState({}, '', '/schijfgebruik' + (next.toString() ? `?${next}` : ''));
        load();
    });
}

function renderKpis(stats) {
    const cards = [
        { label: 'Volumes', value: stats.volumes, icon: 'bi-hdd', tone: 'behandeling' },
        { label: 'Totaal', value: `${stats.totaalTb.toFixed(1)} TB`, icon: 'bi-server', tone: 'opgelost' },
        { label: 'Gebruikt', value: `${stats.gebruiktTb.toFixed(1)} TB`, icon: 'bi-database', tone: 'wachtend' },
        { label: '> 90% vol', value: stats.kritiek, icon: 'bi-exclamation-triangle', tone: 'gesloten' },
    ];
    document.getElementById('sgKpis').innerHTML = cards.map((c) => `
        <div class="kpi-card">
            <div class="kpi-card-head"><span class="kpi-label">${esc(c.label)}</span>
                <span class="kpi-icon kpi-icon-${c.tone}"><i class="bi ${c.icon}"></i></span></div>
            <div class="kpi-value">${c.value}</div>
        </div>
    `).join('');
}

function renderFilterOptions(filterOptions) {
    const params = getParams();
    const fill = (id, name, options) => {
        const current = params.get(name) || '';
        const select = document.getElementById(id);
        const opts = Object.entries(options).map(([val, label]) =>
            `<option value="${esc(val)}"${current === val ? ' selected' : ''}>${esc(label)}</option>`).join('');
        select.innerHTML = select.querySelector('option').outerHTML + opts;
    };
    fill('sgOrgSelect', 'organisatie', filterOptions.organisatie);
    fill('sgLocatieSelect', 'locatie', filterOptions.locatie);
    fill('sgLetterSelect', 'letter', filterOptions.letter);
}

function renderList(items) {
    const list = document.getElementById('sgList');

    if (items.length === 0) {
        list.innerHTML = '<div class="empty-state">Geen apparaten/schijven gevonden. Importeer hierboven een CSV-export.</div>';
        return;
    }

    list.innerHTML = items.map((r) => {
        const pct = parseInt(r.gebruik_percentage, 10) || 0;
        const statusBadge = r.is_online
            ? '<span class="badge" style="background:#198754;color:#fff">Online</span>'
            : `<span class="badge" style="background:#dc3545;color:#fff">Offline${r.dagen_offline !== null ? ` (${r.dagen_offline}d)` : ''}</span>`;
        const waarschuwingTitel = (r.waarschuwingen || []).join(' — ');

        return `
        <a class="list-group-item" href="/schijfgebruik/${r.device_id}" style="display:flex;align-items:center;gap:16px;color:inherit;text-decoration:none">
            ${ringGauge(pct)}
            <div style="min-width:0;flex:1">
                <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
                    <span class="mono" style="font-weight:600;font-size:13.5px">${esc(r.naam)}</span>
                    <span style="color:var(--color-text-tertiary)">&middot;</span>
                    <span class="mono" style="font-size:13px">${esc(r.letter)}</span>
                    <span style="font-size:10px;font-weight:500;padding:2px 6px;border-radius:4px;background:var(--color-background-tertiary);color:var(--color-text-tertiary)">${esc(r.disk_type || '—')}</span>
                    ${statusBadge}
                    ${r.herstart_nodig ? '<i class="bi bi-arrow-clockwise" style="color:#fd7e14" title="Herstart aanbevolen"></i>' : ''}
                    ${waarschuwingTitel ? `<i class="bi bi-exclamation-triangle-fill" style="color:#dc3545" title="${esc(waarschuwingTitel)}"></i>` : ''}
                </div>
                <div style="font-size:11.5px;color:var(--color-text-tertiary);margin-top:4px">
                    ${esc(r.laatste_login || '—')} &middot; ${esc(r.organisatie || '—')} &middot; ${esc(r.locatie || '—')} &middot; laatst online ${formatDatumTijd(r.laatst_online)}
                </div>
                <div style="margin-top:6px;height:6px;border-radius:999px;background:var(--color-background-tertiary);overflow:hidden">
                    <div style="height:100%;width:${pct}%;background:${colorFor(pct).fg}"></div>
                </div>
            </div>
            <div style="font-size:11px;color:var(--color-text-tertiary);text-align:right;flex-shrink:0;width:100px">${esc(r.capaciteit_label || '—')}</div>
        </a>
        `;
    }).join('');
}

async function load() {
    if (!document.getElementById('sgList')) {
        renderShell();
    }
    document.getElementById('sgList').innerHTML = '<div class="empty-state">Laden&hellip;</div>';

    try {
        const res = await api.get(apiQuery());
        renderKpis(res.meta.stats);
        renderFilterOptions(res.meta.filterOptions);
        renderList(res.data);
    } catch (e) {
        const message = e instanceof ApiError ? e.message : 'Kon schijfgebruik niet laden.';
        document.getElementById('sgList').innerHTML =
            `<div class="empty-state" style="color:var(--color-text-danger)">${esc(message)}</div>`;
    }
}

renderShell();
load();
