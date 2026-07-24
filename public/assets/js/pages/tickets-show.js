import { api, ApiError } from '/assets/js/api/client.js';

const STATUS_LABELS = {
    open: 'Open', in_behandeling: 'In behandeling', wacht_op_info: 'Wacht op info', afgehandeld: 'Afgehandeld',
};
const STATUS_TONE = { open: 'open', in_behandeling: 'behandeling', wacht_op_info: 'wachtend', afgehandeld: 'opgelost' };

function esc(value) {
    const div = document.createElement('div');
    div.textContent = value ?? '';
    return div.innerHTML;
}
function nl2br(value) {
    return esc(value).replace(/\n/g, '<br>');
}
function formatDatum(value) {
    if (!value) return '—';
    const [y, m, d] = String(value).slice(0, 10).split('-');
    return `${d}-${m}-${y}`;
}
function formatDatumTijd(value) {
    if (!value) return '—';
    const [datum, tijd] = String(value).split(' ');
    return `${formatDatum(datum)} ${(tijd || '').slice(0, 5)}`;
}
function statusBadge(status) {
    return `<span class="badge badge-${esc(status)}">${esc(STATUS_LABELS[status] || status)}</span>`;
}
function prioBadge(prio) {
    const labels = { laag: 'Laag', normaal: 'Normaal', hoog: 'Hoog', kritiek: 'Kritiek' };
    return `<span class="prio prio-${esc(prio)}"><span class="prio-dot"></span>${esc(labels[prio] || prio)}</span>`;
}

const id = window.location.pathname.split('/').filter(Boolean).pop();
const root = document.getElementById('ticket-app');

function flash(message, type = 'error') {
    const el = document.createElement('div');
    el.className = `alert alert-${type === 'error' ? 'error' : 'success'}`;
    el.textContent = message;
    root.prepend(el);
    window.setTimeout(() => el.remove(), 5000);
}

function renderTimeline(logs) {
    if (logs.length === 0) {
        return '<div class="empty-state">Nog geen gebeurtenissen.</div>';
    }
    const items = logs.map((log) => {
        const isStatus = log.status_naar !== null;
        const heeftOpmerking = (log.opmerking && log.opmerking.trim() !== '') || log.titel;
        return `
            <div class="timeline-item">
                <div class="timeline-connector"></div>
                <div class="timeline-icon ${isStatus ? 'timeline-icon-status' : 'timeline-icon-opmerking'}">
                    <i class="bi ${isStatus ? 'bi-arrow-repeat' : 'bi-chat-left-text'}"></i>
                </div>
                <div class="timeline-body">
                    <div class="timeline-head">
                        <span class="timeline-actor">${esc(log.user_naam || 'ACA')}</span>
                        <span class="timeline-action">${isStatus ? 'wijzigde de status' : 'voegde een opmerking toe'}</span>
                        <span class="timeline-time">${formatDatumTijd(log.created_at)}</span>
                    </div>
                    ${isStatus ? `<div style="margin-top:6px"><span class="status-change">
                        <span class="badge badge-${esc(log.status_van)}" style="padding:2px 6px;font-size:10px">${esc(STATUS_LABELS[log.status_van] || log.status_van)}</span>
                        &rarr;
                        <span class="badge badge-${esc(log.status_naar)}" style="padding:2px 6px;font-size:10px">${esc(STATUS_LABELS[log.status_naar] || log.status_naar)}</span>
                    </span></div>` : ''}
                    ${heeftOpmerking ? `<div class="timeline-detail">${log.titel ? `<strong>${esc(log.titel)}</strong><br>` : ''}${log.opmerking ? nl2br(log.opmerking) : ''}</div>` : ''}
                </div>
            </div>`;
    }).join('');
    return `<div class="timeline">${items}</div>`;
}

