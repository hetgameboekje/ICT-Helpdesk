import { api } from '/assets/js/api/client.js';

const trigger = document.getElementById('globalSearchTrigger');
const backdrop = document.getElementById('globalSearchBackdrop');
const panel = document.getElementById('globalSearchPanel');
const input = document.getElementById('globalSearchInput');
const body = document.getElementById('globalSearchResults');
const countEl = document.getElementById('globalSearchCount');

if (trigger && backdrop && panel && input && body) {
    const GROUPS = [
        { key: 'tickets', label: 'Tickets', href: (item) => `/tickets/${item.id}`, icon: 'bi-ticket-perforated', iconClass: 'global-search-item-icon-ticket', render: renderTicket },
        { key: 'medewerkers', label: 'Medewerkers', href: (item) => `/medewerkers/${item.id}`, icon: null, iconClass: 'global-search-item-icon-medewerker', render: renderMedewerker },
        { key: 'kennisbank', label: 'Kennisbank', href: (item) => `/kennisbank/${item.id}`, icon: 'bi-book', iconClass: 'global-search-item-icon-kennisbank', render: renderKennisbank },
    ];

    let debounceTimer = null;
    let currentQuery = '';

    trigger.addEventListener('click', open);

    backdrop.addEventListener('click', close);

    document.addEventListener('keydown', (e) => {
        if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') {
            e.preventDefault();
            open();
        } else if (e.key === 'Escape' && !panel.hidden) {
            close();
        }
    });

    input.addEventListener('input', () => {
        clearTimeout(debounceTimer);
        const q = input.value.trim();
        if (q.length < 2) {
            renderHint();
            return;
        }
        debounceTimer = setTimeout(() => runSearch(q), 300);
    });

    function open() {
        backdrop.hidden = false;
        panel.hidden = false;
        input.value = '';
        renderHint();
        setTimeout(() => input.focus(), 10);
    }

    function close() {
        backdrop.hidden = true;
        panel.hidden = true;
    }

    async function runSearch(q) {
        currentQuery = q;
        try {
            const payload = await api.get(`/api/v1/search?q=${encodeURIComponent(q)}`);
            if (q !== currentQuery) return;
            render(payload.data);
        } catch (e) {
            renderHint();
        }
    }

    function renderHint() {
        countEl.textContent = '';
        body.innerHTML = '<div class="global-search-hint">Typ minimaal 2 tekens om te zoeken in tickets, medewerkers en de kennisbank.</div>';
    }

    function render(data) {
        const total = GROUPS.reduce((sum, group) => sum + (data[group.key]?.length ?? 0), 0);
        countEl.textContent = total > 0 ? `${total} resultaten` : '';

        if (total === 0) {
            body.innerHTML = `
                <div class="global-search-empty">
                    <div class="global-search-empty-icon"><i class="bi bi-search"></i></div>
                    <div class="global-search-empty-title">Geen resultaten gevonden</div>
                    <div>Probeer een andere naam of trefwoord.</div>
                </div>
            `;
            return;
        }

        body.innerHTML = GROUPS
            .map((group) => {
                const items = data[group.key] || [];
                if (items.length === 0) return '';
                const rows = items.map((item) => `
                    <a class="global-search-item" href="${group.href(item)}">
                        <span class="global-search-item-icon ${group.iconClass}">${group.icon ? `<i class="bi ${group.icon}"></i>` : esc(initials(item.naam))}</span>
                        <span class="global-search-item-body">${group.render(item)}</span>
                        <i class="bi bi-chevron-right global-search-item-chevron"></i>
                    </a>
                `).join('');
                return `
                    <div class="global-search-group">
                        <div class="global-search-group-label">${group.label}</div>
                        ${rows}
                    </div>
                `;
            })
            .join('');
    }

    function esc(value) {
        const div = document.createElement('div');
        div.textContent = value ?? '';
        return div.innerHTML;
    }

    function initials(naam) {
        return (naam || '')
            .split(' ')
            .filter(Boolean)
            .slice(0, 2)
            .map((part) => part[0])
            .join('')
            .toUpperCase();
    }

    function renderTicket(item) {
        return `
            <span class="global-search-item-title">${esc(item.titel)}</span>
            <span class="global-search-item-sub"><span class="badge badge-${esc(item.status)}">${esc(item.status)}</span></span>
        `;
    }

    function renderMedewerker(item) {
        return `
            <span class="global-search-item-title">${esc(item.naam)}</span>
            ${item.functie ? `<span class="global-search-item-sub">${esc(item.functie)}</span>` : ''}
        `;
    }

    function renderKennisbank(item) {
        return `
            <span class="global-search-item-title">${esc(item.titel)}</span>
            ${item.categorie ? `<span class="global-search-item-sub">${esc(item.categorie)}</span>` : ''}
        `;
    }
}
