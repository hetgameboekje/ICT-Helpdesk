<?php

namespace App\Modules\Voorraad;

use App\Core\Exceptions\NotFoundException;
use App\Core\TableQuery;
use App\Modules\Uitgifte\Models\UitgifteModel;
use App\Modules\Voorraad\Models\VoorraadItemModel;

/**
 * Service/Business-laag voor voorraad: alleen lezen (list/find). Aanmaken/bewerken loopt via de
 * bestaande multi-item/barcode/DxDiag-upload-formulieren (VoorraadController::store()/update()) en
 * blijft daarom op de oude server-rendered routes — te veel eigen logica (serienummer-uniekheid,
 * barcode-opbouw, bestandsupload) om hier zonder risico te herbouwen. Zie CLAUDE.md.
 */
class VoorraadService
{
    /** @return array{items:array,pagination:array,kpis:array} */
    public function list(array $queryParams): array
    {
        $allItems = VoorraadItemModel::allWithRelations();
        $items = TableQuery::apply($allItems, $queryParams, 'barcode');
        $pagination = TableQuery::paginate($items, $queryParams);

        $statusCounts = ['op_voorraad' => 0, 'uitgegeven' => 0, 'afgeschreven' => 0];
        foreach ($allItems as $item) {
            if (isset($statusCounts[$item['status']])) {
                $statusCounts[$item['status']]++;
            }
        }

        return [
            'items' => $pagination['items'],
            'pagination' => [
                'page' => $pagination['page'],
                'perPage' => $pagination['perPage'],
                'totalPages' => $pagination['totalPages'],
                'total' => $pagination['total'],
            ],
            'kpis' => [
                'aantal' => count($allItems),
                'opVoorraad' => $statusCounts['op_voorraad'],
                'uitgegeven' => $statusCounts['uitgegeven'],
                'afgeschreven' => $statusCounts['afgeschreven'],
            ],
        ];
    }

    public function find(int $id): array
    {
        $item = VoorraadItemModel::findWithRelations($id);
        if ($item === null) {
            throw new NotFoundException("Voorraaditem {$id} niet gevonden.");
        }

        return [
            'item' => $item,
            'uitgiften' => UitgifteModel::forVoorraadItem($id),
        ];
    }
}