function renderKbItem(artikel, { koppeling = false, categorie = '' } = {}) {
    if (koppeling) {
        return `
            <div class="kb-item" style="display:flex;align-items:center;justify-content:space-between;gap:8px">
                <a href="/kennisbank/${artikel.id}" style="min-width:0">
                    <div class="kb-item-id">Gekoppeld</div>
                    <div class="kb-item-title text-truncate">${esc(artikel.titel)}</div>
                </a>
                <button class="btn btn-danger" type="button" data-unlink="${artikel.id}" style="padding:2px 8px;font-size:11px">&times;</button>
            </div>`;
    }
    return `
        <div class="kb-item" style="display:flex;align-items:center;justify-content:space-between;gap:8px">
            <span style="min-width:0">
                <span class="kb-item-id">Suggestie &middot; ${esc(categorie)}</span>
                <span class="kb-item-title text-truncate" style="display:block">${esc(artikel.titel)}</span>
            </span>
            <button class="btn" type="button" data-link="${artikel.id}" style="padding:2px 8px;font-size:11px">Koppelen</button>
        </div>`;
}

function render(state) {
    const { item, logs, tijdregistraties, tijdTotaal, gekoppeldeArtikelen, suggestiesArtikelen } = state;

    document.title = `#${item.id} — ${item.titel} · Intranet`;
    const titleEl = document.querySelector('.topbar-title');
    if (titleEl) titleEl.textContent = `#${item.id} — ${item.titel}`;

    root.innerHTML = `
        <div class="detail-subheader">
            <a class="detail-back" href="/tickets"><i class="bi bi-arrow-left"></i> Terug</a>
            <span style="color:var(--color-text-tertiary)">&middot;</span>
            ${statusBadge(item.status)}
            ${prioBadge(item.prioriteit)}
            <div style="flex:1"></div>
            ${item.status !== 'afgehandeld' ? '<button type="button" class="btn btn-ghost" id="markAfgehandeldBtn"><i class="bi bi-check-circle"></i> Markeer afgehandeld</button>' : ''}
            <a class="btn btn-ghost" href="/tickets/${item.id}/edit"><i class="bi bi-person-circle"></i> Toewijzen / bewerken</a>
            <form method="post" action="/tickets/${item.id}/verwijderen" onsubmit="return confirm('Weet je zeker dat je dit item wilt verwijderen? Het blijft bewaard in de database, maar verdwijnt uit het overzicht.')">
                <button class="btn btn-danger" type="submit">Verwijderen</button>
            </form>
        </div>

        <div class="grid-detail">
            <div class="grid-detail-main">
                <div class="card" style="padding:18px;margin-bottom:16px">
                    <div style="display:flex;align-items:center;gap:8px;margin-bottom:10px">
                        <i class="bi bi-envelope" style="color:var(--color-text-tertiary)"></i>
                        <span style="font-size:12px;font-weight:500;color:var(--color-text-secondary)">Oorspronkelijk bericht van ${esc(item.opdrachtgever_naam)}</span>
                    </div>
                    <div style="font-size:13px;line-height:1.7;color:var(--color-text-secondary);overflow-wrap:anywhere">
                        ${item.omschrijving ? nl2br(item.omschrijving) : '<span style="color:var(--color-text-tertiary)">Geen omschrijving</span>'}
                    </div>
                </div>

                <div class="card" style="margin-bottom:16px">
                    <div class="card-header"><span class="card-title">Opmerking toevoegen</span></div>
                    <div style="padding:16px;border-bottom:0.5px solid var(--color-border-tertiary)">
                        <div class="form-group" style="margin-bottom:10px">
                            <label class="form-label">Titel</label>
                            <input type="text" id="opmerkingTitel" placeholder="Korte titel voor deze opmerking">
                        </div>
                        <textarea id="opmerkingTekst" placeholder="Beschrijf wat je gedaan hebt of vraag om meer informatie&hellip;" style="min-height:90px"></textarea>
                    </div>
                    <div style="display:flex;align-items:center;justify-content:flex-end;padding:10px 16px;background:var(--color-background-secondary)">
                        <button class="btn btn-accent" type="button" id="opmerkingOpslaanBtn">Opslaan</button>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <span class="card-title">Logboek &amp; tijdlijn</span>
                        <span style="font-size:11px;color:var(--color-text-tertiary)">${logs.length} gebeurtenissen</span>
                    </div>
                    ${renderTimeline(logs)}
                </div>
            </div>

            <div class="grid-detail-side">
                <div class="card" style="padding:16px">
                    <h3 class="detail-side-heading">Details</h3>
                    <div class="meta-row"><span class="meta-key">Opdrachtgever</span><span>${esc(item.opdrachtgever_naam)}</span></div>
                    <div class="meta-row"><span class="meta-key">Afdeling</span><span>${esc(item.afdeling_naam || '—')}</span></div>
                    <div class="meta-row"><span class="meta-key">Behandelaar</span><span>${esc(item.behandelaar_naam || '—')}</span></div>
                    <div class="meta-row"><span class="meta-key">Categorie</span><span>${esc(item.categorie || 'Algemeen')}</span></div>
                    ${item.is_cyberrisico ? `<div class="meta-row"><span class="meta-key">Cyber risico</span><span>${prioBadge('kritiek')}</span></div>` : ''}
                    <div class="meta-row"><span class="meta-key">Impact</span><span>${esc(item.impact)}</span></div>
                    <div class="meta-row"><span class="meta-key">Schatting</span><span>${item.schatting_minuten !== null ? item.schatting_minuten + ' min' : '—'}</span></div>
                    <div class="meta-row"><span class="meta-key">Aangemaakt</span><span>${formatDatum(item.created_at)}</span></div>
                    <div class="meta-row"><span class="meta-key">Bijgewerkt</span><span>${formatDatum(item.updated_at)}</span></div>
                    <div class="meta-row"><span class="meta-key">Deadline</span><span>${formatDatum(item.deadline)}</span></div>
                </div>

                <div class="card" style="padding:16px">
                    <h3 class="detail-side-heading">Status wijzigen</h3>
                    <div class="status-picker">
                        ${Object.entries(STATUS_LABELS).map(([val, label]) => `
                            <button type="button" data-status="${val}" class="${item.status === val ? 'active' : ''}"
                                style="background:var(--color-status-${STATUS_TONE[val]}-bg);color:var(--color-status-${STATUS_TONE[val]})">
                                ${esc(label)}
                            </button>`).join('')}
                    </div>
                </div>

                <div class="card" style="padding:16px">
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px">
                        <h3 class="detail-side-heading" style="margin-bottom:0">Tijdregistratie</h3>
                        <span style="font-size:11px;color:var(--color-text-tertiary)">Totaal: ${tijdTotaal} min</span>
                    </div>
                    <div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:${tijdregistraties.length ? '10px' : '0'}">
                        ${[5, 10, 15, 30, 45, 60].map((m) => `<button class="btn" type="button" data-minuten="${m}" style="padding:4px 9px;font-size:11.5px">${m} min</button>`).join('')}
                    </div>
                    ${tijdregistraties.length ? `<div class="log-list" style="max-height:160px;margin:0 -16px -16px;border-top:0.5px solid var(--color-border-tertiary)">
                        ${tijdregistraties.map((t) => `
                            <div class="log-item">
                                <div class="log-meta"><span class="log-user">${esc(t.user_naam || 'Onbekend')}</span><span class="log-time">${formatDatumTijd(t.created_at)}</span></div>
                                <div class="log-text">${t.minuten} min</div>
                            </div>`).join('')}
                    </div>` : ''}
                </div>

                <div class="card" style="padding:16px">
                    <h3 class="detail-side-heading">Escalatie</h3>
                    <div class="form-group" style="margin-bottom:10px">
                        <label class="form-label">Escalatienummer</label>
                        <input type="text" id="escalatieNummer" value="${esc(item.escalatie_nummer || '')}" placeholder="Bijv. CAS-109311-L4Z5L7 - ACA:000133869">
                    </div>
                    <div class="form-group" style="margin-bottom:10px">
                        <label class="form-label">Instantie</label>
                        <input type="text" id="escalatieInstantie" value="${esc(item.escalatie_instantie || '')}" placeholder="Bijv. ACA, ClearSolutions">
                    </div>
                    <button class="btn" type="button" id="escalatieOpslaanBtn">Opslaan</button>
                </div>

                <div class="card" style="padding:16px">
                    <div style="display:flex;align-items:center;gap:6px;margin-bottom:10px">
                        <i class="bi bi-stars" style="color:var(--color-primary);font-size:12px"></i>
                        <h3 class="detail-side-heading" style="margin-bottom:0">Kennisbank</h3>
                    </div>
                    ${(gekoppeldeArtikelen.length === 0 && suggestiesArtikelen.length === 0)
                        ? '<div style="font-size:12.5px;color:var(--color-text-tertiary)">Nog geen artikelen gekoppeld of gesuggereerd.</div>' : ''}
                    ${gekoppeldeArtikelen.map((a) => renderKbItem(a, { koppeling: true })).join('')}
                    ${suggestiesArtikelen.length ? (gekoppeldeArtikelen.length ? '<div style="font-size:10.5px;text-transform:uppercase;letter-spacing:.04em;color:var(--color-text-tertiary);margin:10px 0 6px">Suggesties</div>' : '') : ''}
                    ${suggestiesArtikelen.map((a) => renderKbItem(a, { categorie: item.categorie || 'Algemeen' })).join('')}
                </div>
            </div>
        </div>
    `;

    wireEvents(state);
}

