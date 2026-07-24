import { api, ApiError } from '/assets/js/api/client.js';

/**
 * Reflectie: lijst + inline bewerkbaar detail in één split-view scherm, o.b.v.
 * src/routes/modules.reflectie.tsx (opgehaald via de Lovable MCP read_file-tool tegen project
 * 4675b36f-276e-4fc5-9606-d83a98f9d801, niet de gecommitte kopie onder docs/design/).
 *
 * Bewuste afwijkingen t.o.v. de mockup:
 * - "Periode"-veld (bestaat in de reflecties-tabel, niet in de mockdata) toegevoegd als klein
 *   invoerveld naast de titel, zodat die data niet verdwijnt bij de conversie.
 * - Een "Opmerkingen"-logboek onderaan het detailpaneel: bestond al in de oude server-rendered
 *   show.php en komt niet in de Lovable-mockup voor, maar blijft behouden (geen functionaliteit
 *   laten vervallen bij een zuiver visuele conversie).
 */

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

const root = document.getElementById('reflectie-app');

function pathId() {
    const m = window.location.pathname.match(/^\/reflecties\/(\d+)$/);
    return m ? parseInt(m[1], 10) : null;
}

function getParams() {
    return new URLSearchParams(window.location.search);
}

function apiQuery() {
    const qs = getParams().toString();
    return '/api/v1/reflecties' + (qs ? `?${qs}` : '');
}

let selectedId = pathId();
let currentItems = [];
let detailState = null;

function renderShell() {
    root.innerHTML = `
        <div class="page-header">
            <div class="page-title">Reflectie</div>
            <a class="btn btn-accent" href="/reflecties/create"><i class="bi bi-plus-lg"></i> Nieuwe reflectie</a>
        </div>
        <div class="card" style="padding:16px;margin-bottom:16px;display:flex;align-items:center;gap:12px;background:var(--color-background-secondary)">
            <div style="width:38px;height:38px;border-radius:8px;background:var(--color-primary);color:#fff;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                <i class="bi bi-chat-square-quote"></i>
            </div>
            <div>
                <div style="font-size:13px;font-weight:600">Wat viel op deze week?</div>
                <div style="font-size:12px;color:var(--color-text-tertiary);margin-top:2px">
                    Reflectie is een lichte notitiemodule: leg kort patronen vast die in Ticket of Verbeterpunt opgepakt kunnen worden.
                </div>
            </div>
        </div>
        <div style="display:grid;grid-template-columns:minmax(0,360px) 1fr;gap:16px;align-items:start">
            <div class="card" style="padding:0;overflow:hidden">
                <div id="refListBody"></div>
            </div>
            <div class="card" id="refDetail" style="padding:24px"></div>
        </div>
    `;
}

function renderList(items) {
    currentItems = items;
    const body = document.getElementById('refListBody');

    if (items.length === 0) {
        body.innerHTML = '<div class="empty-state">Geen reflecties gevonden.</div>';
        return;
    }

    if (selectedId === null || !items.some((r) => r.id === selectedId)) {
        selectedId = items[0].id;
    }

    body.innerHTML = `<div class="list-group list-group-flush">${items.map((r) => `
        <div class="list-group-item" data-id="${r.id}" style="cursor:pointer${r.id === selectedId ? ';background:var(--color-background-secondary)' : ''}">
            <div style="display:flex;align-items:center;justify-content:space-between;gap:8px">
                <span class="text-truncate" style="font-weight:500;font-size:13.5px">${esc(r.titel)}</span>
                <span class="mono" style="font-size:10px;color:var(--color-text-tertiary);flex-shrink:0">${esc(String(r.created_at || '').slice(0, 10))}</span>
            </div>
            <div style="font-size:11px;color:var(--color-text-tertiary);margin-top:2px">${esc(r.gebruiker_naam || '—')}</div>
        </div>
    `).join('')}</div>`;

    body.querySelectorAll('[data-id]').forEach((el) => {
        el.addEventListener('click', () => selectItem(parseInt(el.dataset.id, 10)));
    });
}

function selectItem(id) {
    selectedId = id;
    window.history.pushState({}, '', `/reflecties/${id}`);
    renderList(currentItems);
    loadDetail(id);
}

window.addEventListener('popstate', () => {
    selectedId = pathId();
    renderList(currentItems);
    if (selectedId) loadDetail(selectedId);
});

function flash(message, type = 'error') {
    const detail = document.getElementById('refDetail');
    const el = document.createElement('div');
    el.className = `alert alert-${type === 'error' ? 'error' : 'success'}`;
    el.textContent = message;
    detail.prepend(el);
    window.setTimeout(() => el.remove(), 4000);
}

