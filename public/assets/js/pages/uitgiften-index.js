import { api, ApiError } from '/assets/js/api/client.js';

/**
 * Uitgifte: lijst + item in één split-view scherm, zoals in het Lovable-ontwerp
 * (src/routes/modules.uitgifte.tsx + modules.hardware-uitgaven.tsx zijn feitelijk dezelfde pagina
 * — hardware-uitgaven is Lovable's hardware-only subview van uitgifte, via de Lovable MCP; ons
 * echte `HardwareUitgave`-domeinmodule (inkoop-aanvraagworkflow, aangevraagd→goedgekeurd/afgekeurd→
 * besteld→geleverd) is een heel ander concept met dezelfde naam en heeft GEEN Lovable-mockup, zie
 * CLAUDE.md). Vervangt de eerdere server-rendered index.php/show.php-inhoud.
 *
 * Afwijking t.o.v. de mockup: geen "soort"-tabs (hardware/telefoon/toegangspas/overig) — dat
 * onderscheid bestaat niet in het datamodel (elke uitgifte is een voorraad_item, met een vrij
 * `type_naam`, geen vaste categorie). "Nieuw uitgifte" is hier een echt, werkend inline formulier
 * (barcode + naam, met autocomplete via de bestaande /uitgiften/items en /uitgiften/namen-lookups),
 * niet een dode knop. Retour nemen is een echte toggle tegen POST .../retour (zelfde
 * dual-write-actie als de oude server-rendered flow: zet zowel de uitgifte als het gekoppelde
 * voorraad-item bij).
 */

function esc(value) {
    const div = document.createElement('div');
    div.textContent = value ?? '';
    return div.innerHTML;
}

const root = document.getElementById('uitgiften-app');

function pathId() {
    const m = window.location.pathname.match(/^\/uitgiften\/(\d+)$/);
    return m ? parseInt(m[1], 10) : null;
}

function getParams() {
    return new URLSearchParams(window.location.search);
}

let selectedId = pathId();
let currentItems = [];

function renderShell() {
    root.innerHTML = `
        <div class="page-header"><div class="page-title">Uitgifte</div></div>
        <section class="kpi-grid" id="uiKpis"></section>
        <div class="list-toolbar">
            <div class="search-input">
                <i class="bi bi-search"></i>
                <input type="text" id="uiZoekInput" placeholder="Zoek op medewerker of item&hellip;">
            </div>
            <button class="btn btn-accent" type="button" id="uiNieuweBtn"><i class="bi bi-plus-lg"></i> Nieuwe uitgifte</button>
        </div>
        <div class="card" id="uiCreatePanel" style="display:none;padding:16px;margin-bottom:16px"></div>
        <div class="kb-split" style="display:grid;grid-template-columns:minmax(0,1fr) 340px;gap:16px;align-items:start">
            <div class="card" style="padding:0;overflow:hidden">
                <div id="uiListBody"></div>
            </div>
            <div class="card" id="uiDetail" style="padding:24px"></div>
        </div>
    `;

    document.getElementById('uiZoekInput').value = getParams().get('q') || '';
    document.getElementById('uiZoekInput').addEventListener('input', debounce(() => {
        const params = getParams();
        const q = document.getElementById('uiZoekInput').value.trim();
        if (q) { params.set('q', q); } else { params.delete('q'); }
        window.history.replaceState({}, '', '/uitgiften' + (params.toString() ? `?${params}` : ''));
        load();
    }, 300));

    document.getElementById('uiNieuweBtn').addEventListener('click', toggleCreatePanel);
}

function debounce(fn, ms) {
    let t;
    return (...args) => { clearTimeout(t); t = setTimeout(() => fn(...args), ms); };
}

