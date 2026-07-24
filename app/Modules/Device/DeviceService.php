<?php

namespace App\Modules\Device;

use App\Core\Exceptions\NotFoundException;
use App\Core\Exceptions\ValidationException;
use App\Core\TableQuery;
use App\Modules\Device\Models\DeviceModel;
use App\Modules\Device\Models\DeviceSoftwareModel;

/**
 * Service/Business-laag voor apparaten (devices): read + eenvoudige veldupdate (naam/medewerker).
 * Aanmaken loopt uitsluitend via CSV-upload (DeviceController::store()) en blijft daarom op de
 * oude server-rendered route — geen create() hier, zie CLAUDE.md.
 */
class DeviceService
{
    /** @return array{items:array,pagination:array,kpis:array} */
    public function list(array $queryParams): array
    {
        $allItems = DeviceModel::allWithRelations();
        $items = TableQuery::apply($allItems, $queryParams, 'naam');
        $pagination = TableQuery::paginate($items, $queryParams);

        $metKoppeling = count(array_filter($allItems, fn (array $d) => !empty($d['medewerker_id'])));

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
                'gekoppeld' => $metKoppeling,
                'ongekoppeld' => count($allItems) - $metKoppeling,
            ],
        ];
    }

    public function find(int $id): array
    {
        $item = DeviceModel::findWithRelations($id);
        if ($item === null) {
            throw new NotFoundException("Apparaat {$id} niet gevonden.");
        }

        return [
            'item' => $item,
            'software' => DeviceSoftwareModel::forDevice($id),
        ];
    }

    public function update(int $id, array $input): array
    {
        $huidig = DeviceModel::find($id);
        if ($huidig === null) {
            throw new NotFoundException("Apparaat {$id} niet gevonden.");
        }

        $naam = trim((string) ($input['naam'] ?? ''));
        if ($naam === '') {
            throw new ValidationException(['naam' => ['Naam is verplicht.']]);
        }

        $data = DeviceModel::alleenGewijzigdeVelden($huidig, [
            'naam' => $naam,
            'medewerker_id' => !empty($input['medewerker_id']) ? (int) $input['medewerker_id'] : null,
        ]);
        if ($data !== []) {
            DeviceModel::update($id, $data);
        }

        return $this->find($id);
    }

    public function delete(int $id): void
    {
        if (DeviceModel::find($id) === null) {
            throw new NotFoundException("Apparaat {$id} niet gevonden.");
        }

        DeviceModel::delete($id);
    }
}
