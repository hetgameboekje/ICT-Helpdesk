<?php
/**
 * Gedeelde tab-navigatie voor de Tools-sectie, o.b.v. de tabbladen uit Lovable's modules.tools.tsx
 * ("Telefoonlijst" / "E-mailhandtekening" / "Herstart-herinneringen"). Zoals bij beheer-tabs.php:
 * dit zijn al aparte, volledig werkende server-rendered pagina's, dus server-side navigatielinks
 * i.p.v. client-side tabs op één scherm. Twee extra tabs t.o.v. de mockup: "Overzicht" (de
 * bestaande /tools-hub) en "Installatie" (een bestaande, echte tool zonder Lovable-tegenhanger).
 *
 * @param string $actief 'overzicht'|'telefoonlijst'|'handtekeningen'|'herstart'|'installatie'|'entra-ninjaone'
 */
function toolsTabs(string $actief): string
{
    $tabs = [
        'overzicht' => ['label' => 'Overzicht', 'icon' => 'bi-grid', 'href' => '/tools'],
        'telefoonlijst' => ['label' => 'Telefoonlijst', 'icon' => 'bi-telephone', 'href' => '/tools/telefoonlijst'],
        'handtekeningen' => ['label' => 'E-mailhandtekening', 'icon' => 'bi-pen', 'href' => '/tools/handtekeningen'],
        'herstart' => ['label' => 'Herstart-herinneringen', 'icon' => 'bi-arrow-repeat', 'href' => '/tools/herstart-herinneringen'],
        'installatie' => ['label' => 'Installatie', 'icon' => 'bi-hdd-stack', 'href' => '/tools/installatie'],
        'entra-ninjaone' => ['label' => 'Entra → NinjaOne', 'icon' => 'bi-arrow-left-right', 'href' => '/tools/entra-ninjaone'],
    ];

    $html = '<div style="display:flex;gap:2px;border-bottom:1px solid var(--color-border-tertiary);margin-bottom:16px;overflow-x:auto">';
    foreach ($tabs as $key => $tab) {
        $isActief = $key === $actief;
        $style = $isActief
            ? 'color:var(--color-text-primary);border-bottom:2px solid var(--color-primary)'
            : 'color:var(--color-text-tertiary);border-bottom:2px solid transparent';
        $html .= '<a href="' . htmlspecialchars($tab['href']) . '" style="display:flex;align-items:center;gap:6px;padding:10px 14px;font-size:13px;font-weight:500;text-decoration:none;margin-bottom:-1px;white-space:nowrap;' . $style . '">'
            . '<i class="bi ' . $tab['icon'] . '"></i>' . htmlspecialchars($tab['label']) . '</a>';
    }
    $html .= '</div>';

    return $html;
}
