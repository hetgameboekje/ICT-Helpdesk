import { api, ApiError } from '/assets/js/api/client.js';

/**
 * Tickets-lijst, volledig client-side gerenderd via /api/v1/tickets. Vervangt de eerdere
 * server-rendered app/Modules/Ticket/Views/TicketView/index.php-inhoud (zie CLAUDE.md >
 * API-architectuur). Export/Import (Excel) blijven bewust op de oude server-rendered routes
 * (/tickets/export, /tickets/import) — bestandsdownload/-upload hoort niet in deze JSON-API-scope.
 */

const STATUS_LABELS = {
    open: 'Open', in_behandeling: 'In behandeling', wacht_op_info: 'Wacht op info', afgehandeld: 'Afgehandeld',
};
const STATUS_TABS = [
    { key: '', label: 'Actief' },
    { key: 'alle', label: 'Alle' },
    { key: 'open', label: 'Open' },
    { key: 'in_behandeling', label: 'In behandeling' },
    { key: 'wacht_op_info', label: 'Wacht op info' },
    { key: 'afgehandeld', label: 'Afgehandeld' },
];

function esc(value) {
    const div = document.createElement('div');
    div.textContent = value ?? '';
    return div.innerHTML;
}

function formatDatum(value) {
    if (!value) return '—';
    const [y, m, d] = String(value).slice(0, 10).split('-');
    return `${d}-${m}-${y}`;
}

function initialen(naam) {
    return naam.trim().split(/\s+/).slice(0, 2).map((p) => p[0].toUpperCase()).join('') || '?';
}

function statusBadge(status) {
    const label = STATUS_LABELS[status] || status;
    return `<span class="badge badge-${esc(status)}">${esc(label)}</span>`;
}

function prioBadge(prio) {
    const labels = { laag: 'Laag', normaal: 'Normaal', hoog: 'Hoog', kritiek: 'Kritiek' };
    return `<span class="prio prio-${esc(prio)}"><span class="prio-dot"></span>${esc(labels[prio] || prio)}</span>`;
}

function getParams() {
    return new URLSearchParams(window.location.search);
}

function buildUrl(overrides) {
    const params = getParams();
    Object.entries(overrides).forEach(([key, value]) => {
        if (value === null || value === '') {
            params.delete(key);
        } else {
            params.set(key, value);
        }
    });
    const qs = params.toString();
    return '/tickets' + (qs ? `?${qs}` : '');
}

function apiQuery() {
    return '/api/v1/tickets' + (window.location.search || '');
}

const root = document.getElementById('tickets-app');

function renderShell() {
    root.innerHTML = `
        <div class="page-header"><div class="page-title">Tickets</div></div>
        <div id="tickets-flash"></div>
        <form class="list-toolbar" id="ticketZoekForm">
            <div class="search-input">
                <i class="bi bi-search"></i>
                <input type="text" name="q" id="ticketZoekInput" placeholder="Zoek op nummer, taak of opdrachtgever&hellip;">
            </div>
            <button type="button" class="btn btn-ghost" id="filtersToggleBtn"><i class="bi bi-sliders"></i> Filters</button>
            <a class="btn btn-ghost" href="/tickets/export"><i class="bi bi-download"></i> Export</a>
            <button type="button" class="btn btn-ghost" id="import-trigger-btn"><i class="bi bi-upload"></i> Importeren</button>
            <a class="btn btn-accent" href="/tickets/create"><i class="bi bi-plus-lg"></i> Nieuw</a>
        </form>
        <form method="post" action="/tickets/import" enctype="multipart/form-data" id="import-form">
            <input type="file" name="bestand" accept=".xlsx" id="import-file-input" style="display:none" required>
        </form>
        <div class="status-tabs" id="statusTabs"></div>
        <div class="filters-panel" id="filtersPanel">
            <div class="filters" id="filtersRow"></div>
        </div>
        <div id="ticketsActiveChip"></div>
        <div class="card"><div id="ticketsCardBody"></div></div>
    `;

    document.getElementById('ticketZoekInput').value = getParams().get('q') || '';

    document.getElementById('ticketZoekForm').addEventListener('submit', (e) => {
        e.preventDefault();
        const q = document.getElementById('ticketZoekInput').value.trim();
        navigate(buildUrl({ q, page: null }));
    });

    document.getElementById('import-trigger-btn').addEventListener('click', () => {
        document.getElementById('import-file-input').click();
    });
    document.getElementById('import-file-input').addEventListener('change', (e) => {
        if (e.target.files && e.target.files.length > 0) {
            document.getElementById('import-form').submit();
        }
    });

    document.getElementById('filtersToggleBtn').addEventListener('click', () => {
        document.getElementById('filtersPanel').classList.toggle('open');
    });
}

