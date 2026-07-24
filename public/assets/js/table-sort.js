/**
 * Herbruikbare klikbaar-sorteren-helper voor client-side gerenderde tabelkoppen (Tickets, Voorraad
 * op dit moment — zie CLAUDE.md "Klikbaar sorteren" voor welke modules nog volgen).
 *
 * BEWUSTE UITBREIDING bovenop de Lovable-mockup: klikbaar sorteren bestaat nergens in het
 * Lovable-project zelf (geverifieerd in zowel tickets.index.tsx als modules.voorraad.tsx — platte
 * <th> zonder onClick/sorteericoon). De gebruiker wilde dit alsnog, dus dit is geen 1-op-1-conversie
 * maar een eigen toevoeging. Hergebruikt daarom bewust dezelfde `.th-sort`/`.sort-arrow`-CSS-klassen
 * als de bestaande server-rendered `sortLink()`-helper (app/Views/partials/ticket-helpers.php), zodat
 * het patroon er identiek uitziet ongeacht of een module al op de JSON-API draait.
 *
 * Backend-ondersteuning loopt via de generieke sort/dir-querystring die App\Core\TableQuery::apply()
 * al afhandelt voor elk lijst-endpoint dat TableQuery gebruikt — controleer dit per module voordat je
 * een kolom sorteerbaar maakt, en maak alleen kolommen sorteerbaar die corresponderen met een echte
 * databasekolom (geen client-side-only afgeleide waarden zoals een samengestelde naam).
 */

/**
 * @param {string} column Veldnaam zoals de backend/array-rij die kent (bv. "opdrachtgever_naam").
 * @param {string} label Zichtbare kolomtekst (wordt niet ge-escaped — geef alleen statische labels door).
 * @param {string|null} currentSort Huidige sort-param.
 * @param {string} currentDir Huidige dir-param ("asc"/"desc").
 * @returns {string} HTML voor de inhoud van een <th data-sort-column="...">.
 */
export function sortableHeaderHtml(column, label, currentSort, currentDir) {
    const nextDir = (currentSort === column && currentDir === 'asc') ? 'desc' : 'asc';
    const arrow = currentSort === column
        ? `<span class="sort-arrow">${currentDir === 'desc' ? '&darr;' : '&uarr;'}</span>`
        : '';
    return `<a class="th-sort" href="#" data-sort-column="${column}" data-sort-dir="${nextDir}">${label}${arrow}</a>`;
}

/**
 * Eén keer aanroepen op een voorouder-element (bv. de <table> of <thead>) van de gerenderde
 * sorteerbare headers. Gebruikt event delegation, dus werkt ook na een re-render van de <thead>.
 * @param {HTMLElement} container
 * @param {(column: string, dir: string) => void} onSort
 */
export function bindSortableHeaders(container, onSort) {
    container.addEventListener('click', (e) => {
        const link = e.target.closest('a[data-sort-column]');
        if (!link) return;
        e.preventDefault();
        onSort(link.dataset.sortColumn, link.dataset.sortDir);
    });
}