async function loadDetail(id) {
    const detail = document.getElementById('refDetail');
    detail.innerHTML = '<div class="empty-state">Laden&hellip;</div>';

    try {
        const res = await api.get(`/api/v1/reflecties/${id}`);
        detailState = res.data;
        renderDetail();
    } catch (e) {
        const message = e instanceof ApiError ? e.message : 'Kon reflectie niet laden.';
        detail.innerHTML = `<div class="empty-state" style="color:var(--color-text-danger)">${esc(message)}</div>`;
    }
}

function renderDetail() {
    const { item, logs } = detailState;

    document.getElementById('refDetail').innerHTML = `
        <div style="font-size:11px;color:var(--color-text-tertiary);margin-bottom:6px">
            ${esc(String(item.created_at || '').slice(0, 10))} &middot; ${esc(item.gebruiker_naam || '—')}
        </div>
        <input type="text" id="refTitelInput" value="${esc(item.titel)}"
            style="font-size:20px;font-weight:600;border:0;padding:2px 0;width:100%;background:transparent">
        <div style="margin-top:8px">
            <label class="form-label">Periode</label>
            <input type="text" id="refPeriodeInput" value="${esc(item.periode || '')}" placeholder="Bijv. Week 30, Q3 2026" style="max-width:240px">
        </div>
        <textarea id="refInhoudInput" rows="8" style="margin-top:12px;font-size:13px;line-height:1.7">${esc(item.inhoud)}</textarea>
        <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:12px">
            <button class="btn" type="button" id="refAnnuleerBtn">Annuleer</button>
            <button class="btn btn-accent" type="button" id="refOpslaanBtn">Opslaan</button>
        </div>

        <div style="border-top:0.5px solid var(--color-border-tertiary);margin-top:20px;padding-top:16px">
            <h3 class="detail-side-heading">Opmerkingen</h3>
            <div style="margin-bottom:10px">
                <input type="text" id="refLogTitel" placeholder="Korte titel voor deze opmerking" style="margin-bottom:6px">
                <textarea id="refLogTekst" placeholder="Opmerking&hellip;" style="min-height:60px"></textarea>
                <button class="btn" type="button" id="refLogOpslaanBtn" style="margin-top:6px">Opslaan</button>
            </div>
            ${logs.length === 0 ? '<div class="empty-state">Nog geen opmerkingen.</div>' : logs.map((l) => `
                <div class="log-item">
                    <div class="log-meta"><span class="log-user">${esc(l.user_naam || 'Onbekend')}</span><span class="log-time">${formatDatumTijd(l.created_at)}</span></div>
                    <div class="log-title" style="font-weight:600;margin-bottom:2px">${esc(l.titel)}</div>
                    <div class="log-text">${nl2br(l.omschrijving)}</div>
                </div>
            `).join('')}
        </div>
    `;

    wireDetailEvents();
}

function wireDetailEvents() {
    document.getElementById('refAnnuleerBtn').addEventListener('click', renderDetail);

    document.getElementById('refOpslaanBtn').addEventListener('click', async () => {
        const titel = document.getElementById('refTitelInput').value.trim();
        const periode = document.getElementById('refPeriodeInput').value.trim();
        const inhoud = document.getElementById('refInhoudInput').value.trim();

        try {
            const res = await api.put(`/api/v1/reflecties/${detailState.item.id}`, { titel, periode, inhoud });
            detailState = res.data;
            renderDetail();
            renderList(currentItems.map((r) => (r.id === detailState.item.id ? { ...r, titel: detailState.item.titel, periode: detailState.item.periode } : r)));
        } catch (e) {
            flash(e instanceof ApiError ? e.message : 'Opslaan is mislukt.');
        }
    });

    document.getElementById('refLogOpslaanBtn').addEventListener('click', async () => {
        const titel = document.getElementById('refLogTitel').value.trim();
        const omschrijving = document.getElementById('refLogTekst').value.trim();
        if (titel === '' || omschrijving === '') {
            flash('Vul zowel een titel als een omschrijving in.');
            return;
        }
        try {
            const res = await api.post(`/api/v1/reflecties/${detailState.item.id}/log`, { titel, omschrijving });
            detailState = res.data;
            renderDetail();
        } catch (e) {
            flash(e instanceof ApiError ? e.message : 'Opslaan is mislukt.');
        }
    });
}

async function load() {
    if (!document.getElementById('refListBody')) {
        renderShell();
    }
    document.getElementById('refListBody').innerHTML = '<div class="empty-state">Laden&hellip;</div>';

    try {
        const res = await api.get(apiQuery());
        renderList(res.data);
        if (selectedId) {
            loadDetail(selectedId);
        }
    } catch (e) {
        const message = e instanceof ApiError ? e.message : 'Kon reflecties niet laden.';
        document.getElementById('refListBody').innerHTML =
            `<div class="empty-state" style="color:var(--color-text-danger)">${esc(message)}</div>`;
    }
}

renderShell();
load();