function navigate(url) {
    window.history.pushState({}, '', url);
    load();
}

window.addEventListener('popstate', load);

function renderStatusTabs(statusCounts) {
    const current = getParams().get('status') || '';
    const wrap = document.getElementById('statusTabs');
    wrap.innerHTML = STATUS_TABS.map((tab) => {
        const countKey = tab.key === '' ? 'actief' : tab.key;
        const count = statusCounts[countKey] ?? 0;
        const active = current === tab.key ? ' active' : '';
        return `<a class="status-tab${active}" href="${esc(buildUrl({ status: tab.key || null, page: null }))}" data-status="${esc(tab.key)}">${esc(tab.label)} <span class="status-tab-count">${count}</span></a>`;
    }).join('');
    wrap.querySelectorAll('.status-tab').forEach((el) => {
        el.addEventListener('click', (e) => {
            e.preventDefault();
            navigate(buildUrl({ status: el.dataset.status || null, page: null }));
        });
    });
}

function renderFilters(filterOptions) {
    const params = getParams();
    const selectHtml = (name, allLabel, options) => {
        const current = params.get(name) || '';
        const opts = Object.entries(options).map(([val, label]) =>
            `<option value="${esc(val)}"${current === val ? ' selected' : ''}>${esc(label)}</option>`).join('');
        return `<select name="${esc(name)}"><option value="">${esc(allLabel)}</option>${opts}</select>`;
    };

    document.getElementById('filtersRow').innerHTML = `
        ${selectHtml('prioriteit', 'Alle prioriteiten', filterOptions.prioriteit)}
        ${selectHtml('afdeling_naam', 'Alle afdelingen', filterOptions.afdeling_naam)}
        ${selectHtml('behandelaar_naam', 'Alle behandelaars', filterOptions.behandelaar_naam)}
        <button class="btn" type="button" id="filtersApplyBtn">Toepassen</button>
    `;

    const anyActive = ['prioriteit', 'afdeling_naam', 'behandelaar_naam'].some((k) => params.get(k));
    if (anyActive) {
        document.getElementById('filtersPanel').classList.add('open');
    }

    document.getElementById('filtersApplyBtn').addEventListener('click', () => {
        const row = document.getElementById('filtersRow');
        const overrides = { page: null };
        row.querySelectorAll('select').forEach((sel) => { overrides[sel.name] = sel.value || null; });
        navigate(buildUrl(overrides));
    });
}

function renderActiveChip() {
    const params = getParams();
    const filters = [...params.entries()].filter(([k]) => !['sort', 'dir', 'q', 'page', 'status'].includes(k) && params.get(k) !== '');
    const el = document.getElementById('ticketsActiveChip');
    if (filters.length === 0) {
        el.innerHTML = '';
        return;
    }
    const label = filters.map(([k, v]) => `${k.charAt(0).toUpperCase()}${k.slice(1)}: ${v}`).join(', ');
    el.innerHTML = `<a class="filter-chip" href="/tickets">${esc(label)} &times;</a>`;
}

