<?php

namespace App\Modules\Schijfgebruik;

use App\Core\TableQuery;
use App\Modules\Schijfgebruik\Models\SchijfgebruikSchijfModel;

/**
 * Service/Business-laag voor schijfgebruik, zelfde filters als de bestaande
 * SchijfgebruikController::index() (min_gebruik/alleen_waarschuwingen/zoeken op apparaat+gebruiker).
 * Sluit qua vorm (ring-gauge per schijf, meest vol eerst) goed aan bij Lovable's
 * modules.schijfgebruik.tsx — dit systeem levert zelfs meer echte data dan de mockup: online/
 * offline-status en "herstart aanbevolen"/"schijf bijna vol"-waarschuwingen komen uit
 * SchijfgebruikHealth::evaluate() i.p.v. verzonnen te worden.
 */
class SchijfgebruikService
{
    /** @return array{items:array,pagination:array,stats:array,filterOptions:array} */
    public function list(array $queryParams): array
    {
        $allItems = array_map(
            fn (array $row) => array_merge($row, SchijfgebruikHealth::evaluate($row)),
            SchijfgebruikSchijfModel::allWithDevice()
        );

        $minGebruik = trim((string) ($queryParams['min_gebruik'] ?? ''));
        if ($minGebruik !== '' && is_numeric($minGebruik)) {
            $drempel = (int) $minGebruik;
            $allItems = array_values(array_filter($allItems, fn (array $row) => (int) $row['gebruik_percentage'] >= $drempel));
        }

        if (($queryParams['alleen_waarschuwingen'] ?? '') === '1') {
            $allItems = array_values(array_filter($allItems, fn (array $row) => !empty($row['waarschuwingen'])));
        }

        $search = trim((string) ($queryParams['q'] ?? ''));
        if ($search !== '') {
            $allItems = array_values(array_filter(
                $allItems,
                fn (array $row) => stripos($row['naam'], $search) !== false || stripos((string) $row['laatste_login'], $search) !== false
            ));
        }

        $filterOptions = $this->filterOptions($allItems);

        $params = array_diff_key($queryParams, ['min_gebruik' => null, 'q' => null, 'alleen_waarschuwingen' => null]);
        $params['sort'] = $params['sort'] ?? 'gebruik_percentage';
        $params['dir'] = $params['dir'] ?? 'desc';

        $items = TableQuery::apply($allItems, $params);
        $pagination = TableQuery::paginate($items, $queryParams);

        return [
            'items' => $pagination['items'],
            'pagination' => [
                'page' => $pagination['page'],
                'perPage' => $pagination['perPage'],
                'totalPages' => $pagination['totalPages'],
                'total' => $pagination['total'],
            ],
            'stats' => $this->stats($allItems),
            'filterOptions' => $filterOptions,
        ];
    }

    private function stats(array $allItems): array
    {
        $totaalBytes = array_sum(array_column($allItems, 'capaciteit_bytes'));
        $gebruiktBytes = array_sum(array_map(
            fn (array $r) => ((int) $r['capaciteit_bytes']) * ((int) $r['gebruik_percentage']) / 100,
            $allItems
        ));

        return [
            'volumes' => count($allItems),
            'totaalTb' => round($totaalBytes / 1e12, 1),
            'gebruiktTb' => round($gebruiktBytes / 1e12, 1),
            'kritiek' => count(array_filter($allItems, fn (array $r) => (int) $r['gebruik_percentage'] >= 90)),
        ];
    }

    private function filterOptions(array $items): array
    {
        $build = function (string $key) use ($items): array {
            $values = array_values(array_unique(array_filter(array_column($items, $key))));
            sort($values);
            return array_combine($values, $values);
        };

        return [
            'organisatie' => $build('organisatie'),
            'locatie' => $build('locatie'),
            'letter' => $build('letter'),
        ];
    }
}
