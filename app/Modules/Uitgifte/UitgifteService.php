<?php

namespace App\Modules\Uitgifte;

use App\Core\Exceptions\NotFoundException;
use App\Core\Exceptions\ValidationException;
use App\Core\TableQuery;
use App\Modules\Uitgifte\Models\UitgifteModel;
use App\Modules\Voorraad\Models\VoorraadItemModel;

/**
 * Service/Business-laag voor uitgiften (hardware/telefoons/toegangspassen aan medewerkers, met
 * retourregistratie) — dit is het echte tegenhanger van de Lovable-mockup modules.uitgifte.tsx.
 * Let op: geen "soort"-classificatie (hardware/telefoon/toegangspas/overig) op het uitgifte-record
 * zelf — dat leeft op het gekoppelde voorraad_items.type_id, dus de soort-tabs uit de mockup zijn
 * vervangen door een status-filter (uitgegeven/geretourneerd), wat wel een echte kolom is. Aanmaken
 * (barcode/medewerker-autocomplete) blijft op het bestaande server-rendered formulier — er is ook
 * bewust geen destroy-route (uitgiften zijn een permanente historie, alleen retour() muteert ze).
 */
class UitgifteService
{
    /** @return array{items:array,pagination:array,stats:array,filterOptions:array} */
    public function list(array $queryParams): array
    {
        $allItems = UitgifteModel::allWithRelations();
        $items = TableQuery::apply($allItems, $queryParams, 'medewerker_naam');
        $pagination = TableQuery::paginate($items, $queryParams);

        $vandaag = new \DateTimeImmutable('today');
        $weekGeleden = $vandaag->modify('-7 days');
        $dezeWeek = count(array_filter($allItems, function (array $u) use ($weekGeleden) {
            if (empty($u['uitgegeven_op'])) {
                return false;
            }
            return new \DateTimeImmutable($u['uitgegeven_op']) >= $weekGeleden;
        }));

        return [
            'items' => $pagination['items'],
            'pagination' => [
                'page' => $pagination['page'],
                'perPage' => $pagination['perPage'],
                'totalPages' => $pagination['totalPages'],
                'total' => $pagination['total'],
            ],
            'stats' => [
                'totaal' => count($allItems),
                'openstaand' => count(array_filter($allItems, fn (array $u) => $u['status'] === 'uitgegeven')),
                'geretourneerd' => count(array_filter($allItems, fn (array $u) => $u['status'] === 'geretourneerd')),
                'dezeWeek' => $dezeWeek,
            ],
            'filterOptions' => ['status' => ['uitgegeven' => 'Uitgegeven', 'geretourneerd' => 'Geretourneerd']],
        ];
    }

    public function find(int $id): array
    {
        $item = UitgifteModel::findWithRelations($id);
        if ($item === null) {
            throw new NotFoundException("Uitgifte {$id} niet gevonden.");
        }

        return ['item' => $item];
    }

    public function retour(int $id, array $input): array
    {
        $uitgifte = UitgifteModel::findWithRelations($id);
        if ($uitgifte === null) {
            throw new NotFoundException("Uitgifte {$id} niet gevonden.");
        }
        if ($uitgifte['status'] !== 'uitgegeven') {
            throw new ValidationException(['status' => ['Deze uitgifte is al retour genomen.']]);
        }

        $resultaat = ($input['resultaat'] ?? '') === 'afgeschreven' ? 'afgeschreven' : 'op_voorraad';
        $opmerking = trim((string) ($input['opmerking'] ?? '')) ?: null;

        UitgifteModel::setTeruggegeven($id, date('Y-m-d'), $opmerking);
        VoorraadItemModel::setStatus((int) $uitgifte['voorraad_item_id'], $resultaat);

        return $this->find($id);
    }
}
