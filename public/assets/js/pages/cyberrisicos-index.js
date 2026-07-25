import { api, ApiError } from '/assets/js/api/client.js';
import { actionMenuHtml, wireActionMenu, openSheet } from '/assets/js/ui/panel-menu.js';

/**
 * Cyberrisico's: lijst + item in één split-view scherm, zoals in het Lovable-ontwerp
 * (src/routes/modules.cyberrisico.tsx, via de Lovable MCP). Vervangt de eerdere server-rendered
 * index.php/show.php-inhoud.
 *
 * Update: kans/impact (1-5) zijn inmiddels echte kolommen (database/xml/cyberrisicos.xml) — de
 * risicomatrix-widget is dus niet langer weggelaten maar 1-op-1 uit de mockup overgenomen
 * (kans x impact-grid met het geselecteerde vakje gemarkeerd). `prioriteit` blijft bestaan als
 * opgeslagen, filterbaar veld maar wordt nu serverside afgeleid uit kans x impact
 * (CyberRisicoService::prioriteitVanMatrix(), zelfde thresholds als Lovable's levelFrom()) i.p.v.
 * handmatig gekozen. Statusbadge + logboek (cyberrisico_logs) blijven daarnaast bestaan, want
 * `status` is een apart, echt workflow-veld dat in de mockup niet voorkomt.
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
    nieuw: 'Nieuw', in_onderzoek: 'In onderzoek', bevestigd: 'Bevestigd', opgelost: 'Opgelost', geaccepteerd: 'Geaccepteerd risico',
};
const PRIO_LABELS = { laag: 'Laag', middel: 'Middel', hoog: 'Hoog', kritiek: 'Kritiek' };
const PRIO_RISK_CLASS = { laag: 'laag', middel: 'gemiddeld', hoog: 'hoog', kritiek: 'kritiek' };

/** Zelfde thresholds als CyberRisicoService::prioriteitVanMatrix() (PHP), hier als CSS-risk-klasse. */
function levelFrom(kans, impact) {
    const s = kans * impact;
    if (s >= 20) return 'kritiek';
    if (s >= 12) return 'hoog';
    if (s >= 6) return 'gemiddeld';
    return 'laag';
}

function matrixHtml(item) {
    const kans = item.kans, impact = item.impact;
    const header = [1, 2, 3, 4, 5].map((k) => `<div style="font-size:10px;color:var(--color-text-tertiary);text-align:center">K${k}</div>`).join('');
    let rows = '';
    for (let i = 5; i >= 1; i--) {
        let cells = '';
        for (let k = 1; k <= 5; k++) {
            const niv = levelFrom(k, i);
            const active = kans === k && impact === i;
            cells += `<div style="aspect-ratio:1;border-radius:4px;display:flex;align-items:center;justify-content:center;font-weight:600;font-size:11px;background:var(--color-risk-${niv}-bg);color:var(--color-risk-${niv})${active ? ';box-shadow:inset 0 0 0 2px var(--color-text-primary)' : ''}">${active ? '●' : ''}</div>`;
        }
        rows += `<div style="font-size:10px;color:var(--color-text-tertiary);text-align:right;padding-right:4px;display:flex;align-items:center;justify-content:flex-end">I${i}</div>${cells}`;
    }

    return `
        <div style="font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.03em;color:var(--color-text-tertiary);margin-bottom:10px">Risicomatrix</div>
        <div style="display:grid;grid-template-columns:20px repeat(5,1fr);gap:4px">
            <div></div>${header}
            ${rows}
        </div>
        <div style="margin-top:10px;font-size:11px;color:var(--color-text-tertiary)">Kans <span class="mono" style="color:var(--color-text-primary)">${kans}</span> &times; Impact <span class="mono" style="color:var(--color-text-primary)">${impact}</span> = score <span class="mono" style="color:var(--color-text-primary)">${kans * impact}</span></div>
    `;
}

const root = document.getElementById('cyberrisicos-app');

function pathId() {
    const m = window.location.pathname.match(/^\/cyberrisicos\/(\d+)$/);
    return m ? parseInt(m[1], 10) : null;
}