function wireEvents(state) {
    const markBtn = document.getElementById('markAfgehandeldBtn');
    if (markBtn) markBtn.addEventListener('click', () => saveLog({ status: 'afgehandeld' }));

    document.getElementById('opmerkingOpslaanBtn').addEventListener('click', () => {
        const titel = document.getElementById('opmerkingTitel').value.trim();
        const opmerking = document.getElementById('opmerkingTekst').value.trim();
        if (opmerking !== '' && titel === '') {
            flash('Vul een titel in om deze opmerking op te slaan.');
            document.getElementById('opmerkingTitel').focus();
            return;
        }
        saveLog({ titel, opmerking });
    });

    document.querySelectorAll('.status-picker button[data-status]').forEach((btn) => {
        btn.addEventListener('click', () => saveLog({ status: btn.dataset.status }));
    });

    document.getElementById('escalatieOpslaanBtn').addEventListener('click', () => {
        saveLog({
            escalatie_nummer: document.getElementById('escalatieNummer').value.trim(),
            escalatie_instantie: document.getElementById('escalatieInstantie').value.trim(),
        });
    });

    document.querySelectorAll('button[data-minuten]').forEach((btn) => {
        btn.addEventListener('click', async () => {
            try {
                const res = await api.post(`/api/v1/tickets/${id}/tijd`, { minuten: parseInt(btn.dataset.minuten, 10) });
                Object.assign(state, res.data);
                render(state);
            } catch (e) {
                flash(e instanceof ApiError ? e.message : 'Tijd registreren is mislukt.');
            }
        });
    });

    document.querySelectorAll('button[data-link]').forEach((btn) => {
        btn.addEventListener('click', async () => {
            try {
                const res = await api.post(`/api/v1/tickets/${id}/kennisbank/${btn.dataset.link}`);
                state.gekoppeldeArtikelen = res.data.gekoppeldeArtikelen;
                state.suggestiesArtikelen = state.suggestiesArtikelen.filter((a) => String(a.id) !== btn.dataset.link);
                render(state);
            } catch (e) {
                flash(e instanceof ApiError ? e.message : 'Koppelen is mislukt.');
            }
        });
    });

    document.querySelectorAll('button[data-unlink]').forEach((btn) => {
        btn.addEventListener('click', async () => {
            if (!window.confirm('Koppeling met dit artikel verwijderen?')) return;
            try {
                const res = await api.del(`/api/v1/tickets/${id}/kennisbank/${btn.dataset.unlink}`);
                state.gekoppeldeArtikelen = res.data.gekoppeldeArtikelen;
                render(state);
            } catch (e) {
                flash(e instanceof ApiError ? e.message : 'Ontkoppelen is mislukt.');
            }
        });
    });
}

async function saveLog(fields) {
    try {
        const res = await api.post(`/api/v1/tickets/${id}/log`, fields);
        render(res.data);
    } catch (e) {
        flash(e instanceof ApiError ? e.message : 'Opslaan is mislukt.');
    }
}

async function load() {
    root.innerHTML = '<div class="empty-state">Laden&hellip;</div>';
    try {
        const res = await api.get(`/api/v1/tickets/${id}`);
        render(res.data);
    } catch (e) {
        const message = e instanceof ApiError ? e.message : 'Kon ticket niet laden.';
        root.innerHTML = `<div class="empty-state" style="color:var(--color-text-danger)">${esc(message)}</div>`;
    }
}

load();
