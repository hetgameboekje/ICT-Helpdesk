<?php

if (!function_exists('prioBadge')) {
    function prioBadge(string $prio): string
    {
        $labels = ['laag' => 'Laag', 'normaal' => 'Normaal', 'middel' => 'Middel', 'hoog' => 'Hoog', 'kritiek' => 'Kritiek'];
        $label = $labels[$prio] ?? ucfirst($prio);
        return '<span class="prio prio-' . htmlspecialchars($prio) . '"><span class="prio-dot"></span>' . htmlspecialchars($label) . '</span>';
    }
}

if (!function_exists('statusLabel')) {
    function statusLabel(string $status): string
    {
        $labels = [
            'alle' => 'Alle statussen',
            'open' => 'Open',
            'in_behandeling' => 'In behandeling',
            'wacht_op_info' => 'Wacht op info',
            'afgehandeld' => 'Afgehandeld',
            'opgelost' => 'Opgelost',
            'gesloten' => 'Gesloten',
            'nieuw' => 'Nieuw',
            'in_overweging' => 'In overweging',
            'goedgekeurd' => 'Goedgekeurd',
            'afgewezen' => 'Afgewezen',
            'uitgevoerd' => 'Uitgevoerd',
            'aangevraagd' => 'Aangevraagd',
            'afgekeurd' => 'Afgekeurd',
            'besteld' => 'Besteld',
            'geleverd' => 'Geleverd',
            'actief' => 'Actief',
            'inactief' => 'Inactief',
            'in_onderzoek' => 'In onderzoek',
            'bevestigd' => 'Bevestigd',
            'geaccepteerd' => 'Geaccepteerd risico',
            // E-mailverwerking (MailMind) — imported_emails.status / kb_article_drafts.status.
            'received' => 'Ontvangen',
            'parsed' => 'Verwerkt',
            'stored' => 'Opgeslagen',
            'analyzed' => 'Geanalyseerd',
            'draft_created' => 'Concept klaar',
            'in_review' => 'In review',
            'published' => 'Gepubliceerd',
            'failed' => 'Mislukt',
            'afgewezen' => 'Afgewezen',
        ];
        return $labels[$status] ?? ucfirst(str_replace('_', ' ', $status));
    }
}

if (!function_exists('statusBadge')) {
    function statusBadge(string $status): string
    {
        return '<span class="badge badge-' . htmlspecialchars($status) . '">' . htmlspecialchars(statusLabel($status)) . '</span>';
    }
}

if (!function_exists('truncateWoorden')) {
    /** Kapt tekst af op $maxWoorden woorden — voorkomt dat een lange taaknaam/omschrijving een tabel breder maakt dan zijn kaart. */
    function truncateWoorden(string $tekst, int $maxWoorden = 10): string
    {
        $woorden = preg_split('/\s+/', trim($tekst));
        if (count($woorden) <= $maxWoorden) {
            return $tekst;
        }

        return implode(' ', array_slice($woorden, 0, $maxWoorden)) . '…';
    }
}

if (!function_exists('behandelaarInitialen')) {
    /** Initialen voor de avatar-cirkel in tabellen (bv. "Sanne de Vries" -> "SV"). */
    function behandelaarInitialen(string $naam): string
    {
        $parts = preg_split('/\s+/', trim($naam));
        $letters = array_map(fn($p) => strtoupper(substr($p, 0, 1)), array_slice($parts, 0, 2));
        return implode('', $letters) ?: '?';
    }
}

if (!function_exists('formatDatum')) {
    function formatDatum(?string $datum): string
    {
        if (empty($datum)) {
            return '—';
        }
        return date('d-m-Y', strtotime($datum));
    }
}

if (!function_exists('formatDatumTijd')) {
    function formatDatumTijd(?string $datum): string
    {
        if (empty($datum)) {
            return '—';
        }
        return date('d-m-Y H:i', strtotime($datum));
    }
}

if (!function_exists('sortLink')) {
    function sortLink(string $column, string $label, ?string $currentSort, string $currentDir): string
    {
        $nextDir = ($currentSort === $column && $currentDir === 'asc') ? 'desc' : 'asc';

        $params = $_GET;
        unset($params['sort'], $params['dir']);
        $params['sort'] = $column;
        $params['dir'] = $nextDir;

        $arrow = '';
        if ($currentSort === $column) {
            $arrow = '<span class="sort-arrow">' . ($currentDir === 'asc' ? '&uarr;' : '&darr;') . '</span>';
        }

        return '<a class="th-sort" href="?' . htmlspecialchars(http_build_query($params)) . '">'
            . htmlspecialchars($label) . $arrow . '</a>';
    }
}

