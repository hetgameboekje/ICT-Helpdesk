<?php

namespace App\Modules\Uitgifte;

use App\Core\Exceptions\NotFoundException;
use App\Core\Exceptions\ValidationException;
use App\Core\TableQuery;
use App\Modules\Uitgifte\Models\UitgifteModel;
use App\Modules\Voorraad\Models\VoorraadItemModel;

/**
 * Service/Business-laag voor uitgiften: elke uitgifte is een dunne, transactionele koppeling naar
 * een geserialiseerd voorraad-item (barcode-scan), geen zelfstandig record — zie
 * UitgifteController::store()/retour() (het patroon dat hier 1-op-1 is overgenomen). Geen
 * scope-autorisatie: elk ingelogd account met leesrecht ziet alle uitgiften.
 */
class UitgifteService
{
    /** @return array{items:array,pagination:array,kpis:array} */
    public function list(array $queryParams): array
    {
        $allItems = UitgifteModel::allWithRelations();
        $items = TableQuery::apply($allItems, $queryParams, 'medewerker_naam');
        $pagination = TableQuery::paginate($items, $queryParams);

        $openstaand = count(array_filter($allItems, fn (array $u) => $u['status'] === 'uitgegeven'));

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
                'openstaand' => $openstaand,
                'geretourneerd' => count($allItems) - $openstaand,
            ],
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

    /** Zelfde flow als UitgifteController::store(): barcode niet gevonden → automatisch als 'Overig' aanmaken i.p.v. weigeren. */
    public function create(array $input, array $currentUser): int
    {
        $barcode = trim((string) ($input['barcode'] ?? ''));
        $medewerkerNaam = trim((string) ($input['medewerker_naam'] ?? ''));

        $errors = [];
        if ($barcode === '') {
            $errors['barcode'][] = 'Barcode is verplicht.';
        }
        if ($medewerkerNaam === '') {
            $errors['medewerker_naam'][] = 'Naam is verplicht.';
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $item = VoorraadItemModel::findAvailableByBarcode($barcode);
        $onbekend = false;

        if ($item === null) {
            $itemId = VoorraadItemModel::createOnbekend($barcode, $currentUser['id']);
            $item = VoorraadItemModel::findWithRelations($itemId);
            $onbekend = true;
        }

        $id = UitgifteModel::create([
            'voorraad_item_id' => $item['id'],
            'medewerker_naam' => $medewerkerNaam,
            'uitgegeven_op' => !empty($input['uitgegeven_op']) ? $input['uitgegeven_op'] : date('Y-m-d'),
            'opmerking' => trim((string) ($input['opmerking'] ?? '')) ?: null,
            'toestemming_manager' => !empty($input['toestemming_manager']) ? 1 : 0,
            'uitgegeven_door_id' => $currentUser['id'],
        ]);

        if (!$onbekend) {
            VoorraadItemModel::setStatus((int) $item['id'], 'uitgegeven');
        }

        return $id;
    }

    /** Zelfde flow als UitgifteController::retour(). */
    public function retour(int $id, array $input): array
    {
        $uitgifte = UitgifteModel::findWithRelations($id);
        if ($uitgifte === null) {
            throw new NotFoundException("Uitgifte {$id} niet gevonden.");
        }

        $opmerking = trim((string) ($input['opmerking'] ?? '')) ?: null;
        $resultaat = ($input['resultaat'] ?? '') === 'afgeschreven' ? 'afgeschreven' : 'op_voorraad';

        UitgifteModel::setTeruggegeven($id, date('Y-m-d'), $opmerking);
        VoorraadItemModel::setStatus((int) $uitgifte['voorraad_item_id'], $resultaat);

        return $this->find($id);
    }
}
