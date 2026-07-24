<?php

namespace App\Modules\Device;

use App\Core\Exceptions\NotFoundException;
use App\Core\TableQuery;
use App\Modules\Device\Models\DeviceModel;
use App\Modules\Device\Models\DeviceSoftwareModel;

/**
 * Service/Business-laag voor apparaten. Let op: het echte datamodel is een fleet-inventarisatie
 * op basis van CSV-geïmporteerde software (devices.naam, medewerker_id, software_aantal) — geen
 * live monitoring. De Lovable-mockup (modules.device.tsx) modelleert devices met online/offline-
 * status, hostname/os/serie/type/locatie en "Herstart"/"Wipe"-acties, die hier niet bestaan; deze
 * Service dekt daarom alleen lijst/detail/verwijderen. Aanmaken/CSV-import (met upload) blijft op
 * de bestaande server-rendered formulieren/knoppen (DeviceController::store()).
 */
class DeviceService
{
    /** @return array{items:array,pagination:array,filterOptions:array} */
    public function list(array $queryParams): array
    {
        $allItems = DeviceModel::allWithRelations();
        $items = TableQuery::apply($allItems, $queryParams, 'naam');
        $pagination = TableQuery::paginate($items, $queryParams);

        $namen = array_values(array_unique(array_filter(array_column($allItems, 'medewerker_naam'))));
        sort($namen);

        return [
            'items' => $pagination['items'],
            'pagination' => [
                'page' => $pagination['page'],
                'perPage' => $pagination['perPage'],
                'totalPages' => $pagination['totalPages'],
                'total' => $pagination['total'],
            ],
            'filterOptions' => ['medewerker_naam' => array_combine($namen, $namen)],
            'stats' => [
                'totaal' => count($allItems),
                'gekoppeld' => count(array_filter($allItems, fn (array $d) => !empty($d['medewerker_id']))),
                'softwareTotaal' => array_sum(array_map(fn (array $d) => (int) $d['software_aantal'], $allItems)),
                'zonderImport' => count(array_filter($allItems, fn (array $d) => empty($d['laatst_geimporteerd_op']))),
            ],
        ];
    }

    public function find(int $id): array
    {
        $item = DeviceModel::findWithRelations($id);
        if ($item === null) {
            throw new NotFoundException("Apparaat {$id} niet gevonden.");
        }

        return ['item' => $item, 'software' => DeviceSoftwareModel::forDevice($id)];
    }

    public function delete(int $id): void
    {
        if (DeviceModel::find($id) === null) {
            throw new NotFoundException("Apparaat {$id} niet gevonden.");
        }

        DeviceModel::delete($id);
    }
}