function toggleCreatePanel() {
    const panel = document.getElementById('uiCreatePanel');
    if (panel.style.display !== 'none') {
        panel.style.display = 'none';
        return;
    }

    panel.style.display = 'block';
    panel.innerHTML = `
        <form id="uiCreateForm" style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
            <div>
                <label class="form-label" style="font-size:11px">Barcode</label>
                <input type="text" name="barcode" list="uiBarcodeOptions" autocomplete="off" required>
                <datalist id="uiBarcodeOptions"></datalist>
            </div>
            <div>
                <label class="form-label" style="font-size:11px">Naam medewerker</label>
                <input type="text" name="medewerker_naam" list="uiNaamOptions" autocomplete="off" required>
                <datalist id="uiNaamOptions"></datalist>
            </div>
            <div style="grid-column:span 2">
                <label class="form-label" style="font-size:11px">Opmerking</label>
                <input type="text" name="opmerking">
            </div>
            <div style="grid-column:span 2;display:flex;justify-content:flex-end">
                <button class="btn btn-accent" type="submit">Uitgeven</button>
            </div>
        </form>
    `;

    const barcodeInput = panel.querySelector('[name="barcode"]');
    barcodeInput.addEventListener('input', debounce(async () => {
        if (barcodeInput.value.trim().length < 2) return;
        const items = await api.get(`/uitgiften/items?q=${encodeURIComponent(barcodeInput.value.trim())}`).catch(() => []);
        document.getElementById('uiBarcodeOptions').innerHTML = (Array.isArray(items) ? items : []).map((i) => `<option value="${esc(i.barcode)}">${esc(i.label)}</option>`).join('');
    }, 250));

    const naamInput = panel.querySelector('[name="medewerker_naam"]');
    naamInput.addEventListener('input', debounce(async () => {
        if (naamInput.value.trim().length < 2) return;
        const namen = await api.get(`/uitgiften/namen?q=${encodeURIComponent(naamInput.value.trim())}`).catch(() => []);
        document.getElementById('uiNaamOptions').innerHTML = (Array.isArray(namen) ? namen : []).map((n) => `<option value="${esc(n)}"></option>`).join('');
    }, 250));

    document.getElementById('uiCreateForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const form = e.target;
        try {
            const res = await api.post('/api/v1/uitgiften', {
                barcode: form.barcode.value.trim(),
                medewerker_naam: form.medewerker_naam.value.trim(),
                opmerking: form.opmerking.value.trim(),
            });
            panel.style.display = 'none';
            selectItem(res.data.item.id);
            load();
        } catch (err) {
            alert(err instanceof ApiError ? err.message : 'Uitgifte mislukt.');
        }
    });
}

function statusBadge(status) {
    return status === 'geretourneerd'
        ? '<span class="badge badge-opgelost"><i class="bi bi-arrow-return-left"></i> Retour</span>'
        : '<span class="badge badge-in_behandeling">In gebruik</span>';
}

function renderKpis(kpis) {
    document.getElementById('uiKpis').innerHTML = `
        <div class="kpi-card">
            <div class="kpi-card-head"><span class="kpi-label">Totaal uitgiften</span><span class="kpi-icon kpi-icon-open"><i class="bi bi-box-arrow-up"></i></span></div>
            <div class="kpi-value">${kpis.aantal}</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-card-head"><span class="kpi-label">Openstaand</span><span class="kpi-icon kpi-icon-behandeling"><i class="bi bi-hourglass-split"></i></span></div>
            <div class="kpi-value">${kpis.openstaand}</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-card-head"><span class="kpi-label">Geretourneerd</span><span class="kpi-icon kpi-icon-opgelost"><i class="bi bi-arrow-return-left"></i></span></div>
            <div class="kpi-value">${kpis.geretourneerd}</div>
        </div>
    `;
}

function renderList(items) {
    currentItems = items;
    const body = document.getElementById('uiListBody');

    if (items.length === 0) {
        body.innerHTML = '<div class="empty-state">Geen uitgiften gevonden.</div>';
        return;
    }

    if (selectedId === null || !items.some((u) => u.id === selectedId)) {
        selectedId = items[0].id;
    }

    body.innerHTML = `
        <div class="table-wrap"><table>
            <thead><tr><th>#</th><th>Medewerker</th><th>Item</th><th>Datum</th><th>Status</th></tr></thead>
            <tbody>${items.map((u) => `
                <tr data-id="${u.id}" style="cursor:pointer${u.id === selectedId ? ';background:var(--color-background-secondary)' : ''}">
                    <td class="mono" style="font-size:11px;color:var(--color-text-tertiary)">#${u.id}</td>
                    <td style="font-size:12px;font-weight:500">${esc(u.medewerker_naam)}</td>
                    <td style="font-size:12px">${esc(u.type_naam || '—')}${u.variant ? ' (' + esc(u.variant) + ')' : ''}</td>
                    <td style="font-size:12px;color:var(--color-text-tertiary)">${esc(String(u.uitgegeven_op || '').slice(0, 10))}</td>
                    <td>${statusBadge(u.status)}</td>
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
    window.history.pushState({}, '', `/uitgiften/${id}`);
    renderList(currentItems);
    loadDetail(id);
}

