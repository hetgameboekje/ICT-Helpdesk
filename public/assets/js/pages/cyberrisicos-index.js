import { api, ApiError } from '/assets/js/api/client.js';

/**
 * Cyberrisico's: lijst + item in één split-view scherm, zoals in het Lovable-ontwerp
 * (src/routes/modules.cyberrisico.tsx, via de Lovable MCP). Vervangt de eerdere server-rendered
 * index.php/show.php-inhoud.
 *
 * Afwijking t.o.v. de mockup: Lovable gaat uit van een kans(1-5) × impact(1-5)-risicomatrix met een
 * daarvan afgeleid niveau — die twee kolommen bestaan niet in ons datamodel. In plaats daarvan
 * hebben we een direct `prioriteit`-veld (laag/middel/hoog/kritiek) en een los `status`-veld
 * (nieuw/in_onderzoek/bevestigd/opgelost/geaccepteerd); de risicomatrix-widget is vervangen door
 * een statusbadge + een echt logboek (cyberrisico_logs, zelfde patroon als Tickets), en de
 * prioriteits-KPI's/badges gebruiken de bestaande --color-risk-*/badge-risico-*-tokens ("middel"
 * hergebruikt de "gemiddeld"-CSS-klasse, enige naam die niet 1-op-1 overeenkomt).
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
            <div class="card" id="crDetail" style="padding:24px"></div>
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
            <thead><tr><th>#</th><th>Risico</th><th>Eigenaar</th><th>Prioriteit</th><th>Status</th></tr></thead>
            <tbody>${items.map((r) => `
                <tr data-id="${r.id}" style="cursor:pointer${r.id === selectedId ? ';background:var(--color-background-secondary)' : ''}">
                    <td class="mono" style="font-size:11px;color:var(--color-text-tertiary)">#${r.id}</td>
                    <td>
                        <div class="text-truncate" style="font-weight:500">${esc(r.titel)}</div>
                        <div style="font-size:11px;color:var(--color-text-tertiary)">${esc(r.categorie)}</div>
                    </td>
                    <td style="font-size:12px">${esc(r.eigenaar_naam || '—')}</td>
                    <td>${prioBadge(r.prioriteit)}</td>
                    <td>${statusBadge(r.status)}</td>
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

    try {
        const res = await api.get(`/api/v1/cyberrisicos/${id}`);
        renderDetail(res.data.item, res.data.logs);
    } catch (e) {
        const message = e instanceof ApiError ? e.message : 'Kon risico niet laden.';
        detail.innerHTML = `<div class="empty-state" style="color:var(--color-text-danger)">${esc(message)}</div>`;
    }
}

function renderDetail(item, logs) {
    const statusOptions = Object.entries(STATUS_LABELS)
        .map(([val, label]) => `<option value="${val}"${item.status === val ? ' selected' : ''}>${esc(label)}</option>`)
        .join('');

    document.getElementById('crDetail').innerHTML = `
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px">
            <span class="mono" style="font-size:11px;color:var(--color-text-tertiary)">#${item.id}</span>
            ${prioBadge(item.prioriteit)}${item.is_gevoelig == 1 ? ' <span class="badge badge-risico-kritiek"><i class="bi bi-eye-slash"></i> Gevoelig</span>' : ''}
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
            <div style="display:flex;gap:8px;align-items:center;margin-bottom:12px">
                <select id="crStatusSelect" style="flex:1">${statusOptions}</select>
                <button class="btn" type="button" id="crStatusBtn">Status bijwerken</button>
                <a class="btn btn-ghost" href="/cyberrisicos/${item.id}/edit">Bewerken</a>
            </div>
            <h3 style="font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.04em;color:var(--color-text-tertiary);margin:0 0 8px">Logboek</h3>
            <div style="display:flex;flex-direction:column;gap:8px;margin-bottom:12px">
                ${logs.length === 0 ? '<p style="font-size:12px;color:var(--color-text-tertiary)">Nog geen logregels.</p>' : logs.map((l) => `
                    <div style="font-size:12.5px">
                        <span class="mono" style="font-size:10px;color:var(--color-text-tertiary)">${esc(String(l.created_at || '').slice(0, 16).replace('T', ' '))}</span>
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
        </div>
    `;

    document.getElementById('crStatusBtn').addEventListener('click', async () => {
        try {
            const res = await api.put(`/api/v1/cyberrisicos/${item.id}`, { status: document.getElementById('crStatusSelect').value });
            renderDetail(res.data.item, res.data.logs);
            renderList(currentItems.map((r) => (r.id === item.id ? { ...r, status: res.data.item.status } : r)));
        } catch (err) {
            alert(err instanceof ApiError ? err.message : 'Bijwerken mislukt.');
        }
    });

    document.getElementById('crLogForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const form = e.target;
        try {
            const res = await api.post(`/api/v1/cyberrisicos/${item.id}/log`, {
                titel: form.titel.value.trim(),
                omschrijving: form.omschrijving.value.trim(),
            });
            renderDetail(res.data.item, res.data.logs);
        } catch (err) {
            alert(err instanceof ApiError ? err.message : 'Opslaan mislukt.');
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
