<?php

namespace App\Modules\Reflectie;

use App\Core\Exceptions\NotFoundException;
use App\Core\Exceptions\ValidationException;
use App\Core\TableQuery;
use App\Modules\Reflectie\Models\ReflectieModel;

/**
 * Service/Business-laag voor reflecties: validatie en data-samenstelling voor de API-laag
 * (App\Api\V1\ReflectiesApiController). Kent geen HTTP-concepten. Net als Kennisbank geen
 * scope-autorisatie per item — elk ingelogd account met leesrecht ziet alle reflecties.
 */
class ReflectieService
{
    /** @return array{items:array,pagination:array} */
    public function list(array $queryParams): array
    {
        $allItems = ReflectieModel::allWithRelations();
        $items = TableQuery::apply($allItems, $queryParams, 'titel');
        $pagination = TableQuery::paginate($items, $queryParams);

        return [
            'items' => $pagination['items'],
            'pagination' => [
                'page' => $pagination['page'],
                'perPage' => $pagination['perPage'],
                'totalPages' => $pagination['totalPages'],
                'total' => $pagination['total'],
            ],
        ];
    }

    public function find(int $id): array
    {
        $item = ReflectieModel::findWithRelations($id);
        if ($item === null) {
            throw new NotFoundException("Reflectie {$id} niet gevonden.");
        }

        return ['item' => $item];
    }

    public function create(array $input, array $currentUser): int
    {
        $data = $this->validate($input);
        $data['gebruiker_id'] = $currentUser['id'];

        return ReflectieModel::create($data);
    }

    public function update(int $id, array $input): array
    {
        $huidig = ReflectieModel::find($id);
        if ($huidig === null) {
            throw new NotFoundException("Reflectie {$id} niet gevonden.");
        }

        $data = ReflectieModel::alleenGewijzigdeVelden($huidig, $this->validate($input));
        if ($data !== []) {
            ReflectieModel::update($id, $data);
        }

        return $this->find($id);
    }

    public function delete(int $id): void
    {
        if (ReflectieModel::find($id) === null) {
            throw new NotFoundException("Reflectie {$id} niet gevonden.");
        }

        ReflectieModel::delete($id);
    }

    private function validate(array $input): array
    {
        $titel = trim((string) ($input['titel'] ?? ''));
        $inhoud = trim((string) ($input['inhoud'] ?? ''));

        $errors = [];
        if ($titel === '') {
            $errors['titel'][] = 'Titel is verplicht.';
        }
        if ($inhoud === '') {
            $errors['inhoud'][] = 'Inhoud is verplicht.';
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        return [
            'titel' => $titel,
            'periode' => trim((string) ($input['periode'] ?? '')),
            'inhoud' => $inhoud,
        ];
    }
}