function getParams() {
    return new URLSearchParams(window.location.search);
}

let selectedId = pathId();
let currentItems = [];

function renderShell() {
    root.innerHTML = `
        <div class="page-header"><div class="page-title">Cyberrisico's</div></div>
        <section class="kpi-grid" id="crKpis"></section>
        <div class="list-toolbar">
            <div class="search-input">
                <i class="bi bi-search"></i>
                <input type="text" id="crZoekInput" placeholder="Zoek risico&hellip;">
            </div>
            <a class="btn btn-accent" href="/cyberrisicos/create"><i class="bi bi-plus-lg"></i> Registreer risico</a>
        </div>
        <div class="kb-split" style="display:grid;grid-template-columns:minmax(0,1fr) 380px;gap:16px;align-items:start">
            <div class="card" style="padding:0;overflow:hidden">
                <div id="crListBody"></div>
            </div>
            <div style="display:flex;flex-direction:column;gap:16px">
                <div class="card" id="crDetail" style="padding:24px"></div>
                <div class="card" id="crMatrix" style="padding:24px"></div>
            </div>
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
}

function debounce(fn, ms) {
    let t;
    return (...args) => { clearTimeout(t); t = setTimeout(() => fn(...args), ms); };
}

function prioBadge(prio) {
    const cls = PRIO_RISK_CLASS[prio] || 'laag';
    return `<span class="badge badge-risico-${cls}">${esc(PRIO_LABELS[prio] || prio)}</span>`;
}

function statusBadge(status) {
    return `<span class="badge badge-${esc(status)}">${esc(STATUS_LABELS[status] || status)}</span>`;
}

function renderKpis(counts) {
    document.getElementById('crKpis').innerHTML = ['laag', 'middel', 'hoog', 'kritiek'].map((p) => `
        <div class="kpi-card">
            <div class="kpi-card-head"><span class="kpi-label">${esc(PRIO_LABELS[p])}</span>
                <span class="kpi-icon" style="background:var(--color-risk-${PRIO_RISK_CLASS[p]}-bg);color:var(--color-risk-${PRIO_RISK_CLASS[p]})"><i class="bi bi-shield-exclamation"></i></span></div>
            <div class="kpi-value">${counts[p] ?? 0}</div>
        </div>
    `).join('');
}

function renderList(items) {
    currentItems = items;
    const body = document.getElementById('crListBody');

    if (items.length === 0) {
        body.innerHTML = '<div class="empty-state">Geen risico\'s gevonden.</div>';
        return;
    }

    if (selectedId === null || !items.some((r) => r.id === selectedId)) {
        selectedId = items[0].id;
    }

    body.innerHTML = `
        <div class="table-wrap"><table>
            <thead><tr>
                <th style="width:70px">Nummer</th>
                <th>Risico</th>
                <th style="width:110px">Eigenaar</th>
                <th style="width:50px;text-align:center">Kans</th>
                <th style="width:55px;text-align:center">Impact</th>
                <th style="width:110px">Niveau</th>
                <th style="width:110px;text-align:right">Status</th>
            </tr></thead>
            <tbody>${items.map((r) => `
                <tr data-id="${r.id}" style="cursor:pointer${r.id === selectedId ? ';background:var(--color-background-secondary)' : ''}">
                    <td class="mono" style="font-size:11px;color:var(--color-text-tertiary)">#${r.id}</td>
                    <td>
                        <div class="text-truncate" style="font-weight:500">${esc(r.titel)}</div>
                        <div style="font-size:11px;color:var(--color-text-tertiary)">${esc(r.categorie)}</div>
                    </td>
                    <td style="font-size:12px">${esc(r.eigenaar_naam || '—')}</td>
                    <td class="mono" style="text-align:center;font-size:12px">${r.kans}</td>
                    <td class="mono" style="text-align:center;font-size:12px">${r.impact}</td>
                    <td>${prioBadge(r.prioriteit)}</td>
                    <td style="text-align:right">${statusBadge(r.status)}</td>
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
    window.history.pushState({}, '', `/cyberrisicos/${id}`);
    renderList(currentItems);
    loadDetail(id);
}

window.addEventListener('popstate', () => {
    selectedId = pathId();
    renderList(currentItems);
    if (selectedId) loadDetail(selectedId);
});

async function loadDetail(id) {
    const detail = document.getElementById('crDetail');
    detail.innerHTML = '<div class="empty-state">Laden&hellip;</div>';
    document.getElementById('crMatrix').innerHTML = '';

    try {
        const res = await api.get(`/api/v1/cyberrisicos/${id}`);
        renderDetail(res.data.item, res.data.logs);
    } catch (e) {
        const message = e instanceof ApiError ? e.message : 'Kon risico niet laden.';
        detail.innerHTML = `<div class="empty-state" style="color:var(--color-text-danger)">${esc(message)}</div>`;
    }
}

// Sheet-inhoud van het logboek staat, indien open, ingesteld op het item dat nu getoond wordt —
// zodat een statuswijziging of nieuwe logregel de open sheet live bijwerkt i.p.v. hem te sluiten.
let logSheetOpenForId = null;

function logboekSheetHtml(itemId, logs) {
    return `
        <div style="display:flex;flex-direction:column;gap:8px;margin-bottom:14px">
            ${logs.length === 0 ? '<p style="font-size:12px;color:var(--color-text-tertiary)">Nog geen logregels.</p>' : logs.map((l) => `
                <div style="font-size:12.5px;border-bottom:0.5px solid var(--color-border-tertiary);padding-bottom:8px">
                    <span class="mono" style="font-size:10px;color:var(--color-text-tertiary)">${esc(String(l.created_at || '').slice(0, 16).replace('T', ' '))}</span>
                    ${l.user_naam ? ` <span style="font-size:11px;color:var(--color-text-tertiary)">&middot; ${esc(l.user_naam)}</span>` : ''}
                    <div style="font-weight:500;margin-top:2px">${esc(l.titel)}</div>
                    <div style="color:var(--color-text-secondary)">${esc(l.omschrijving)}</div>
                </div>
            `).join('')}
        </div>
        <form id="crLogForm" style="display:flex;flex-direction:column;gap:8px">
            <input type="text" name="titel" placeholder="Titel" required>
            <textarea name="omschrijving" rows="2" placeholder="Omschrijving" required></textarea>
            <button class="btn btn-accent" type="submit" style="align-self:flex-end">Toevoegen</button>
        </form>
    `;
}

function openLogboekSheet(itemId, logs) {
    logSheetOpenForId = itemId;
    const body = openSheet('Logboek', logboekSheetHtml(itemId, logs));
    wireLogForm(body, itemId);
}

function wireLogForm(container, itemId) {
    const form = container.querySelector('#crLogForm');
    if (!form) return;
    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        try {
            const res = await api.post(`/api/v1/cyberrisicos/${itemId}/log`, {
                titel: form.titel.value.trim(),
                omschrijving: form.omschrijving.value.trim(),
            });
            renderDetail(res.data.item, res.data.logs);
            if (logSheetOpenForId === itemId) openLogboekSheet(itemId, res.data.logs);
        } catch (err) {
            alert(err instanceof ApiError ? err.message : 'Opslaan mislukt.');
        }
    });
}

function renderDetail(item, logs) {
    const statusOptions = Object.entries(STATUS_LABELS)
        .map(([val, label]) => `<option value="${val}"${item.status === val ? ' selected' : ''}>${esc(label)}</option>`)
        .join('');

    document.getElementById('crDetail').innerHTML = `
        <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:8px;margin-bottom:6px">
            <div style="display:flex;align-items:center;gap:8px">
                <span class="mono" style="font-size:11px;color:var(--color-text-tertiary)">#${item.id}</span>
                ${prioBadge(item.prioriteit)}${item.is_gevoelig == 1 ? ' <span class="badge badge-risico-kritiek"><i class="bi bi-eye-slash"></i> Gevoelig</span>' : ''}
            </div>
            ${actionMenuHtml('cr-detail')}
        </div>
        <h2 style="font-size:18px;font-weight:600;margin:0">${esc(item.titel)}</h2>
        <p style="font-size:13px;color:var(--color-text-secondary);margin-top:8px;line-height:1.6">${nl2br(item.omschrijving)}</p>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;font-size:12.5px;border-top:0.5px solid var(--color-border-tertiary);margin-top:12px;padding-top:12px">
            <div><div style="font-size:11px;text-transform:uppercase;color:var(--color-text-tertiary)">Eigenaar</div><div>${esc(item.eigenaar_naam || '—')}</div></div>
            <div><div style="font-size:11px;text-transform:uppercase;color:var(--color-text-tertiary)">Categorie</div><div>${esc(item.categorie)}</div></div>
            <div><div style="font-size:11px;text-transform:uppercase;color:var(--color-text-tertiary)">Afdeling</div><div>${esc(item.afdeling_naam || '—')}</div></div>
            <div><div style="font-size:11px;text-transform:uppercase;color:var(--color-text-tertiary)">Locatie</div><div>${esc(item.locatie || '—')}</div></div>
            <div><div style="font-size:11px;text-transform:uppercase;color:var(--color-text-tertiary)">Geconstateerd</div><div>${esc(String(item.datum_geconstateerd || '').slice(0, 10) || '—')}</div></div>
            <div><div style="font-size:11px;text-transform:uppercase;color:var(--color-text-tertiary)">Gemeld</div><div>${esc(String(item.datum_gemeld || '').slice(0, 10) || '—')}</div></div>
        </div>
        ${item.oplossingsadvies ? `<div style="margin-top:12px"><div style="font-size:11px;text-transform:uppercase;color:var(--color-text-tertiary)">Oplossingsadvies</div><div style="font-size:12.5px;margin-top:2px">${nl2br(item.oplossingsadvies)}</div></div>` : ''}
        <div style="border-top:0.5px solid var(--color-border-tertiary);margin-top:16px;padding-top:16px">
            <div style="display:flex;gap:8px;align-items:center">
                <select id="crStatusSelect" style="flex:1">${statusOptions}</select>
                <button class="btn" type="button" id="crStatusBtn">Status bijwerken</button>
                <a class="btn btn-ghost" href="/cyberrisicos/${item.id}/edit">Bewerken</a>
            </div>
        </div>
    `;

    document.getElementById('crMatrix').innerHTML = matrixHtml(item);

    wireActionMenu(document.getElementById('crDetail'), 'cr-detail', [
        {
            label: `Logboek bekijken (${logs.length})`,
            icon: 'bi-clock-history',
            onClick: () => openLogboekSheet(item.id, logs),
        },
    ]);

    document.getElementById('crStatusBtn').addEventListener('click', async () => {
        try {
            const res = await api.put(`/api/v1/cyberrisicos/${item.id}`, { status: document.getElementById('crStatusSelect').value });
            renderDetail(res.data.item, res.data.logs);
            renderList(currentItems.map((r) => (r.id === item.id ? { ...r, status: res.data.item.status } : r)));
            if (logSheetOpenForId === item.id) openLogboekSheet(item.id, res.data.logs);
        } catch (err) {
            alert(err instanceof ApiError ? err.message : 'Bijwerken mislukt.');
        }
    });
}

async function load() {
    if (!document.getElementById('crListBody')) {
        renderShell();
    }
    document.getElementById('crListBody').innerHTML = '<div class="empty-state">Laden&hellip;</div>';

    try {
        const res = await api.get('/api/v1/cyberrisicos' + (window.location.search || ''));
        renderKpis(res.meta.statusCounts);
        renderList(res.data);
        if (selectedId) {
            loadDetail(selectedId);
        }
    } catch (e) {
        const message = e instanceof ApiError ? e.message : "Kon cyberrisico's niet laden.";
        document.getElementById('crListBody').innerHTML =
            `<div class="empty-state" style="color:var(--color-text-danger)">${esc(message)}</div>`;
    }
}

renderShell();
load();
