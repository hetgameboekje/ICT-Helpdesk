<?php

namespace App\Modules\Reflectie;

use App\Core\Exceptions\NotFoundException;
use App\Core\Exceptions\ValidationException;
use App\Core\TableQuery;
use App\Modules\Reflectie\Models\ReflectieLogModel;
use App\Modules\Reflectie\Models\ReflectieModel;

/**
 * Service/Business-laag voor reflecties, zelfde patroon als KennisbankService: geen scope-
 * autorisatie per item (elk ingelogd account met leesrecht ziet alle reflecties, net als in de
 * bestaande ReflectieController).
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

        return ['item' => $item, 'logs' => ReflectieLogModel::forReflectie($id)];
    }

    public function create(array $input, array $currentUser): int
    {
        $data = $this->validate($input);
        $data['gebruiker_id'] = $currentUser['id'];

        return ReflectieModel::create($data);
    }

    public function update(int $id, array $input): array
    {
        if (ReflectieModel::find($id) === null) {
            throw new NotFoundException("Reflectie {$id} niet gevonden.");
        }

        ReflectieModel::update($id, $this->validate($input));

        return $this->find($id);
    }

    public function delete(int $id): void
    {
        if (ReflectieModel::find($id) === null) {
            throw new NotFoundException("Reflectie {$id} niet gevonden.");
        }

        ReflectieModel::delete($id);
    }

    /** Opmerking toevoegen — zelfde regel als ReflectieLogController::store(): titel én omschrijving zijn beide verplicht. */
    public function addLog(int $id, array $input, array $currentUser): array
    {
        if (ReflectieModel::find($id) === null) {
            throw new NotFoundException("Reflectie {$id} niet gevonden.");
        }

        $titel = trim((string) ($input['titel'] ?? ''));
        $omschrijving = trim((string) ($input['omschrijving'] ?? ''));

        $errors = [];
        if ($titel === '') {
            $errors['titel'][] = 'Titel is verplicht.';
        }
        if ($omschrijving === '') {
            $errors['omschrijving'][] = 'Omschrijving is verplicht.';
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        ReflectieLogModel::create([
            'reflectie_id' => $id,
            'user_id' => $currentUser['id'],
            'titel' => $titel,
            'omschrijving' => $omschrijving,
        ]);

        return $this->find($id);
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
