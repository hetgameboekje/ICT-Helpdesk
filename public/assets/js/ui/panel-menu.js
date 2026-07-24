/**
 * Herbruikbaar UI-patroon voor detailpanelen die Lovable-strak moeten blijven, maar wel een
 * secundaire actie (bv. "Logboek bekijken") nodig hebben zonder het hoofdpaneel te vervuilen:
 * een "..."-actiemenu (dropdown) dat een uitklap-sheet vanaf de rechterkant opent.
 * Zie CLAUDE.md ("Logboek/historie-patroon") voor de achtergrond en welke modules dit gebruiken.
 *
 * Gebruik:
 *   import { actionMenuHtml, wireActionMenu, openSheet } from '/assets/js/ui/panel-menu.js';
 *   // in de detail-HTML: actionMenuHtml('cr-detail')
 *   // na het inserten in de DOM:
 *   wireActionMenu(detailEl, 'cr-detail', [
 *       { label: 'Logboek bekijken', icon: 'bi-clock-history', onClick: () => openSheet('Logboek', renderLogHtml(logs)) },
 *   ]);
 */

let sheetEl = null;

function ensureSheet() {
    if (sheetEl) return sheetEl;
    sheetEl = document.createElement('div');
    sheetEl.className = 'app-sheet-backdrop';
    sheetEl.innerHTML = `
        <aside class="app-sheet" role="dialog" aria-modal="true">
            <div class="app-sheet-header">
                <h3 class="app-sheet-title"></h3>
                <button type="button" class="app-sheet-close" aria-label="Sluiten"><i class="bi bi-x-lg"></i></button>
            </div>
            <div class="app-sheet-body"></div>
        </aside>
    `;
    document.body.appendChild(sheetEl);
    sheetEl.addEventListener('click', (e) => { if (e.target === sheetEl) closeSheet(); });
    sheetEl.querySelector('.app-sheet-close').addEventListener('click', closeSheet);
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && sheetEl.classList.contains('open')) closeSheet();
    });
    return sheetEl;
}

/** Opent de sheet met een titel en (al ge-escapete) HTML-inhoud. Retourneert de body-container. */
export function openSheet(title, bodyHtml) {
    const el = ensureSheet();
    el.querySelector('.app-sheet-title').textContent = title;
    el.querySelector('.app-sheet-body').innerHTML = bodyHtml;
    requestAnimationFrame(() => el.classList.add('open'));
    return el.querySelector('.app-sheet-body');
}

export function closeSheet() {
    if (sheetEl) sheetEl.classList.remove('open');
}

/** HTML voor de "..."-trigger + lege dropdown-lijst; plaats dit in de detail-header. */
export function actionMenuHtml(id) {
    return `
        <div class="action-menu" data-action-menu="${id}">
            <button type="button" class="btn btn-ghost action-menu-trigger" aria-haspopup="true" aria-expanded="false" title="Meer acties">
                <i class="bi bi-three-dots"></i>
            </button>
            <div class="action-menu-list" role="menu"></div>
        </div>
    `;
}

/** Koppelt click-handlers aan het actiemenu. items: [{label, icon, onClick}]. */
export function wireActionMenu(root, id, items) {
    const menu = root.querySelector(`[data-action-menu="${id}"]`);
    if (!menu) return;
    const trigger = menu.querySelector('.action-menu-trigger');
    const list = menu.querySelector('.action-menu-list');

    list.innerHTML = items.map((item, i) => `
        <button type="button" class="action-menu-item" data-idx="${i}" role="menuitem">
            ${item.icon ? `<i class="bi ${item.icon}"></i>` : ''}<span>${item.label}</span>
        </button>
    `).join('');

    function close() {
        menu.classList.remove('open');
        trigger.setAttribute('aria-expanded', 'false');
    }
    function toggle(e) {
        e.stopPropagation();
        const willOpen = !menu.classList.contains('open');
        document.querySelectorAll('.action-menu.open').forEach((m) => m.classList.remove('open'));
        if (willOpen) {
            menu.classList.add('open');
            trigger.setAttribute('aria-expanded', 'true');
        }
    }

    trigger.addEventListener('click', toggle);
    list.querySelectorAll('.action-menu-item').forEach((btn) => {
        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            close();
            items[parseInt(btn.dataset.idx, 10)].onClick();
        });
    });
    document.addEventListener('click', close);
}
