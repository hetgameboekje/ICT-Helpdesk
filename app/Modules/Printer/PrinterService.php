<?php

namespace App\Modules\Printer;

use App\Core\Exceptions\NotFoundException;
use App\Core\Exceptions\ValidationException;
use App\Core\TableQuery;
use App\Modules\Printer\Models\PrinterModel;

/**
 * Service/Business-laag voor printers: eenvoudige catalogus zonder status-flow. Kent geen
 * HTTP-concepten — de API-laag (App\Api\V1\PrintersApiController) parsed de request en roept dit aan.
 */
class PrinterService
{
    /** @return array{items:array,pagination:array,kpis:array} */
    public function list(array $queryParams): array
    {
        $allItems = PrinterModel::allWithRelations();
        $items = TableQuery::apply($allItems, $queryParams, 'naam');
        $pagination = TableQuery::paginate($items, $queryParams);

        $types = array_unique(array_filter(array_column($allItems, 'type')));
        $locaties = array_unique(array_filter(array_column($allItems, 'computer_naam')));

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
                'aantalTypen' => count($types),
                'aantalServers' => count($locaties),
            ],
        ];
    }

    public function find(int $id): array
    {
        $item = PrinterModel::findWithRelations($id);
        if ($item === null) {
            throw new NotFoundException("Printer {$id} niet gevonden.");
        }

        return [
            'item' => $item,
            'installCommand' => PrinterModel::buildInstallCommand($item),
        ];
    }

    public function create(array $input, array $currentUser): int
    {
        $data = $this->validate($input);
        $data['aangemaakt_door_id'] = $currentUser['id'];

        return PrinterModel::create($data);
    }

    public function update(int $id, array $input): array
    {
        $huidig = PrinterModel::find($id);
        if ($huidig === null) {
            throw new NotFoundException("Printer {$id} niet gevonden.");
        }

        $data = PrinterModel::alleenGewijzigdeVelden($huidig, $this->validate($input));
        if ($data !== []) {
            PrinterModel::update($id, $data);
        }

        return $this->find($id);
    }

    public function delete(int $id): void
    {
        if (PrinterModel::find($id) === null) {
            throw new NotFoundException("Printer {$id} niet gevonden.");
        }

        PrinterModel::delete($id);
    }

    private function validate(array $input): array
    {
        $naam = trim((string) ($input['naam'] ?? ''));
        if ($naam === '') {
            throw new ValidationException(['naam' => ['Naam is verplicht.']]);
        }

        return [
            'naam' => $naam,
            'computer_naam' => trim((string) ($input['computer_naam'] ?? '')) ?: null,
            'type' => trim((string) ($input['type'] ?? '')) ?: 'Local',
            'driver_naam' => trim((string) ($input['driver_naam'] ?? '')) ?: null,
            'ip_adres' => trim((string) ($input['ip_adres'] ?? '')) ?: null,
            'opmerking' => trim((string) ($input['opmerking'] ?? '')) ?: null,
        ];
    }
}
