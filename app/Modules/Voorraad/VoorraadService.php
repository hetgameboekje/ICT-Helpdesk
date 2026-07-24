<?php

namespace App\Modules\Voorraad;

use App\Core\Exceptions\NotFoundException;
use App\Core\Exceptions\ValidationException;
use App\Core\TableQuery;
use App\Modules\Voorraad\Models\VoorraadItemModel;
use App\Modules\Voorraad\Models\VoorraadTypeModel;

/**
 * Service/Business-laag voor voorraad. Let op: dit is een per-stuk (barcode/serienummer)
 * voorraadregistratie, geen aantal/minimum-model — de Lovable-mockup (modules.voorraad.tsx)
 * modelleert voorraad als aantal-met-minimumdrempel per artikel ("bijboeken"/"afboeken"), wat hier
 * niet bestaat. Create/update (met DxDiag-upload, serienummer-batches) blijven daarom bewust op de
 * oude server-rendered formulieren (VoorraadController::store()/update()) — deze Service dekt
 * alleen lijst/detail/statuswijziging/verwijderen, dezelfde scope als de JS-pagina nodig heeft.
 */
class VoorraadService
{
    private const STATUS_LABELS = ['op_voorraad' => 'Op voorraad', 'uitgegeven' => 'Uitgegeven', 'afgeschreven' => 'Afgeschreven'];

    /** @return array{items:array,pagination:array,statusCounts:array,filterOptions:array} */
    public function list(array $queryParams): array
    {
        $allItems = VoorraadItemModel::allWithRelations();
        $items = TableQuery::apply($allItems, $queryParams, 'barcode');
        $pagination = TableQuery::paginate($items, $queryParams);

        $types = VoorraadTypeModel::all();
        $typeOptions = [];
        foreach ($types as $t) {
            $typeOptions[$t['naam']] = $t['naam'];
        }

        return [
            'items' => $pagination['items'],
            'pagination' => [
                'page' => $pagination['page'],
                'perPage' => $pagination['perPage'],
                'totalPages' => $pagination['totalPages'],
                'total' => $pagination['total'],
            ],
            'statusCounts' => $this->statusCounts($allItems),
            'filterOptions' => ['type_naam' => $typeOptions, 'status' => self::STATUS_LABELS],
        ];
    }

    public function find(int $id): array
    {
        $item = VoorraadItemModel::findWithRelations($id);
        if ($item === null) {
            throw new NotFoundException("Voorraaditem {$id} niet gevonden.");
        }

        return ['item' => $item];
    }

    public function setStatus(int $id, string $status): array
    {
        if (VoorraadItemModel::find($id) === null) {
            throw new NotFoundException("Voorraaditem {$id} niet gevonden.");
        }
        if (!array_key_exists($status, self::STATUS_LABELS)) {
            throw new ValidationException(['status' => ["Ongeldige status: {$status}."]]);
        }

        VoorraadItemModel::setStatus($id, $status);

        return $this->find($id);
    }

    public function delete(int $id): void
    {
        if (VoorraadItemModel::find($id) === null) {
            throw new NotFoundException("Voorraaditem {$id} niet gevonden.");
        }

        VoorraadItemModel::delete($id);
    }

    private function statusCounts(array $allItems): array
    {
        $counts = ['alle' => count($allItems)];
        foreach (array_keys(self::STATUS_LABELS) as $status) {
            $counts[$status] = 0;
        }
        foreach ($allItems as $item) {
            if (isset($counts[$item['status']])) {
                $counts[$item['status']]++;
            }
        }

        return $counts;
    }
}