function renderTable(items, pagination) {
    const body = document.getElementById('ticketsCardBody');

    if (items.length === 0) {
        body.innerHTML = '<div class="empty-state">Geen tickets gevonden.</div>';
        return;
    }

    const rows = items.map((t) => {
        const behandelaar = t.behandelaar_naam
            ? `<span style="display:flex;align-items:center;gap:6px"><span class="avatar-xs">${esc(initialen(t.behandelaar_naam))}</span>${esc(t.behandelaar_naam.split(' ')[0])}</span>`
            : '<span style="color:var(--color-text-tertiary)">—</span>';
        return `
            <tr data-id="${t.id}" style="cursor:pointer">
                <td class="col-1"><span class="mono" style="color:var(--color-text-tertiary)">#${t.id}</span></td>
                <td>
                    <span class="text-truncate d-block" title="${esc(t.titel)}">${esc(t.titel)}</span>
                    <span class="text-truncate d-block" style="font-size:11px;color:var(--color-text-tertiary)">${esc(t.opdrachtgever_naam)}</span>
                </td>
                <td class="col-2"><span class="text-truncate d-block" title="${esc(t.afdeling_naam || '')}">${esc(t.afdeling_naam || '—')}</span></td>
                <td class="col-2">${behandelaar}</td>
                <td class="col-1">${prioBadge(t.prioriteit)}</td>
                <td class="col-2">${statusBadge(t.status)}</td>
                <td class="col-2"><span style="color:var(--color-text-tertiary)">${formatDatum(t.deadline)}</span></td>
            </tr>`;
    }).join('');

    body.innerHTML = `
        <div class="table-wrap">
            <table>
                <thead><tr>
                    <th class="col-1">#</th><th>Taak</th><th class="col-2">Afdeling</th>
                    <th class="col-2">Behandelaar</th><th class="col-1">Prio</th><th class="col-2">Status</th><th class="col-2">Deadline</th>
                </tr></thead>
                <tbody>${rows}</tbody>
            </table>
        </div>
        <div class="list-footer">
            <span>${items.length} van ${pagination.total} tickets</span>
            <div style="display:flex;gap:4px">
                ${pagination.page > 1
                    ? `<a class="btn btn-ghost" href="${esc(buildUrl({ page: pagination.page - 1 }))}" data-page="${pagination.page - 1}">Vorige</a>`
                    : '<span class="btn btn-ghost" style="opacity:.4;pointer-events:none">Vorige</span>'}
                ${pagination.page < pagination.totalPages
                    ? `<a class="btn btn-ghost" href="${esc(buildUrl({ page: pagination.page + 1 }))}" data-page="${pagination.page + 1}">Volgende</a>`
                    : '<span class="btn btn-ghost" style="opacity:.4;pointer-events:none">Volgende</span>'}
            </div>
        </div>`;

    body.querySelectorAll('tr[data-id]').forEach((tr) => {
        tr.addEventListener('click', () => { window.location.href = `/tickets/${tr.dataset.id}`; });
    });
    body.querySelectorAll('a[data-page]').forEach((a) => {
        a.addEventListener('click', (e) => { e.preventDefault(); navigate(a.getAttribute('href')); });
    });
}

async function load() {
    if (!document.getElementById('ticketsCardBody')) {
        renderShell();
    }
    document.getElementById('ticketsCardBody').innerHTML = '<div class="empty-state">Laden&hellip;</div>';

    try {
        const res = await api.get(apiQuery());
        renderStatusTabs(res.meta.statusCounts);
        renderFilters(res.meta.filterOptions);
        renderActiveChip();
        renderTable(res.data, res.meta.pagination);
    } catch (e) {
        const message = e instanceof ApiError ? e.message : 'Kon tickets niet laden.';
        document.getElementById('ticketsCardBody').innerHTML =
            `<div class="empty-state" style="color:var(--color-text-danger)">${esc(message)} <button class="btn" id="ticketsRetryBtn" style="margin-left:8px">Opnieuw proberen</button></div>`;
        const retry = document.getElementById('ticketsRetryBtn');
        if (retry) retry.addEventListener('click', load);
    }
}

renderShell();
load();
