import { api, ApiError } from '/assets/js/api/client.js';

/**
 * Reflectie: lijst + item in één split-view scherm met inline bewerken, zoals in het Lovable-ontwerp
 * (src/routes/modules.reflectie.tsx, via de Lovable MCP). Vervangt de eerdere server-rendered
 * index.php/show.php-inhoud.
 *
 * Bewuste afwijking t.o.v. de mockup: het bestaande opmerkingen-logboek (reflectie_logs,
 * ReflectieLogController) komt niet voor in dit scherm — Lovable toont hier alleen titel+inhoud
 * bewerken, geen los commentaarveld. De oude /reflecties/{id}/log-route blijft bestaan maar wordt
 * door dit scherm niet meer aangeroepen.
 */

function esc(value) {
    const div = document.createElement('div');
    div.textContent = value ?? '';
    return div.innerHTML;
}

const root = document.getElementById('reflecties-app');

function pathId() {
    const m = window.location.pathname.match(/^\/reflecties\/(\d+)$/);
    return m ? parseInt(m[1], 10) : null;
}

let selectedId = pathId();
let currentItems = [];

function renderShell() {
    root.innerHTML = `
        <div class="page-header">
            <div class="page-title">Reflectie</div>
            <a class="btn btn-accent" href="/reflecties/create"><i class="bi bi-plus-lg"></i> Nieuwe reflectie</a>
        </div>
        <div class="kb-split" style="display:grid;grid-template-columns:minmax(0,360px) 1fr;gap:16px;align-items:start">
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

    body.innerHTML = `<div class="divide-list">${items.map((r) => `
        <div data-id="${r.id}" style="cursor:pointer;padding:12px 16px;border-bottom:0.5px solid var(--color-border-tertiary)${r.id === selectedId ? ';background:var(--color-background-secondary)' : ''}">
            <div style="display:flex;align-items:center;justify-content:space-between;gap:8px">
                <span class="text-truncate" style="font-weight:500">${esc(r.titel)}</span>
                <span class="mono" style="font-size:11px;color:var(--color-text-tertiary);flex-shrink:0">${esc(String(r.created_at || '').slice(0, 10))}</span>
            </div>
            <div style="font-size:11px;color:var(--color-text-tertiary);margin-top:2px">${esc(r.gebruiker_naam || '—')}${r.periode ? ' &middot; ' + esc(r.periode) : ''}</div>
        </div>
    `).join('')}</div>`;

    body.querySelectorAll('[data-id]').forEach((row) => {
        row.addEventListener('click', () => selectItem(parseInt(row.dataset.id, 10)));
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

async function loadDetail(id) {
    const detail = document.getElementById('refDetail');
    detail.innerHTML = '<div class="empty-state">Laden&hellip;</div>';

    try {
        const res = await api.get(`/api/v1/reflecties/${id}`);
        renderDetail(res.data.item);
    } catch (e) {
        const message = e instanceof ApiError ? e.message : 'Kon reflectie niet laden.';
        detail.innerHTML = `<div class="empty-state" style="color:var(--color-text-danger)">${esc(message)}</div>`;
    }
}

function renderDetail(item) {
    document.getElementById('refDetail').innerHTML = `
        <div style="font-size:11px;color:var(--color-text-tertiary)">
            ${esc(String(item.created_at || '').slice(0, 10))} &middot; ${esc(item.gebruiker_naam || '—')}
        </div>
        <form id="refForm">
            <input type="text" name="titel" value="${esc(item.titel)}" style="width:100%;font-size:20px;font-weight:600;border:0;padding:8px 0;margin-top:4px;background:transparent">
            <input type="text" name="periode" value="${esc(item.periode || '')}" placeholder="Periode (bv. Week 30)" style="width:100%;font-size:12px;color:var(--color-text-secondary);border:0;padding:2px 0;background:transparent">
            <textarea name="inhoud" rows="8" style="width:100%;margin-top:12px;font-size:13px;line-height:1.6">${esc(item.inhoud)}</textarea>
            <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:12px">
                <button type="button" class="btn btn-ghost" id="refCancelBtn">Annuleer</button>
                <button type="submit" class="btn btn-accent">Opslaan</button>
            </div>
        </form>
    `;

    document.getElementById('refCancelBtn').addEventListener('click', () => loadDetail(item.id));

    document.getElementById('refForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const form = e.target;
        const body = {
            titel: form.titel.value.trim(),
            periode: form.periode.value.trim(),
            inhoud: form.inhoud.value.trim(),
        };
        try {
            const res = await api.put(`/api/v1/reflecties/${item.id}`, body);
            renderDetail(res.data.item);
            renderList(currentItems.map((r) => (r.id === item.id ? { ...r, ...res.data.item } : r)));
        } catch (err) {
            const message = err instanceof ApiError ? err.message : 'Opslaan mislukt.';
            alert(message);
        }
    });
}

async function load() {
    if (!document.getElementById('refListBody')) {
        renderShell();
    }
    document.getElementById('refListBody').innerHTML = '<div class="empty-state">Laden&hellip;</div>';

    try {
        const res = await api.get('/api/v1/reflecties' + (window.location.search || ''));
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
