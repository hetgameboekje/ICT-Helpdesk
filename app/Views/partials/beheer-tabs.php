<?php
/**
 * Gedeelde tab-navigatie voor de Beheer-sectie, o.b.v. de tabbladen uit Lovable's
 * modules.beheer.tsx ("Gebruikers & rechten" / "API-sleutels" / "E-mailqueue" / "Logs"). Anders dan
 * de mockup (client-side tabs op één scherm) zijn dit hier gewone server-rendered paginalinks —
 * elke sectie was al een aparte, volledig werkende pagina (incl. eigen create/edit-formulieren),
 * dus die zijn behouden i.p.v. samengevoegd tot één client-side app. Een 5e tab "Systeembeheer"
 * (git pull/database/export/locaties) is toegevoegd omdat dat een bestaand echt onderdeel van
 * /beheer is dat geen tegenhanger heeft in de mockup.
 *
 * @param string $actief 'rechten'|'api-sleutels'|'emails'|'log'|'systeem'
 */
function beheerTabs(string $actief): string
{
    $tabs = [
        'rechten' => ['label' => 'Gebruikers & rechten', 'icon' => 'bi-people', 'href' => '/beheer/rechten'],
        'api-sleutels' => ['label' => 'API-sleutels', 'icon' => 'bi-key', 'href' => '/beheer/api-sleutels'],
        'emails' => ['label' => 'E-mailqueue', 'icon' => 'bi-envelope', 'href' => '/beheer/emails'],
        'log' => ['label' => 'Logs', 'icon' => 'bi-list-columns-reverse', 'href' => '/beheer/log'],
        'systeem' => ['label' => 'Systeembeheer', 'icon' => 'bi-gear', 'href' => '/beheer'],
    ];

    $html = '<div style="display:flex;gap:2px;border-bottom:1px solid var(--color-border-tertiary);margin-bottom:16px">';
    foreach ($tabs as $key => $tab) {
        $isActief = $key === $actief;
        $style = $isActief
            ? 'color:var(--color-text-primary);border-bottom:2px solid var(--color-primary)'
            : 'color:var(--color-text-tertiary);border-bottom:2px solid transparent';
        $html .= '<a href="' . htmlspecialchars($tab['href']) . '" style="display:flex;align-items:center;gap:6px;padding:10px 14px;font-size:13px;font-weight:500;text-decoration:none;margin-bottom:-1px;' . $style . '">'
            . '<i class="bi ' . $tab['icon'] . '"></i>' . htmlspecialchars($tab['label']) . '</a>';
    }
    $html .= '</div>';

    return $html;
}