if (!function_exists('activeFilterChip')) {
    /** @param array<int,string> $exclude Extra $_GET-sleutels die al elders zichtbaar zijn (bv. 'status'
     *  als een module dat al als pil-tabs toont) en dus geen dubbele chip nodig hebben. */
    function activeFilterChip(string $routeBase, array $exclude = []): string
    {
        $filters = array_diff_key($_GET, array_merge(['sort' => null, 'dir' => null, 'q' => null, 'page' => null], array_fill_keys($exclude, null)));
        $filters = array_filter($filters, fn ($v) => is_scalar($v) && $v !== '');

        if (empty($filters)) {
            return '';
        }

        $parts = [];
        foreach ($filters as $key => $value) {
            $displayValue = ($key === 'status' && function_exists('statusLabel'))
                ? statusLabel((string) $value)
                : (string) $value;
            $parts[] = htmlspecialchars(ucfirst((string) $key)) . ': ' . htmlspecialchars($displayValue);
        }

        return '<a class="filter-chip" href="/' . htmlspecialchars($routeBase) . '">'
            . implode(', ', $parts) . ' &times;</a>';
    }
}

if (!function_exists('filterSelect')) {
    function filterSelect(string $name, string $allLabel, array $options): string
    {
        $current = $_GET[$name] ?? '';

        $html = '<select name="' . htmlspecialchars($name) . '" onchange="this.form.submit()">';
        $html .= '<option value="">' . htmlspecialchars($allLabel) . '</option>';
        foreach ($options as $value => $label) {
            $selected = ((string) $value === (string) $current) ? ' selected' : '';
            $html .= '<option value="' . htmlspecialchars((string) $value) . '"' . $selected . '>'
                . htmlspecialchars($label) . '</option>';
        }
        $html .= '</select>';

        return $html;
    }
}

if (!function_exists('paginaBezoekParametersHtml')) {
    /**
     * Rendert de JSON-parameters van een paginabezoek (zie PaginaBezoekLogger) als een knop die de
     * key: value-regels in een popup (native <dialog>) toont, zodat de tabelrij niet de volledige
     * hoogte van de parameterlijst inneemt.
     */
    function paginaBezoekParametersHtml(?string $parametersJson, int $bezoekId): string
    {
        if ($parametersJson === null || $parametersJson === '') {
            return '<span style="color:var(--color-text-tertiary)">—</span>';
        }

        $params = json_decode($parametersJson, true);
        if (!is_array($params) || empty($params)) {
            return '<span style="color:var(--color-text-tertiary)">—</span>';
        }

        $lines = [];
        foreach ($params as $key => $value) {
            $waarde = is_array($value) ? json_encode($value) : (string) $value;
            $lines[] = '<div><strong>' . htmlspecialchars((string) $key) . ':</strong> ' . htmlspecialchars($waarde) . '</div>';
        }

        $dialogId = 'paginabezoek-params-' . $bezoekId;

        return '<button type="button" class="btn" onclick="document.getElementById(\'' . $dialogId . '\').showModal()">Bekijken (' . count($params) . ')</button>'
            . '<dialog id="' . $dialogId . '" style="max-width:480px;width:90%;border-radius:8px;padding:16px">'
            . '<form method="dialog" style="margin:0 0 10px;text-align:right"><button type="submit" class="btn">Sluiten</button></form>'
            . '<div style="font-size:13px;line-height:1.6">' . implode('', $lines) . '</div>'
            . '</dialog>';
    }
}

if (!function_exists('deleteButton')) {
    function deleteButton(string $routeBase, int $id, string $label = 'Verwijderen'): string
    {
        return '<form method="post" action="/' . htmlspecialchars($routeBase) . '/' . $id . '/verwijderen" '
            . 'onsubmit="return confirm(\'Weet je zeker dat je dit item wilt verwijderen? Het blijft bewaard in de database, maar verdwijnt uit het overzicht.\')">'
            . '<button class="btn btn-danger" type="submit">' . htmlspecialchars($label) . '</button>'
            . '</form>';
    }
}

if (!function_exists('paginationLinks')) {
    function paginationLinks(array $pagination): string
    {
        if ($pagination['totalPages'] <= 1) {
            return '';
        }

        $page = $pagination['page'];
        $totalPages = $pagination['totalPages'];
        $perPage = $pagination['perPage'];
        $total = $pagination['total'];

        $linkFor = function (int $p): string {
            $params = $_GET;
            $params['page'] = $p;
            return '?' . htmlspecialchars(http_build_query($params));
        };

        $html = '<div class="pagination">';
        $html .= $page > 1
            ? '<a class="page-link" href="' . $linkFor($page - 1) . '">&larr;</a>'
            : '<span class="page-link page-link-disabled">&larr;</span>';

        for ($p = 1; $p <= $totalPages; $p++) {
            $start = ($p - 1) * $perPage + 1;
            $end = min($p * $perPage, $total);
            $label = $start . '-' . $end;

            $html .= $p === $page
                ? '<span class="page-link page-link-active">' . $label . '</span>'
                : '<a class="page-link" href="' . $linkFor($p) . '">' . $label . '</a>';
        }

        $html .= $page < $totalPages
            ? '<a class="page-link" href="' . $linkFor($page + 1) . '">&rarr;</a>'
            : '<span class="page-link page-link-disabled">&rarr;</span>';
        $html .= '</div>';

        return $html;
    }
}