window.addEventListener('popstate', () => {
    selectedId = pathId();
    renderList(currentItems);
    if (selectedId) loadDetail(selectedId);
});

async function loadDetail(id) {
    const detail = document.getElementById('uiDetail');
    detail.innerHTML = '<div class="empty-state">Laden&hellip;</div>';

    try {
        const res = await api.get(`/api/v1/uitgiften/${id}`);
        renderDetail(res.data.item);
    } catch (e) {
        const message = e instanceof ApiError ? e.message : 'Kon uitgifte niet laden.';
        detail.innerHTML = `<div class="empty-state" style="color:var(--color-text-danger)">${esc(message)}</div>`;
    }
}

function renderDetail(item) {
    document.getElementById('uiDetail').innerHTML = `
        <div style="margin-bottom:12px">
            <div class="mono" style="font-size:11px;color:var(--color-text-tertiary)">#${item.id} &middot; ${esc(String(item.uitgegeven_op || '').slice(0, 10))}</div>
            <div style="font-size:16px;font-weight:600;margin-top:2px">${esc(item.type_naam || '—')}${item.variant ? ' (' + esc(item.variant) + ')' : ''}</div>
            <div class="mono" style="font-size:11px;color:var(--color-text-tertiary);margin-top:2px">${esc(item.barcode || '—')}</div>
            <div style="margin-top:8px">${statusBadge(item.status)}</div>
        </div>
        <div style="font-size:13px;border-top:0.5px solid var(--color-border-tertiary);padding-top:12px">
            <div style="font-size:11px;text-transform:uppercase;color:var(--color-text-tertiary)">Uitgegeven aan</div>
            <div style="font-weight:500">${esc(item.medewerker_naam)}</div>
        </div>
        ${item.opmerking ? `<div style="margin-top:10px;font-size:12.5px;color:var(--color-text-secondary)">${esc(item.opmerking)}</div>` : ''}
        ${item.status === 'geretourneerd'
            ? `<div style="margin-top:16px;font-size:12px;color:var(--color-text-tertiary)">Retour genomen op ${esc(String(item.teruggegeven_op || '').slice(0, 10))}</div>`
            : `<form id="uiRetourForm" style="border-top:0.5px solid var(--color-border-tertiary);margin-top:16px;padding-top:16px;display:flex;flex-direction:column;gap:8px">
                <select name="resultaat">
                    <option value="op_voorraad">Terug op voorraad</option>
                    <option value="afgeschreven">Afgeschreven</option>
                </select>
                <input type="text" name="opmerking" placeholder="Opmerking (optioneel)">
                <button class="btn btn-accent" type="submit" style="align-self:flex-end">Retour nemen</button>
               </form>`}
    `;

    const retourForm = document.getElementById('uiRetourForm');
    if (retourForm) {
        retourForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const form = e.target;
            try {
                const res = await api.post(`/api/v1/uitgiften/${item.id}/retour`, {
                    resultaat: form.resultaat.value,
                    opmerking: form.opmerking.value.trim(),
                });
                renderDetail(res.data.item);
                load();
            } catch (err) {
                alert(err instanceof ApiError ? err.message : 'Retour nemen mislukt.');
            }
        });
    }
}

async function load() {
    if (!document.getElementById('uiListBody')) {
        renderShell();
    }
    document.getElementById('uiListBody').innerHTML = '<div class="empty-state">Laden&hellip;</div>';

    try {
        const res = await api.get('/api/v1/uitgiften' + (window.location.search || ''));
        renderKpis(res.meta.kpis);
        renderList(res.data);
        if (selectedId) {
            loadDetail(selectedId);
        }
    } catch (e) {
        const message = e instanceof ApiError ? e.message : 'Kon uitgiften niet laden.';
        document.getElementById('uiListBody').innerHTML =
            `<div class="empty-state" style="color:var(--color-text-danger)">${esc(message)}</div>`;
    }
}

renderShell();
load();
